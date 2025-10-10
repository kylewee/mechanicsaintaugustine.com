package quotes

import (
	"errors"
	"time"
)

var (
	ErrNotImplemented = errors.New("quotes repository: not implemented")
	ErrNotFound       = errors.New("quote not found")
)

// Quote represents a customer quote with line items.
type Quote struct {
	ID          string
	CustomerID  string
	VehicleID   string
	Status      Status
	TotalAmount int64 // store in cents
	CreatedAt   time.Time
	UpdatedAt   time.Time
	LineItems   []LineItem
}

// LineItem represents an item within a quote.
type LineItem struct {
	ID          string
	QuoteID     string
	Description string
	Quantity    int
	UnitPrice   int64
	LaborHours  float64
	SortOrder   int
}

// Status represents quote status.
type Status string

const (
	StatusDraft     Status = "draft"
	StatusSent      Status = "sent"
	StatusAccepted  Status = "accepted"
	StatusDeclined  Status = "declined"
	StatusConverted Status = "converted"
)

// Repository abstracts quote persistence.
type Repository interface {
	FindByID(id string) (Quote, error)
	Save(quote Quote) (Quote, error)
	ListByCustomer(customerID string, offset, limit int) ([]Quote, error)
}

// NullRepository returns ErrNotImplemented for all operations.
type NullRepository struct{}

func (NullRepository) FindByID(id string) (Quote, error) {
	return Quote{}, ErrNotImplemented
}

func (NullRepository) Save(quote Quote) (Quote, error) {
	return Quote{}, ErrNotImplemented
}

func (NullRepository) ListByCustomer(customerID string, offset, limit int) ([]Quote, error) {
	return nil, ErrNotImplemented
}

// Service provides business logic around quotes.
type Service interface {
	Get(id string) (Quote, error)
	Create(input CreateInput) (Quote, error)
	UpdateStatus(id string, status Status) (Quote, error)
	ListForCustomer(customerID string, offset, limit int) ([]Quote, error)
}

// CreateInput is used to create new quotes.
type CreateInput struct {
	CustomerID string
	VehicleID  string
	LineItems  []CreateLineItem
}

// CreateLineItem describes line items when creating a quote.
type CreateLineItem struct {
	Description string
	Quantity    int
	UnitPrice   int64
	LaborHours  float64
}

// NewService builds a quote service.
func NewService(repo Repository) Service {
	return &service{repo: repo}
}

type service struct {
	repo Repository
}

func (s *service) Get(id string) (Quote, error) {
	return s.repo.FindByID(id)
}

func (s *service) Create(input CreateInput) (Quote, error) {
	quote := Quote{
		CustomerID: input.CustomerID,
		VehicleID:  input.VehicleID,
		Status:     StatusDraft,
	}

	for idx, item := range input.LineItems {
		quote.LineItems = append(quote.LineItems, LineItem{
			Description: item.Description,
			Quantity:    item.Quantity,
			UnitPrice:   item.UnitPrice,
			LaborHours:  item.LaborHours,
			SortOrder:   idx,
		})
		quote.TotalAmount += int64(item.Quantity) * item.UnitPrice
	}

	return s.repo.Save(quote)
}

func (s *service) UpdateStatus(id string, status Status) (Quote, error) {
	quote, err := s.repo.FindByID(id)
	if err != nil {
		return Quote{}, err
	}
	quote.Status = status
	return s.repo.Save(quote)
}

func (s *service) ListForCustomer(customerID string, offset, limit int) ([]Quote, error) {
	return s.repo.ListByCustomer(customerID, offset, limit)
}
