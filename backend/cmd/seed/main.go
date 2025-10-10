package main

import (
	"context"
	"fmt"
	"os"

	"github.com/ezmobilemechanic/platform/internal/config"
	"github.com/ezmobilemechanic/platform/internal/database"
	"github.com/ezmobilemechanic/platform/internal/domain/customers"
	domainquotes "github.com/ezmobilemechanic/platform/internal/domain/quotes"
	"github.com/ezmobilemechanic/platform/internal/domain/vehicles"
	"github.com/ezmobilemechanic/platform/internal/logger"
	pgstorage "github.com/ezmobilemechanic/platform/internal/storage/postgres"
)

func main() {
	cfg, err := config.Load()
	if err != nil {
		slog := logger.New("development")
		slog.Error("failed to load config", "err", err)
		os.Exit(1)
	}

	logr := logger.New(cfg.Env)

	if cfg.DataBackend != "postgres" {
		logr.Error("seed command requires DATA_BACKEND=postgres")
		os.Exit(1)
	}

	ctx := context.Background()

	db, err := database.Connect(ctx, database.Options{
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
	defer db.Close()

	migrator := database.NewSQLMigrator(db.DB, database.MigrationsFS(), "db/migrations", logr)
	if err := db.RunMigrations(ctx, migrator); err != nil {
		logr.Error("migrations failed", "err", err)
		os.Exit(1)
	}

	custRepo := pgstorage.NewCustomerRepository(db.DB)
	vehicleRepo := pgstorage.NewVehicleRepository(db.DB)
	quoteRepo := pgstorage.NewQuoteRepository(db.DB)

	sampleCustomers := []customers.Customer{
		{FirstName: "Alex", LastName: "Driver", Email: "alex@example.com", Phone: "904-555-0101", MarketingOpt: true},
		{FirstName: "Jordan", LastName: "Mechanic", Email: "jordan@example.com", Phone: "904-555-0202"},
	}

	createdCustomers := make([]customers.Customer, 0, len(sampleCustomers))
	for _, c := range sampleCustomers {
		saved, err := custRepo.Save(c)
		if err != nil {
			logr.Error("failed to seed customer", "email", c.Email, "err", err)
			os.Exit(1)
		}
		createdCustomers = append(createdCustomers, saved)
	}

	sampleVehicles := []vehicles.Vehicle{
		{CustomerID: createdCustomers[0].ID, VIN: "1FTNE14W67DA12345", Year: 2017, Make: "Ford", Model: "Transit", Trim: "250", Engine: "3.7L V6", Mileage: 120000},
		{CustomerID: createdCustomers[1].ID, VIN: "5YJSA1CN5DFP12345", Year: 2015, Make: "Tesla", Model: "Model S", Trim: "85", Engine: "Electric", Mileage: 85000},
	}

	createdVehicles := make([]vehicles.Vehicle, 0, len(sampleVehicles))
	for _, v := range sampleVehicles {
		saved, err := vehicleRepo.Save(v)
		if err != nil {
			logr.Error("failed to seed vehicle", "vin", v.VIN, "err", err)
			os.Exit(1)
		}
		createdVehicles = append(createdVehicles, saved)
	}

	sampleQuotes := []domainquotes.Quote{
		{
			CustomerID: createdCustomers[0].ID,
			VehicleID:  createdVehicles[0].ID,
			Status:     domainquotes.StatusDraft,
			LineItems: []domainquotes.LineItem{
				{Description: "Brake Pad Replacement", Quantity: 1, UnitPrice: 18000, LaborHours: 1.5},
				{Description: "Rotor Resurfacing", Quantity: 2, UnitPrice: 12000, LaborHours: 2.0},
			},
		},
		{
			CustomerID: createdCustomers[1].ID,
			VehicleID:  createdVehicles[1].ID,
			Status:     domainquotes.StatusDraft,
			LineItems: []domainquotes.LineItem{
				{Description: "Battery Diagnostic", Quantity: 1, UnitPrice: 9500, LaborHours: 0.8},
			},
		},
	}

	for i := range sampleQuotes {
		sampleQuotes[i].TotalAmount = computeTotal(sampleQuotes[i].LineItems)
		saved, err := quoteRepo.Save(sampleQuotes[i])
		if err != nil {
			logr.Error("failed to seed quote", "customer_id", sampleQuotes[i].CustomerID, "err", err)
			os.Exit(1)
		}
		logr.Info("seeded quote", "quote_id", saved.ID, "customer_id", saved.CustomerID)
	}

	for _, c := range createdCustomers {
		fmt.Printf("Customer: %s %s (%s)\n", c.FirstName, c.LastName, c.Email)
	}
	for _, v := range createdVehicles {
		fmt.Printf("Vehicle: %s %s %s (%s)\n", v.Make, v.Model, v.Trim, v.ID)
	}

	logr.Info("seed complete")
}

func computeTotal(items []domainquotes.LineItem) int64 {
	var total int64
	for _, item := range items {
		total += int64(item.Quantity) * item.UnitPrice
	}
	return total
}
