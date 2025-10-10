package vehicles

import (
	"errors"
	"testing"
	"time"
)

// mockRepository implements Repository for testing.
type mockRepository struct {
	vehicles map[string]Vehicle
}

func (m *mockRepository) FindByID(id string) (Vehicle, error) {
	v, ok := m.vehicles[id]
	if !ok {
		return Vehicle{}, ErrNotFound
	}
	return v, nil
}

func (m *mockRepository) ListByCustomer(customerID string) ([]Vehicle, error) {
	var result []Vehicle
	for _, v := range m.vehicles {
		if v.CustomerID == customerID {
			result = append(result, v)
		}
	}
	return result, nil
}

func (m *mockRepository) Save(vehicle Vehicle) (Vehicle, error) {
	vehicle.ID = "new-id"
	vehicle.CreatedAt = time.Now()
	vehicle.UpdatedAt = time.Now()
	m.vehicles[vehicle.ID] = vehicle
	return vehicle, nil
}

func TestService_Get(t *testing.T) {
	repo := &mockRepository{
		vehicles: map[string]Vehicle{
			"v1": {ID: "v1", CustomerID: "c1", VIN: "123"},
		},
	}
	svc := NewService(repo)

	v, err := svc.Get("v1")
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if v.ID != "v1" {
		t.Errorf("expected ID 'v1', got %v", v.ID)
	}

	_, err = svc.Get("notfound")
	if !errors.Is(err, ErrNotFound) {
		t.Errorf("expected ErrNotFound, got %v", err)
	}
}

func TestService_ListForCustomer(t *testing.T) {
	repo := &mockRepository{
		vehicles: map[string]Vehicle{
			"v1": {ID: "v1", CustomerID: "c1"},
			"v2": {ID: "v2", CustomerID: "c2"},
			"v3": {ID: "v3", CustomerID: "c1"},
		},
	}
	svc := NewService(repo)

	list, err := svc.ListForCustomer("c1")
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if len(list) != 2 {
		t.Errorf("expected 2 vehicles, got %d", len(list))
	}
}

func TestService_Create(t *testing.T) {
	repo := &mockRepository{vehicles: make(map[string]Vehicle)}
	svc := NewService(repo)

	input := CreateInput{
		CustomerID: "c1",
		VIN:        "VIN123",
		Year:       2020,
		Make:       "Toyota",
		Model:      "Camry",
		Trim:       "LE",
		Engine:     "2.5L",
		Mileage:    10000,
	}
	v, err := svc.Create(input)
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}
	if v.CustomerID != input.CustomerID || v.VIN != input.VIN {
		t.Errorf("vehicle fields not set correctly")
	}
	if v.ID == "" {
		t.Errorf("expected vehicle ID to be set")
	}
}

func TestNullRepository(t *testing.T) {
	var repo Repository = NullRepository{}

	_, err := repo.FindByID("id")
	if !errors.Is(err, ErrNotImplemented) {
		t.Errorf("expected ErrNotImplemented, got %v", err)
	}

	_, err = repo.ListByCustomer("cid")
	if !errors.Is(err, ErrNotImplemented) {
		t.Errorf("expected ErrNotImplemented, got %v", err)
	}

	_, err = repo.Save(Vehicle{})
	if !errors.Is(err, ErrNotImplemented) {
		t.Errorf("expected ErrNotImplemented, got %v", err)
	}
}
