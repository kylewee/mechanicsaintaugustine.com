package vehicles

import (
	"errors"
	"time"
)

var (
	ErrNotImplemented = errors.New("vehicles repository: not implemented")
	ErrNotFound       = errors.New("vehicle not found")
)

// Vehicle describes a customer's vehicle.
type Vehicle struct {
	ID         string
	CustomerID string
	VIN        string
	Year       int
	Make       string
	Model      string
	Trim       string
	Engine     string
	Mileage    int
	CreatedAt  time.Time
	UpdatedAt  time.Time
}

// Repository abstracts persistence for vehicles.
type Repository interface {
	FindByID(id string) (Vehicle, error)
	ListByCustomer(customerID string) ([]Vehicle, error)
	Save(vehicle Vehicle) (Vehicle, error)
}

// NullRepository stub implementation returning ErrNotImplemented.
type NullRepository struct{}

func (NullRepository) FindByID(id string) (Vehicle, error) {
	return Vehicle{}, ErrNotImplemented
}

func (NullRepository) ListByCustomer(customerID string) ([]Vehicle, error) {
	return nil, ErrNotImplemented
}

func (NullRepository) Save(vehicle Vehicle) (Vehicle, error) {
	return Vehicle{}, ErrNotImplemented
}

// Service defines operations for vehicle management.
type Service interface {
	Get(id string) (Vehicle, error)
	ListForCustomer(customerID string) ([]Vehicle, error)
	Create(input CreateInput) (Vehicle, error)
}

// CreateInput is used to create a new vehicle.
type CreateInput struct {
	CustomerID string
	VIN        string
	Year       int
	Make       string
	Model      string
	Trim       string
	Engine     string
	Mileage    int
}

// NewService creates a vehicle service.
func NewService(repo Repository) Service {
	return &service{repo: repo}
}

type service struct {
	repo Repository
}

func (s *service) Get(id string) (Vehicle, error) {
	return s.repo.FindByID(id)
}

func (s *service) ListForCustomer(customerID string) ([]Vehicle, error) {
	return s.repo.ListByCustomer(customerID)
}

func (s *service) Create(input CreateInput) (Vehicle, error) {
	vehicle := Vehicle{
		CustomerID: input.CustomerID,
		VIN:        input.VIN,
		Year:       input.Year,
		Make:       input.Make,
		Model:      input.Model,
		Trim:       input.Trim,
		Engine:     input.Engine,
		Mileage:    input.Mileage,
	}
	return s.repo.Save(vehicle)
}
