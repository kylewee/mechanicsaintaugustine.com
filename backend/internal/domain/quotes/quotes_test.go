package quotes_test

import (
	"testing"

	"github.com/ezmobilemechanic/platform/internal/domain/quotes"
	"github.com/ezmobilemechanic/platform/internal/storage/memory"
)

func TestQuoteServiceCreateTotals(t *testing.T) {
	repo := memory.NewQuoteRepository()
	svc := quotes.NewService(repo)

	q, err := svc.Create(quotes.CreateInput{
		CustomerID: "cust-1",
		VehicleID:  "veh-1",
		LineItems: []quotes.CreateLineItem{
			{Description: "Brake Pads", Quantity: 1, UnitPrice: 15000, LaborHours: 1.5},
			{Description: "Rotor", Quantity: 2, UnitPrice: 10000, LaborHours: 2},
		},
	})
	if err != nil {
		t.Fatalf("create quote failed: %v", err)
	}

	expected := int64(15000 + 2*10000)
	if q.TotalAmount != expected {
		t.Fatalf("expected total %d, got %d", expected, q.TotalAmount)
	}
	if len(q.LineItems) != 2 {
		t.Fatalf("expected 2 line items")
	}
}

func TestQuoteServiceUpdateStatus(t *testing.T) {
	repo := memory.NewQuoteRepository()
	svc := quotes.NewService(repo)

	q, err := svc.Create(quotes.CreateInput{CustomerID: "cust", VehicleID: "veh"})
	if err != nil {
		t.Fatalf("create quote failed: %v", err)
	}

	updated, err := svc.UpdateStatus(q.ID, quotes.StatusAccepted)
	if err != nil {
		t.Fatalf("update status failed: %v", err)
	}
	if updated.Status != quotes.StatusAccepted {
		t.Fatalf("expected status %s, got %s", quotes.StatusAccepted, updated.Status)
	}
}

func TestQuoteServiceListByCustomer(t *testing.T) {
	repo := memory.NewQuoteRepository()
	svc := quotes.NewService(repo)

	const customerID = "cust"
	for i := 0; i < 3; i++ {
		if _, err := svc.Create(quotes.CreateInput{CustomerID: customerID}); err != nil {
			t.Fatalf("create failed: %v", err)
		}
	}

	list, err := svc.ListForCustomer(customerID, 0, 2)
	if err != nil {
		t.Fatalf("list failed: %v", err)
	}
	if len(list) != 2 {
		t.Fatalf("expected 2 quotes, got %d", len(list))
	}
}
