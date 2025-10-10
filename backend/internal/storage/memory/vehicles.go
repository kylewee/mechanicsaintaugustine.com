package memory

import (
	"sort"
	"sync"
	"time"

	"github.com/ezmobilemechanic/platform/internal/domain/vehicles"
)

// VehicleRepository is an in-memory implementation of vehicles.Repository.
type VehicleRepository struct {
	mu       sync.RWMutex
	vehicles map[string]vehicles.Vehicle
}

// NewVehicleRepository creates an in-memory vehicle repo.
func NewVehicleRepository() *VehicleRepository {
	return &VehicleRepository{
		vehicles: make(map[string]vehicles.Vehicle),
	}
}

func (r *VehicleRepository) FindByID(id string) (vehicles.Vehicle, error) {
	r.mu.RLock()
	defer r.mu.RUnlock()

	v, ok := r.vehicles[id]
	if !ok {
		return vehicles.Vehicle{}, vehicles.ErrNotFound
	}
	return v, nil
}

func (r *VehicleRepository) ListByCustomer(customerID string) ([]vehicles.Vehicle, error) {
	r.mu.RLock()
	defer r.mu.RUnlock()

	var list []vehicles.Vehicle
	for _, v := range r.vehicles {
		if v.CustomerID == customerID {
			list = append(list, v)
		}
	}

	sort.Slice(list, func(i, j int) bool {
		return list[i].CreatedAt.Before(list[j].CreatedAt)
	})

	return list, nil
}

func (r *VehicleRepository) Save(vehicle vehicles.Vehicle) (vehicles.Vehicle, error) {
	r.mu.Lock()
	defer r.mu.Unlock()

	now := time.Now().UTC()
	if vehicle.ID == "" {
		vehicle.ID = newID()
		vehicle.CreatedAt = now
	} else {
		existing, ok := r.vehicles[vehicle.ID]
		if ok && vehicle.CreatedAt.IsZero() {
			vehicle.CreatedAt = existing.CreatedAt
		}
	}
	vehicle.UpdatedAt = now
	r.vehicles[vehicle.ID] = vehicle
	return vehicle, nil
}
