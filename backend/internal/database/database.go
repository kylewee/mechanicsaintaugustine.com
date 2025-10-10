package database

import (
	"context"
	"database/sql"
	"errors"
	"log/slog"
	"time"
)

// Options configures the SQL database connection.
type Options struct {
	Driver          string
	DSN             string
	MaxOpenConns    int
	MaxIdleConns    int
	ConnMaxLifetime time.Duration
	ConnMaxIdleTime time.Duration
	Logger          *slog.Logger
	PingTimeout     time.Duration
}

const defaultPingTimeout = 5 * time.Second

// DB wraps *sql.DB to centralize lifecycle management.
type DB struct {
	*sql.DB
	logger *slog.Logger
}

// Connect initializes a pooled SQL connection using the provided options.
func Connect(ctx context.Context, opts Options) (*DB, error) {
	if opts.Driver == "" {
		return nil, errors.New("database driver is required")
	}
	if opts.DSN == "" {
		return nil, errors.New("database DSN is required")
	}

	log := opts.Logger
	if log == nil {
		log = slog.Default()
	}

	pool, err := sql.Open(opts.Driver, opts.DSN)
	if err != nil {
		return nil, err
	}

	if opts.MaxOpenConns > 0 {
		pool.SetMaxOpenConns(opts.MaxOpenConns)
	}
	if opts.MaxIdleConns > 0 {
		pool.SetMaxIdleConns(opts.MaxIdleConns)
	}
	if opts.ConnMaxLifetime > 0 {
		pool.SetConnMaxLifetime(opts.ConnMaxLifetime)
	}
	if opts.ConnMaxIdleTime > 0 {
		pool.SetConnMaxIdleTime(opts.ConnMaxIdleTime)
	}

	pingTimeout := opts.PingTimeout
	if pingTimeout <= 0 {
		pingTimeout = defaultPingTimeout
	}

	pingCtx, cancel := context.WithTimeout(ctx, pingTimeout)
	defer cancel()

	if err := pool.PingContext(pingCtx); err != nil {
		pool.Close()
		return nil, err
	}

	log.Info("database connected", "driver", opts.Driver)

	return &DB{DB: pool, logger: log}, nil
}

// Close releases database resources.
func (db *DB) Close() error {
	if db == nil || db.DB == nil {
		return nil
	}
	return db.DB.Close()
}

// RunMigrations is a placeholder for future migration wiring.
func (db *DB) RunMigrations(ctx context.Context, migrator Migrator) error {
	if migrator == nil {
		db.logger.Info("no migrator configured; skipping migrations")
		return nil
	}

	db.logger.Info("running migrations")
	if err := migrator.Up(ctx); err != nil {
		return err
	}

	db.logger.Info("migrations completed")
	return nil
}
