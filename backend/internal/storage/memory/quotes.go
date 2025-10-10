package memory

import (
	"sort"
	"sync"
	"time"

	"github.com/ezmobilemechanic/platform/internal/domain/quotes"
)

// QuoteRepository is an in-memory implementation of quotes.Repository.
type QuoteRepository struct {
	mu     sync.RWMutex
	quotes map[string]quotes.Quote
}

// NewQuoteRepository creates an in-memory quote repo.
func NewQuoteRepository() *QuoteRepository {
	return &QuoteRepository{
		quotes: make(map[string]quotes.Quote),
	}
}

func (r *QuoteRepository) FindByID(id string) (quotes.Quote, error) {
	r.mu.RLock()
	defer r.mu.RUnlock()

	q, ok := r.quotes[id]
	if !ok {
		return quotes.Quote{}, quotes.ErrNotFound
	}
	return q, nil
}

func (r *QuoteRepository) Save(quote quotes.Quote) (quotes.Quote, error) {
	r.mu.Lock()
	defer r.mu.Unlock()

	now := time.Now().UTC()
	if quote.ID == "" {
		quote.ID = newID()
		quote.CreatedAt = now
	} else {
		existing, ok := r.quotes[quote.ID]
		if ok && quote.CreatedAt.IsZero() {
			quote.CreatedAt = existing.CreatedAt
		}
	}
	quote.UpdatedAt = now

	// ensure line item IDs and quote ID assignment
	for idx := range quote.LineItems {
		if quote.LineItems[idx].ID == "" {
			quote.LineItems[idx].ID = newID()
		}
		quote.LineItems[idx].QuoteID = quote.ID
		quote.LineItems[idx].SortOrder = idx
	}

	r.quotes[quote.ID] = quote
	return quote, nil
}

func (r *QuoteRepository) ListByCustomer(customerID string, offset, limit int) ([]quotes.Quote, error) {
	r.mu.RLock()
	defer r.mu.RUnlock()

	var list []quotes.Quote
	for _, q := range r.quotes {
		if q.CustomerID == customerID {
			list = append(list, q)
		}
	}

	sort.Slice(list, func(i, j int) bool {
		return list[i].CreatedAt.Before(list[j].CreatedAt)
	})

	if offset > len(list) {
		return []quotes.Quote{}, nil
	}
	end := len(list)
	if limit > 0 && offset+limit < end {
		end = offset + limit
	}
	return list[offset:end], nil
}
