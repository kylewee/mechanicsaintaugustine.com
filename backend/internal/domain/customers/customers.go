package customers

import (
	"errors"
	"time"
)

// Domain-level errors for customers.
var (
	ErrNotImplemented = errors.New("customers repository: not implemented")
	ErrNotFound       = errors.New("customer not found")
)

// Customer represents a service customer in our domain.
type Customer struct {
	ID           string
	ExternalID   string
	FirstName    string
	LastName     string
	Email        string
	Phone        string
	CreatedAt    time.Time
	UpdatedAt    time.Time
	MarketingOpt bool
}

// Repository abstracts persistence for customers.
type Repository interface {
	FindByID(id string) (Customer, error)
	Save(customer Customer) (Customer, error)
	List(offset, limit int) ([]Customer, error)
}

// NullRepository stub implementation returning ErrNotImplemented.
type NullRepository struct{}

func (NullRepository) FindByID(id string) (Customer, error) {
	return Customer{}, ErrNotImplemented
}

func (NullRepository) Save(customer Customer) (Customer, error) {
	return Customer{}, ErrNotImplemented
}

func (NullRepository) List(offset, limit int) ([]Customer, error) {
	return nil, ErrNotImplemented
}

// Service exposes business operations over customers.
type Service interface {
	Get(id string) (Customer, error)
	Create(input CreateInput) (Customer, error)
	Update(id string, input UpdateInput) (Customer, error)
	List(offset, limit int) ([]Customer, error)
}

// CreateInput defines data required to create a customer.
type CreateInput struct {
	FirstName    string
	LastName     string
	Email        string
	Phone        string
	MarketingOpt bool
}

// UpdateInput defines data for updating a customer.
type UpdateInput struct {
	FirstName    *string
	LastName     *string
	Email        *string
	Phone        *string
	MarketingOpt *bool
}

// NewService builds a customer service with the given repository.
func NewService(repo Repository) Service {
	return &service{repo: repo}
}

type service struct {
	repo Repository
}

func (s *service) Get(id string) (Customer, error) {
	return s.repo.FindByID(id)
}

func (s *service) Create(input CreateInput) (Customer, error) {
	customer := Customer{
		FirstName:    input.FirstName,
		LastName:     input.LastName,
		Email:        input.Email,
		Phone:        input.Phone,
		MarketingOpt: input.MarketingOpt,
	}
	return s.repo.Save(customer)
}

func (s *service) Update(id string, input UpdateInput) (Customer, error) {
	customer, err := s.repo.FindByID(id)
	if err != nil {
		return Customer{}, err
	}

	if input.FirstName != nil {
		customer.FirstName = *input.FirstName
	}
	if input.LastName != nil {
		customer.LastName = *input.LastName
	}
	if input.Email != nil {
		customer.Email = *input.Email
	}
	if input.Phone != nil {
		customer.Phone = *input.Phone
	}
	if input.MarketingOpt != nil {
		customer.MarketingOpt = *input.MarketingOpt
	}

	return s.repo.Save(customer)
}

func (s *service) List(offset, limit int) ([]Customer, error) {
	return s.repo.List(offset, limit)
}
