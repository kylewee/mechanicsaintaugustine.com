package httpapi

import (
	"encoding/json"
	"errors"
	"net/http"
	"strconv"
	"strings"

	"log/slog"

	"github.com/ezmobilemechanic/platform/internal/domain/quotes"
)

func registerQuoteRoutes(mux *http.ServeMux, logger *slog.Logger, service quotes.Service) {
	mux.HandleFunc("/v1/quotes", func(w http.ResponseWriter, r *http.Request) {
		switch r.Method {
		case http.MethodPost:
			handleQuoteCreate(w, r, logger, service)
		default:
			w.WriteHeader(http.StatusMethodNotAllowed)
		}
	})

	mux.HandleFunc("/v1/quotes/", func(w http.ResponseWriter, r *http.Request) {
		switch r.Method {
		case http.MethodGet:
			handleQuoteGet(w, r, logger, service)
		case http.MethodPatch:
			handleQuoteUpdateStatus(w, r, logger, service)
		default:
			w.WriteHeader(http.StatusMethodNotAllowed)
		}
	})

	mux.HandleFunc("/v1/customers/", func(w http.ResponseWriter, r *http.Request) {
		if !strings.HasPrefix(r.URL.Path, "/v1/customers/") {
			w.WriteHeader(http.StatusNotFound)
			return
		}
		remainder := strings.TrimPrefix(r.URL.Path, "/v1/customers/")
		if !strings.HasSuffix(remainder, "/quotes") {
			return
		}
		customerID := strings.TrimSuffix(remainder, "/quotes")
		customerID = strings.TrimSuffix(customerID, "/")
		if customerID == "" {
			respondError(w, http.StatusBadRequest, "missing customer id")
			return
		}

		switch r.Method {
		case http.MethodGet:
			handleQuoteListByCustomer(w, r, logger, service, customerID)
		default:
			w.WriteHeader(http.StatusMethodNotAllowed)
		}
	})
}

func handleQuoteCreate(w http.ResponseWriter, r *http.Request, logger *slog.Logger, service quotes.Service) {
	var input quotes.CreateInput
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "invalid JSON payload")
		return
	}
	if strings.TrimSpace(input.CustomerID) == "" {
		respondError(w, http.StatusBadRequest, "customer_id is required")
		return
	}

	for idx := range input.LineItems {
		input.LineItems[idx].Description = strings.TrimSpace(input.LineItems[idx].Description)
		if input.LineItems[idx].Quantity <= 0 {
			input.LineItems[idx].Quantity = 1
		}
	}

	quote, err := service.Create(quotes.CreateInput{
		CustomerID: strings.TrimSpace(input.CustomerID),
		VehicleID:  strings.TrimSpace(input.VehicleID),
		LineItems:  input.LineItems,
	})
	if err != nil {
		if errors.Is(err, quotes.ErrNotImplemented) {
			respondError(w, http.StatusNotImplemented, "create quote not yet implemented")
			return
		}
		logger.Error("create quote failed", "err", err)
		respondError(w, http.StatusInternalServerError, "internal error")
		return
	}

	respondJSON(w, http.StatusCreated, quote)
}

func handleQuoteGet(w http.ResponseWriter, r *http.Request, logger *slog.Logger, service quotes.Service) {
	id := strings.TrimPrefix(r.URL.Path, "/v1/quotes/")
	if id == "" {
		respondError(w, http.StatusBadRequest, "missing quote id")
		return
	}

	quote, err := service.Get(id)
	if err != nil {
		switch {
		case errors.Is(err, quotes.ErrNotImplemented):
			respondError(w, http.StatusNotImplemented, "get quote not yet implemented")
		case errors.Is(err, quotes.ErrNotFound):
			respondError(w, http.StatusNotFound, "quote not found")
		default:
			logger.Error("get quote failed", "err", err)
			respondError(w, http.StatusInternalServerError, "internal error")
		}
		return
	}

	respondJSON(w, http.StatusOK, quote)
}

func handleQuoteUpdateStatus(w http.ResponseWriter, r *http.Request, logger *slog.Logger, service quotes.Service) {
	id := strings.TrimPrefix(r.URL.Path, "/v1/quotes/")
	if id == "" {
		respondError(w, http.StatusBadRequest, "missing quote id")
		return
	}

	var payload struct {
		Status string `json:"status"`
	}
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		respondError(w, http.StatusBadRequest, "invalid JSON payload")
		return
	}
	status := quotes.Status(strings.TrimSpace(payload.Status))
	if status == "" {
		respondError(w, http.StatusBadRequest, "status is required")
		return
	}

	quote, err := service.UpdateStatus(id, status)
	if err != nil {
		switch {
		case errors.Is(err, quotes.ErrNotImplemented):
			respondError(w, http.StatusNotImplemented, "update quote not yet implemented")
		case errors.Is(err, quotes.ErrNotFound):
			respondError(w, http.StatusNotFound, "quote not found")
		default:
			logger.Error("update quote status failed", "err", err)
			respondError(w, http.StatusInternalServerError, "internal error")
		}
		return
	}

	respondJSON(w, http.StatusOK, quote)
}

func handleQuoteListByCustomer(w http.ResponseWriter, r *http.Request, logger *slog.Logger, service quotes.Service, customerID string) {
	query := r.URL.Query()
	offset, limit := 0, 50
	if v := query.Get("offset"); v != "" {
		parsed, err := strconv.Atoi(v)
		if err != nil || parsed < 0 {
			respondError(w, http.StatusBadRequest, "invalid offset parameter")
			return
		}
		offset = parsed
	}
	if v := query.Get("limit"); v != "" {
		parsed, err := strconv.Atoi(v)
		if err != nil || parsed < 0 {
			respondError(w, http.StatusBadRequest, "invalid limit parameter")
			return
		}
		limit = parsed
	}

	quotesList, err := service.ListForCustomer(customerID, offset, limit)
	if err != nil {
		if errors.Is(err, quotes.ErrNotImplemented) {
			respondError(w, http.StatusNotImplemented, "list quotes not yet implemented")
			return
		}
		logger.Error("list quotes failed", "err", err, "customer_id", customerID)
		respondError(w, http.StatusInternalServerError, "internal error")
		return
	}

	respondJSON(w, http.StatusOK, map[string]any{
		"data":  quotesList,
		"count": len(quotesList),
	})
}
