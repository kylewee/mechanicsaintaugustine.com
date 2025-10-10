package domain

import (
	"github.com/ezmobilemechanic/platform/internal/domain/customers"
	"github.com/ezmobilemechanic/platform/internal/domain/quotes"
	"github.com/ezmobilemechanic/platform/internal/domain/users"
	"github.com/ezmobilemechanic/platform/internal/domain/vehicles"
)

// Container wires domain services together. In the future this will manage
// repository implementations backed by the database and Shopmonkey datamodel.
type Container struct {
	Customers customers.Service
	Vehicles  vehicles.Service
	Quotes    quotes.Service
	Users     users.Service
}

// Options configures the domain container.
type Options struct {
	CustomerRepo customers.Repository
	VehicleRepo  vehicles.Repository
	QuoteRepo    quotes.Repository
	UserRepo     users.Repository
}

// New constructs a domain container with provided repositories.
func New(opts Options) Container {
	customerRepo := opts.CustomerRepo
	if customerRepo == nil {
		customerRepo = customers.NullRepository{}
	}

	vehicleRepo := opts.VehicleRepo
	if vehicleRepo == nil {
		vehicleRepo = vehicles.NullRepository{}
	}

	quoteRepo := opts.QuoteRepo
	if quoteRepo == nil {
		quoteRepo = quotes.NullRepository{}
	}

	userRepo := opts.UserRepo
	if userRepo == nil {
		userRepo = users.NullRepository{}
	}

	return Container{
		Customers: customers.NewService(customerRepo),
		Vehicles:  vehicles.NewService(vehicleRepo),
		Quotes:    quotes.NewService(quoteRepo),
		Users:     users.NewService(userRepo),
	}
}
