package memory

import (
	"sort"
	"sync"
	"time"

	"github.com/ezmobilemechanic/platform/internal/domain/customers"
)

// CustomerRepository is an in-memory implementation of customers.Repository.
type CustomerRepository struct {
	mu        sync.RWMutex
	customers map[string]customers.Customer
}

// NewCustomerRepository returns an initialized in-memory repository.
func NewCustomerRepository() *CustomerRepository {
	return &CustomerRepository{
		customers: make(map[string]customers.Customer),
	}
}

// FindByID returns a customer by identifier.
func (r *CustomerRepository) FindByID(id string) (customers.Customer, error) {
	r.mu.RLock()
	defer r.mu.RUnlock()

	c, ok := r.customers[id]
	if !ok {
		return customers.Customer{}, customers.ErrNotFound
	}
	return c, nil
}

// Save inserts or updates a customer record.
func (r *CustomerRepository) Save(customer customers.Customer) (customers.Customer, error) {
	r.mu.Lock()
	defer r.mu.Unlock()

	now := time.Now().UTC()
	if customer.ID == "" {
		customer.ID = newID()
		customer.CreatedAt = now
	} else {
		existing, ok := r.customers[customer.ID]
		if ok {
			if customer.CreatedAt.IsZero() {
				customer.CreatedAt = existing.CreatedAt
			}
		} else {
			customer.CreatedAt = now
		}
	}
	customer.UpdatedAt = now
	r.customers[customer.ID] = customer
	return customer, nil
}

// List returns customers with simple offset/limit pagination.
func (r *CustomerRepository) List(offset, limit int) ([]customers.Customer, error) {
	r.mu.RLock()
	defer r.mu.RUnlock()

	list := make([]customers.Customer, 0, len(r.customers))
	for _, c := range r.customers {
		list = append(list, c)
	}

	sort.Slice(list, func(i, j int) bool {
		return list[i].CreatedAt.Before(list[j].CreatedAt)
	})

	if offset > len(list) {
		return []customers.Customer{}, nil
	}
	end := len(list)
	if limit > 0 && offset+limit < end {
		end = offset + limit
	}
	return list[offset:end], nil
}
