package httpapi

import (
	"encoding/json"
	"net/http"
	"strings"

	"log/slog"
)

// registerPublicRoutes exposes unauthenticated endpoints for quote intake.
func registerPublicRoutes(mux *http.ServeMux, logger *slog.Logger) {
	mux.HandleFunc("/public/quote-intake", func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			w.WriteHeader(http.StatusMethodNotAllowed)
			return
		}

		var payload QuoteIntakeRequest
		if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
			respondError(w, http.StatusBadRequest, "invalid JSON payload")
			return
		}

		payload.Normalize()
		if err := payload.Validate(); err != nil {
			respondError(w, http.StatusBadRequest, err.Error())
			return
		}

		logger.Info("quote_intake_received",
			"name", payload.Name,
			"phone", payload.Phone,
			"vehicle", payload.Vehicle,
			"source", payload.Source,
		)

		respondJSON(w, http.StatusAccepted, map[string]any{
			"status":  "accepted",
			"message": "quote intake received",
		})
	})
}

// QuoteIntakeRequest mirrors the website quote submission payload.
type QuoteIntakeRequest struct {
	Name          string            `json:"name"`
	Phone         string            `json:"phone"`
	Email         string            `json:"email,omitempty"`
	Vehicle       map[string]string `json:"vehicle,omitempty"`
	Location      map[string]string `json:"location,omitempty"`
	Repair        string            `json:"repair,omitempty"`
	Mileage       *int              `json:"mileage,omitempty"`
	LaborHours    *float64          `json:"labor_hours,omitempty"`
	VIN           string            `json:"vin,omitempty"`
	ContactMethod string            `json:"contact_method,omitempty"`
	PreferredSlot any               `json:"preferred_slot,omitempty"`
	Concern       string            `json:"concern,omitempty"`
	Estimate      map[string]any    `json:"estimate,omitempty"`
	Extra         map[string]any    `json:"extra,omitempty"`
	Source        string            `json:"source,omitempty"`
}

// Normalize trims whitespace and ensures nested maps exist.
func (q *QuoteIntakeRequest) Normalize() {
	q.Name = strings.TrimSpace(q.Name)
	q.Phone = strings.TrimSpace(q.Phone)
	q.Email = strings.TrimSpace(q.Email)
	q.Repair = strings.TrimSpace(q.Repair)
	q.VIN = strings.TrimSpace(q.VIN)
	q.ContactMethod = strings.TrimSpace(q.ContactMethod)
	q.Concern = strings.TrimSpace(q.Concern)
	q.Source = strings.TrimSpace(q.Source)

	if q.Vehicle == nil {
		q.Vehicle = make(map[string]string)
	}
	if q.Location == nil {
		q.Location = make(map[string]string)
	}
	if q.Extra == nil {
		q.Extra = make(map[string]any)
	}
}

// Validate ensures required fields are present.
func (q *QuoteIntakeRequest) Validate() error {
	if q.Name == "" {
		return errRequiredField("name")
	}
	if q.Phone == "" {
		return errRequiredField("phone")
	}
	return nil
}

func errRequiredField(field string) error {
	return &validationError{field: field}
}

type validationError struct {
	field string
}

func (v *validationError) Error() string {
	return v.field + " is required"
}
