package httpapi

import (
	"encoding/json"
	"net/http"
	"time"

	"log/slog"

	"github.com/ezmobilemechanic/platform/internal/domain"
)

// Register attaches API routes to the provided mux.
func Register(mux *http.ServeMux, logger *slog.Logger, domainServices domain.Container) {
	mux.HandleFunc("/v1/ping", func(w http.ResponseWriter, r *http.Request) {
		resp := map[string]any{
			"status":  "ok",
			"time":    time.Now().UTC().Format(time.RFC3339),
			"server":  "ezmobilemechanic-platform",
			"version": "v1",
		}

		w.Header().Set("Content-Type", "application/json")
		if err := json.NewEncoder(w).Encode(resp); err != nil {
			logger.Error("failed to write ping response", "err", err)
		}
	})

	registerCustomerRoutes(mux, logger, domainServices.Customers)
	registerVehicleRoutes(mux, logger, domainServices.Vehicles)
	registerQuoteRoutes(mux, logger, domainServices.Quotes)
	registerAuthRoutes(mux, logger, domainServices.Users)
	registerPublicRoutes(mux, logger)
}
