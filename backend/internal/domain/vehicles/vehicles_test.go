package vehicles_test

import (
	"testing"

	"github.com/ezmobilemechanic/platform/internal/domain/vehicles"
	"github.com/ezmobilemechanic/platform/internal/storage/memory"
)

func TestVehicleServiceCreateAndGet(t *testing.T) {
	repo := memory.NewVehicleRepository()
	svc := vehicles.NewService(repo)

	created, err := svc.Create(vehicles.CreateInput{
		CustomerID: "cust-123",
		VIN:        "VIN123",
		Year:       2020,
		Make:       "Ford",
		Model:      "Transit",
	})
	if err != nil {
		t.Fatalf("create failed: %v", err)
	}

	fetched, err := svc.Get(created.ID)
	if err != nil {
		t.Fatalf("get failed: %v", err)
	}
	if fetched.ID != created.ID {
		t.Fatalf("expected ID %s, got %s", created.ID, fetched.ID)
	}
}

func TestVehicleServiceListForCustomer(t *testing.T) {
	repo := memory.NewVehicleRepository()
	svc := vehicles.NewService(repo)

	const custA = "cust-a"
	const custB = "cust-b"

	// Two vehicles for customer A, one for B
	if _, err := svc.Create(vehicles.CreateInput{CustomerID: custA, Make: "Ford"}); err != nil {
		t.Fatalf("create failed: %v", err)
	}
	if _, err := svc.Create(vehicles.CreateInput{CustomerID: custA, Make: "Honda"}); err != nil {
		t.Fatalf("create failed: %v", err)
	}
	if _, err := svc.Create(vehicles.CreateInput{CustomerID: custB, Make: "Toyota"}); err != nil {
		t.Fatalf("create failed: %v", err)
	}

	list, err := svc.ListForCustomer(custA)
	if err != nil {
		t.Fatalf("list failed: %v", err)
	}
	if len(list) != 2 {
		t.Fatalf("expected 2 vehicles for customer A, got %d", len(list))
	}
}
