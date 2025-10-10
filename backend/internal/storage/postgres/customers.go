package postgres

import (
	"database/sql"
	"errors"
	"fmt"
	"time"

	"github.com/ezmobilemechanic/platform/internal/domain/customers"
)

// CustomerRepository persists customers using a *sql.DB handle.
type CustomerRepository struct {
	db *sql.DB
}

// NewCustomerRepository returns a repository backed by a pooled DB connection.
func NewCustomerRepository(db *sql.DB) *CustomerRepository {
	return &CustomerRepository{db: db}
}

// FindByID fetches a customer by primary key.
func (r *CustomerRepository) FindByID(id string) (customers.Customer, error) {
	const query = `
        SELECT id, external_id, first_name, last_name, email, phone, marketing_opt,
               created_at, updated_at
          FROM customers
         WHERE id = $1
    `

	var c customers.Customer
	err := r.db.QueryRow(query, id).Scan(
		&c.ID,
		&c.ExternalID,
		&c.FirstName,
		&c.LastName,
		&c.Email,
		&c.Phone,
		&c.MarketingOpt,
		&c.CreatedAt,
		&c.UpdatedAt,
	)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return customers.Customer{}, customers.ErrNotFound
		}
		return customers.Customer{}, fmt.Errorf("find customer: %w", err)
	}

	return c, nil
}

// Save inserts or updates a customer record.
func (r *CustomerRepository) Save(customer customers.Customer) (customers.Customer, error) {
	now := time.Now().UTC()

	if customer.ID == "" {
		const insert = `
            INSERT INTO customers (external_id, first_name, last_name, email, phone, marketing_opt, created_at, updated_at)
            VALUES ($1,$2,$3,$4,$5,$6,$7,$8)
            RETURNING id
        `
		if err := r.db.QueryRow(insert,
			customer.ExternalID,
			customer.FirstName,
			customer.LastName,
			customer.Email,
			customer.Phone,
			customer.MarketingOpt,
			now,
			now,
		).Scan(&customer.ID); err != nil {
			return customers.Customer{}, fmt.Errorf("insert customer: %w", err)
		}
		customer.CreatedAt = now
		customer.UpdatedAt = now
		return customer, nil
	}

	const update = `
        UPDATE customers
           SET external_id = $2,
               first_name = $3,
               last_name = $4,
               email = $5,
               phone = $6,
               marketing_opt = $7,
               updated_at = $8
         WHERE id = $1
        RETURNING created_at
    `

	var created time.Time
	err := r.db.QueryRow(update,
		customer.ID,
		customer.ExternalID,
		customer.FirstName,
		customer.LastName,
		customer.Email,
		customer.Phone,
		customer.MarketingOpt,
		now,
	).Scan(&created)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return customers.Customer{}, customers.ErrNotFound
		}
		return customers.Customer{}, fmt.Errorf("update customer: %w", err)
	}

	customer.CreatedAt = created
	customer.UpdatedAt = now
	return customer, nil
}

// List returns customers ordered by creation date.
func (r *CustomerRepository) List(offset, limit int) ([]customers.Customer, error) {
	const query = `
        SELECT id, external_id, first_name, last_name, email, phone, marketing_opt,
               created_at, updated_at
          FROM customers
         ORDER BY created_at
         OFFSET $1
         LIMIT $2
    `

	rows, err := r.db.Query(query, offset, limit)
	if err != nil {
		return nil, fmt.Errorf("list customers: %w", err)
	}
	defer rows.Close()

	var result []customers.Customer
	for rows.Next() {
		var c customers.Customer
		if err := rows.Scan(
			&c.ID,
			&c.ExternalID,
			&c.FirstName,
			&c.LastName,
			&c.Email,
			&c.Phone,
			&c.MarketingOpt,
			&c.CreatedAt,
			&c.UpdatedAt,
		); err != nil {
			return nil, fmt.Errorf("scan customer: %w", err)
		}
		result = append(result, c)
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("rows error: %w", err)
	}

	return result, nil
}
