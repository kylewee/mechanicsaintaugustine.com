package httpapi

import (
	"encoding/json"
	"errors"
	"net/http"
	"strings"

	"log/slog"

	"github.com/ezmobilemechanic/platform/internal/domain/vehicles"
)

func registerVehicleRoutes(mux *http.ServeMux, logger *slog.Logger, service vehicles.Service) {
	mux.HandleFunc("/v1/vehicles", func(w http.ResponseWriter, r *http.Request) {
		switch r.Method {
		case http.MethodPost:
			handleVehicleCreate(w, r, logger, service)
		default:
			w.WriteHeader(http.StatusMethodNotAllowed)
		}
	})

	mux.HandleFunc("/v1/vehicles/", func(w http.ResponseWriter, r *http.Request) {
		switch r.Method {
		case http.MethodGet:
			handleVehicleGet(w, r, logger, service)
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
		if !strings.HasSuffix(remainder, "/vehicles") {
			w.WriteHeader(http.StatusNotFound)
			return
		}

		customerID := strings.TrimSuffix(remainder, "/vehicles")
		customerID = strings.TrimSuffix(customerID, "/")
		if customerID == "" {
			respondError(w, http.StatusBadRequest, "missing customer id")
			return
		}

		switch r.Method {
		case http.MethodGet:
			handleVehicleListByCustomer(w, r, logger, service, customerID)
		default:
			w.WriteHeader(http.StatusMethodNotAllowed)
		}
	})
}

func handleVehicleCreate(w http.ResponseWriter, r *http.Request, logger *slog.Logger, service vehicles.Service) {
	var input vehicles.CreateInput
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "invalid JSON payload")
		return
	}

	if strings.TrimSpace(input.CustomerID) == "" {
		respondError(w, http.StatusBadRequest, "customer_id is required")
		return
	}

	vehicle, err := service.Create(vehicles.CreateInput{
		CustomerID: strings.TrimSpace(input.CustomerID),
		VIN:        strings.TrimSpace(input.VIN),
		Year:       input.Year,
		Make:       strings.TrimSpace(input.Make),
		Model:      strings.TrimSpace(input.Model),
		Trim:       strings.TrimSpace(input.Trim),
		Engine:     strings.TrimSpace(input.Engine),
		Mileage:    input.Mileage,
	})
	if err != nil {
		if errors.Is(err, vehicles.ErrNotImplemented) {
			respondError(w, http.StatusNotImplemented, "create vehicle not yet implemented")
			return
		}
		logger.Error("create vehicle failed", "err", err)
		respondError(w, http.StatusInternalServerError, "internal error")
		return
	}

	respondJSON(w, http.StatusCreated, vehicle)
}

func handleVehicleGet(w http.ResponseWriter, r *http.Request, logger *slog.Logger, service vehicles.Service) {
	id := strings.TrimPrefix(r.URL.Path, "/v1/vehicles/")
	if id == "" {
		respondError(w, http.StatusBadRequest, "missing vehicle id")
		return
	}

	vehicle, err := service.Get(id)
	if err != nil {
		switch {
		case errors.Is(err, vehicles.ErrNotImplemented):
			respondError(w, http.StatusNotImplemented, "get vehicle not yet implemented")
		case errors.Is(err, vehicles.ErrNotFound):
			respondError(w, http.StatusNotFound, "vehicle not found")
		default:
			logger.Error("get vehicle failed", "err", err)
			respondError(w, http.StatusInternalServerError, "internal error")
		}
		return
	}

	respondJSON(w, http.StatusOK, vehicle)
}

func handleVehicleListByCustomer(w http.ResponseWriter, r *http.Request, logger *slog.Logger, service vehicles.Service, customerID string) {
	list, err := service.ListForCustomer(customerID)
	if err != nil {
		if errors.Is(err, vehicles.ErrNotImplemented) {
			respondError(w, http.StatusNotImplemented, "list vehicles not yet implemented")
			return
		}
		logger.Error("list vehicles failed", "err", err, "customer_id", customerID)
		respondError(w, http.StatusInternalServerError, "internal error")
		return
	}

	respondJSON(w, http.StatusOK, map[string]any{
		"data":  list,
		"count": len(list),
	})
}
