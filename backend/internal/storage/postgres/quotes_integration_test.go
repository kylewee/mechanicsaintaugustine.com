//go:build integration

package postgres_test

import (
	"testing"

	"github.com/ezmobilemechanic/platform/internal/domain/customers"
	domainquotes "github.com/ezmobilemechanic/platform/internal/domain/quotes"
	"github.com/ezmobilemechanic/platform/internal/domain/vehicles"
	pgstorage "github.com/ezmobilemechanic/platform/internal/storage/postgres"
)

func TestQuoteRepositoryIntegration(t *testing.T) {
	db := setupTestDB(t)
	defer db.Close()

	custRepo := pgstorage.NewCustomerRepository(db)
	vehicleRepo := pgstorage.NewVehicleRepository(db)
	quoteRepo := pgstorage.NewQuoteRepository(db)

	customer, err := custRepo.Save(customers.Customer{FirstName: "Quote", Email: "quote@example.com"})
	if err != nil {
		t.Fatalf("create customer: %v", err)
	}

	vehicle, err := vehicleRepo.Save(vehicles.Vehicle{CustomerID: customer.ID, Make: "Ford"})
	if err != nil {
		t.Fatalf("create vehicle: %v", err)
	}

	created, err := quoteRepo.Save(domainquotes.Quote{
		CustomerID: customer.ID,
		VehicleID:  vehicle.ID,
		Status:     domainquotes.StatusDraft,
		LineItems: []domainquotes.LineItem{
			{
				Description: "Brake Pads",
				Quantity:    1,
				UnitPrice:   15000,
			},
		},
		TotalAmount: 15000,
	})
	if err != nil {
		t.Fatalf("save quote: %v", err)
	}

	fetched, err := quoteRepo.FindByID(created.ID)
	if err != nil {
		t.Fatalf("find quote: %v", err)
	}
	if len(fetched.LineItems) != 1 {
		t.Fatalf("expected 1 line item, got %d", len(fetched.LineItems))
	}

	list, err := quoteRepo.ListByCustomer(customer.ID, 0, 10)
	if err != nil {
		t.Fatalf("list quotes: %v", err)
	}
	if len(list) == 0 {
		t.Fatalf("expected at least one quote")
	}
}
