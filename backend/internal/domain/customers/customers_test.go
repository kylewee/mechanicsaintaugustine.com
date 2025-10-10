package customers_test

import (
	"testing"

	"github.com/ezmobilemechanic/platform/internal/domain/customers"
	"github.com/ezmobilemechanic/platform/internal/storage/memory"
)

func TestServiceCreateAndGet(t *testing.T) {
	repo := memory.NewCustomerRepository()
	svc := customers.NewService(repo)

	created, err := svc.Create(customers.CreateInput{
		FirstName:    "Alex",
		LastName:     "Driver",
		Email:        "alex@example.com",
		Phone:        "555-1234",
		MarketingOpt: true,
	})
	if err != nil {
		t.Fatalf("create failed: %v", err)
	}
	if created.ID == "" {
		t.Fatalf("expected ID to be set")
	}
	if !created.CreatedAt.Before(created.UpdatedAt) && !created.CreatedAt.Equal(created.UpdatedAt) {
		t.Fatalf("expected timestamps to be set")
	}

	fetched, err := svc.Get(created.ID)
	if err != nil {
		t.Fatalf("get failed: %v", err)
	}
	if fetched.Email != "alex@example.com" {
		t.Fatalf("unexpected email: %s", fetched.Email)
	}
}

func TestServiceUpdate(t *testing.T) {
	repo := memory.NewCustomerRepository()
	svc := customers.NewService(repo)

	created, err := svc.Create(customers.CreateInput{
		FirstName: "Jo",
		Email:     "initial@example.com",
	})
	if err != nil {
		t.Fatalf("create failed: %v", err)
	}

	newEmail := "updated@example.com"
	updated, err := svc.Update(created.ID, customers.UpdateInput{Email: &newEmail})
	if err != nil {
		t.Fatalf("update failed: %v", err)
	}
	if updated.Email != newEmail {
		t.Fatalf("email not updated: got %s", updated.Email)
	}
}

func TestServiceList(t *testing.T) {
	repo := memory.NewCustomerRepository()
	svc := customers.NewService(repo)

	for i := 0; i < 3; i++ {
		_, err := svc.Create(customers.CreateInput{FirstName: "Foo", Email: "foo@example.com"})
		if err != nil {
			t.Fatalf("create failed: %v", err)
		}
	}

	got, err := svc.List(0, 2)
	if err != nil {
		t.Fatalf("list failed: %v", err)
	}
	if len(got) != 2 {
		t.Fatalf("expected 2 customers, got %d", len(got))
	}
}
