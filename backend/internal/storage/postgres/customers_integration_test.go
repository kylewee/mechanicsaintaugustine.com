//go:build integration

package postgres_test

import (
	"testing"

	"github.com/ezmobilemechanic/platform/internal/domain/customers"
	pgstorage "github.com/ezmobilemechanic/platform/internal/storage/postgres"
)

func TestCustomerRepositoryIntegration(t *testing.T) {
	db := setupTestDB(t)
	defer db.Close()

	repo := pgstorage.NewCustomerRepository(db)

	created, err := repo.Save(customers.Customer{FirstName: "Integration", Email: "integration@example.com"})
	if err != nil {
		t.Fatalf("save customer failed: %v", err)
	}

	fetched, err := repo.FindByID(created.ID)
	if err != nil {
		t.Fatalf("find customer failed: %v", err)
	}
	if fetched.Email != created.Email {
		t.Fatalf("expected email %s, got %s", created.Email, fetched.Email)
	}

	list, err := repo.List(0, 10)
	if err != nil {
		t.Fatalf("list customers failed: %v", err)
	}
	if len(list) == 0 {
		t.Fatalf("expected at least one customer in list")
	}
}
