package httpapi

import (
	"encoding/json"
	"errors"
	"net/http"
	"strconv"
	"strings"

	"log/slog"

	"github.com/ezmobilemechanic/platform/internal/domain/customers"
)

func registerCustomerRoutes(mux *http.ServeMux, logger *slog.Logger, service customers.Service) {
	mux.HandleFunc("/v1/customers", func(w http.ResponseWriter, r *http.Request) {
		switch r.Method {
		case http.MethodGet:
			handleCustomerList(w, r, logger, service)
		case http.MethodPost:
			handleCustomerCreate(w, r, logger, service)
		default:
			w.WriteHeader(http.StatusMethodNotAllowed)
		}
	})

	mux.HandleFunc("/v1/customers/", func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodGet {
			w.WriteHeader(http.StatusMethodNotAllowed)
			return
		}

		id := strings.TrimPrefix(r.URL.Path, "/v1/customers/")
		if id == "" {
			respondError(w, http.StatusBadRequest, "missing customer id")
			return
		}

		customer, err := service.Get(id)
		if err != nil {
			switch {
			case errors.Is(err, customers.ErrNotImplemented):
				respondError(w, http.StatusNotImplemented, "get customer not yet implemented")
			case errors.Is(err, customers.ErrNotFound):
				respondError(w, http.StatusNotFound, "customer not found")
			default:
				logger.Error("get customer failed", "err", err)
				respondError(w, http.StatusInternalServerError, "internal error")
			}
			return
		}

		respondJSON(w, http.StatusOK, customer)
	})
}

func handleCustomerList(w http.ResponseWriter, r *http.Request, logger *slog.Logger, service customers.Service) {
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

	results, err := service.List(offset, limit)
	if err != nil {
		if errors.Is(err, customers.ErrNotImplemented) {
			respondError(w, http.StatusNotImplemented, "list customers not yet implemented")
			return
		}
		logger.Error("list customers failed", "err", err)
		respondError(w, http.StatusInternalServerError, "internal error")
		return
	}

	respondJSON(w, http.StatusOK, map[string]any{
		"data":  results,
		"count": len(results),
	})
}

func handleCustomerCreate(w http.ResponseWriter, r *http.Request, logger *slog.Logger, service customers.Service) {
	var input customers.CreateInput
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "invalid JSON payload")
		return
	}

	if strings.TrimSpace(input.FirstName) == "" && strings.TrimSpace(input.LastName) == "" {
		respondError(w, http.StatusBadRequest, "first_name or last_name required")
		return
	}
	if strings.TrimSpace(input.Email) == "" && strings.TrimSpace(input.Phone) == "" {
		respondError(w, http.StatusBadRequest, "email or phone required")
		return
	}

	customer, err := service.Create(customers.CreateInput{
		FirstName:    strings.TrimSpace(input.FirstName),
		LastName:     strings.TrimSpace(input.LastName),
		Email:        strings.TrimSpace(input.Email),
		Phone:        strings.TrimSpace(input.Phone),
		MarketingOpt: input.MarketingOpt,
	})
	if err != nil {
		if errors.Is(err, customers.ErrNotImplemented) {
			respondError(w, http.StatusNotImplemented, "create customer not yet implemented")
			return
		}
		logger.Error("create customer failed", "err", err)
		respondError(w, http.StatusInternalServerError, "internal error")
		return
	}

	respondJSON(w, http.StatusCreated, customer)
}

func respondJSON(w http.ResponseWriter, status int, payload any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	if err := json.NewEncoder(w).Encode(payload); err != nil {
		// If encoding fails there's not much we can do; log to stderr.
		slog.Default().Error("failed to encode response", "err", err)
	}
}

func respondError(w http.ResponseWriter, status int, message string) {
	respondJSON(w, status, map[string]string{"error": message})
}
