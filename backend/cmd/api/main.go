package main

import (
	"context"
	"fmt"
	"os"
	"os/signal"
	"syscall"

	"log/slog"

	"github.com/ezmobilemechanic/platform/internal/config"
	"github.com/ezmobilemechanic/platform/internal/database"
	"github.com/ezmobilemechanic/platform/internal/domain"
	"github.com/ezmobilemechanic/platform/internal/httpapi"
	"github.com/ezmobilemechanic/platform/internal/logger"
	"github.com/ezmobilemechanic/platform/internal/server"
	"github.com/ezmobilemechanic/platform/internal/storage/memory"
	pgstorage "github.com/ezmobilemechanic/platform/internal/storage/postgres"
)

func main() {
	cfg, err := config.Load()
	if err != nil {
		slog.Error("failed to load config", "err", err)
		os.Exit(1)
	}

	logr := logger.New(cfg.Env)

	baseCtx := context.Background()

	var db *database.DB
	if cfg.DataBackend == "postgres" {
		db, err = database.Connect(baseCtx, database.Options{
			Driver:          cfg.DatabaseDriver,
			DSN:             cfg.DatabaseURL,
			MaxOpenConns:    cfg.DBMaxOpenConns,
			MaxIdleConns:    cfg.DBMaxIdleConns,
			ConnMaxLifetime: cfg.DBConnMaxLifetime,
			ConnMaxIdleTime: cfg.DBConnMaxIdleTime,
			Logger:          logr,
		})
		if err != nil {
			logr.Error("failed to connect database", "err", err)
			os.Exit(1)
		}
		defer func() {
			if cerr := db.Close(); cerr != nil {
				logr.Error("error closing database", "err", cerr)
			}
		}()

		migrator := database.NewSQLMigrator(db.DB, database.MigrationsFS(), "db/migrations", logr)
		if err := db.RunMigrations(baseCtx, migrator); err != nil {
			logr.Error("database migrations failed", "err", err)
			os.Exit(1)
		}
	}

	domainContainer, err := buildDomainContainer(cfg, logr, db)
	if err != nil {
		logr.Error("failed to init domain container", "err", err)
		os.Exit(1)
	}

	srv := server.New(cfg, logr)

	httpapi.Register(srv.Mux(), logr, domainContainer)

	go func() {
		if err := srv.Run(); err != nil {
			logr.Error("server error", "err", err)
			os.Exit(1)
		}
	}()

	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	ctx, cancel := context.WithTimeout(context.Background(), cfg.ShutdownTimeout)
	defer cancel()

	if err := srv.Shutdown(ctx); err != nil {
		logr.Error("server shutdown failed", "err", err)
		os.Exit(1)
	}
}

func buildDomainContainer(cfg config.Config, logr *slog.Logger, db *database.DB) (domain.Container, error) {
	switch cfg.DataBackend {
	case "memory":
		logr.Info("using in-memory repositories (DATA_BACKEND=memory)")
		return domain.New(domain.Options{
			CustomerRepo: memory.NewCustomerRepository(),
			VehicleRepo:  memory.NewVehicleRepository(),
			QuoteRepo:    memory.NewQuoteRepository(),
			UserRepo:     memory.NewUserRepository(),
		}), nil
	case "postgres":
		if db == nil {
			return domain.Container{}, fmt.Errorf("postgres backend requires database connection")
		}
		logr.Info("using postgres repositories (DATA_BACKEND=postgres)")
		sqlDB := db.DB
		return domain.New(domain.Options{
			CustomerRepo: pgstorage.NewCustomerRepository(sqlDB),
			VehicleRepo:  pgstorage.NewVehicleRepository(sqlDB),
			QuoteRepo:    pgstorage.NewQuoteRepository(sqlDB),
			UserRepo:     pgstorage.NewUserRepository(sqlDB),
		}), nil
	default:
		return domain.Container{}, fmt.Errorf("unsupported data backend: %s", cfg.DataBackend)
	}
}
