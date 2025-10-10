package postgres

import (
	"database/sql"
	"errors"
	"fmt"
	"time"

	"github.com/ezmobilemechanic/platform/internal/domain/quotes"
)

// QuoteRepository persists quotes and their line items.
type QuoteRepository struct {
	db *sql.DB
}

// NewQuoteRepository constructs a repository using a pooled DB handle.
func NewQuoteRepository(db *sql.DB) *QuoteRepository {
	return &QuoteRepository{db: db}
}

// FindByID retrieves a quote and its line items.
func (r *QuoteRepository) FindByID(id string) (quotes.Quote, error) {
	const query = `
        SELECT id, customer_id, vehicle_id, status, total_amount, created_at, updated_at
          FROM quotes
         WHERE id = $1
    `

	var q quotes.Quote
	err := r.db.QueryRow(query, id).Scan(
		&q.ID,
		&q.CustomerID,
		&q.VehicleID,
		&q.Status,
		&q.TotalAmount,
		&q.CreatedAt,
		&q.UpdatedAt,
	)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return quotes.Quote{}, quotes.ErrNotFound
		}
		return quotes.Quote{}, fmt.Errorf("find quote: %w", err)
	}

	items, err := r.fetchLineItems(q.ID)
	if err != nil {
		return quotes.Quote{}, err
	}
	q.LineItems = items

	return q, nil
}

func (r *QuoteRepository) fetchLineItems(quoteID string) ([]quotes.LineItem, error) {
	const query = `
        SELECT id, description, quantity, unit_price, labor_hours, sort_order
          FROM quote_line_items
         WHERE quote_id = $1
         ORDER BY sort_order
    `

	rows, err := r.db.Query(query, quoteID)
	if err != nil {
		return nil, fmt.Errorf("list quote line items: %w", err)
	}
	defer rows.Close()

	var items []quotes.LineItem
	for rows.Next() {
		var item quotes.LineItem
		item.QuoteID = quoteID
		if err := rows.Scan(
			&item.ID,
			&item.Description,
			&item.Quantity,
			&item.UnitPrice,
			&item.LaborHours,
			&item.SortOrder,
		); err != nil {
			return nil, fmt.Errorf("scan quote line item: %w", err)
		}
		items = append(items, item)
	}
	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("line items rows err: %w", err)
	}

	return items, nil
}

// Save inserts or updates a quote with its line items.
func (r *QuoteRepository) Save(q quotes.Quote) (quotes.Quote, error) {
	tx, err := r.db.Begin()
	if err != nil {
		return quotes.Quote{}, fmt.Errorf("begin tx: %w", err)
	}

	now := time.Now().UTC()
	if q.ID == "" {
		const insert = `
            INSERT INTO quotes (customer_id, vehicle_id, status, total_amount, created_at, updated_at)
            VALUES ($1,$2,$3,$4,$5,$6)
            RETURNING id
        `
		if err := tx.QueryRow(insert,
			q.CustomerID,
			q.VehicleID,
			q.Status,
			q.TotalAmount,
			now,
			now,
		).Scan(&q.ID); err != nil {
			tx.Rollback()
			return quotes.Quote{}, fmt.Errorf("insert quote: %w", err)
		}
		q.CreatedAt = now
		q.UpdatedAt = now
	} else {
		const update = `
            UPDATE quotes
               SET customer_id = $2,
                   vehicle_id = $3,
                   status = $4,
                   total_amount = $5,
                   updated_at = $6
             WHERE id = $1
            RETURNING created_at
        `
		var created time.Time
		if err := tx.QueryRow(update,
			q.ID,
			q.CustomerID,
			q.VehicleID,
			q.Status,
			q.TotalAmount,
			now,
		).Scan(&created); err != nil {
			tx.Rollback()
			if errors.Is(err, sql.ErrNoRows) {
				return quotes.Quote{}, quotes.ErrNotFound
			}
			return quotes.Quote{}, fmt.Errorf("update quote: %w", err)
		}
		q.CreatedAt = created
		q.UpdatedAt = now

		if err := deleteLineItems(tx, q.ID); err != nil {
			tx.Rollback()
			return quotes.Quote{}, err
		}
	}

	if err := insertLineItems(tx, q); err != nil {
		tx.Rollback()
		return quotes.Quote{}, err
	}

	if err := tx.Commit(); err != nil {
		return quotes.Quote{}, fmt.Errorf("commit quote save: %w", err)
	}

	return q, nil
}

func deleteLineItems(tx *sql.Tx, quoteID string) error {
	if _, err := tx.Exec(`DELETE FROM quote_line_items WHERE quote_id = $1`, quoteID); err != nil {
		return fmt.Errorf("delete quote line items: %w", err)
	}
	return nil
}

func insertLineItems(tx *sql.Tx, q quotes.Quote) error {
	const insert = `
        INSERT INTO quote_line_items (id, quote_id, description, quantity, unit_price, labor_hours, sort_order)
        VALUES ($1,$2,$3,$4,$5,$6,$7)
    `

	for idx, item := range q.LineItems {
		id := item.ID
		if id == "" {
			if err := tx.QueryRow(`SELECT gen_random_uuid()`).Scan(&id); err != nil {
				return fmt.Errorf("generate line item id: %w", err)
			}
		}

		if _, err := tx.Exec(insert,
			id,
			q.ID,
			item.Description,
			item.Quantity,
			item.UnitPrice,
			item.LaborHours,
			idx,
		); err != nil {
			return fmt.Errorf("insert quote line item: %w", err)
		}
	}

	return nil
}

// ListByCustomer returns paginated quotes for a customer.
func (r *QuoteRepository) ListByCustomer(customerID string, offset, limit int) ([]quotes.Quote, error) {
	const query = `
        SELECT id, customer_id, vehicle_id, status, total_amount, created_at, updated_at
          FROM quotes
         WHERE customer_id = $1
         ORDER BY created_at DESC
         OFFSET $2
         LIMIT $3
    `

	rows, err := r.db.Query(query, customerID, offset, limit)
	if err != nil {
		return nil, fmt.Errorf("list quotes: %w", err)
	}
	defer rows.Close()

	var result []quotes.Quote
	for rows.Next() {
		var q quotes.Quote
		if err := rows.Scan(
			&q.ID,
			&q.CustomerID,
			&q.VehicleID,
			&q.Status,
			&q.TotalAmount,
			&q.CreatedAt,
			&q.UpdatedAt,
		); err != nil {
			return nil, fmt.Errorf("scan quote: %w", err)
		}
		items, err := r.fetchLineItems(q.ID)
		if err != nil {
			return nil, err
		}
		q.LineItems = items
		result = append(result, q)
	}
	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("rows err: %w", err)
	}

	return result, nil
}
