package httpapi

import (
	"encoding/json"
	"errors"
	"net/http"

	"log/slog"

	"github.com/ezmobilemechanic/platform/internal/auth"
	"github.com/ezmobilemechanic/platform/internal/domain/users"
)

func registerAuthRoutes(mux *http.ServeMux, logger *slog.Logger, service users.Service) {
	mux.HandleFunc("/v1/auth/register", func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			w.WriteHeader(http.StatusMethodNotAllowed)
			return
		}
		var payload struct {
			Email    string `json:"email"`
			Name     string `json:"name"`
			Password string `json:"password"`
		}
		if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
			respondError(w, http.StatusBadRequest, "invalid JSON payload")
			return
		}

		user, err := service.Register(users.RegisterInput{
			Email:    payload.Email,
			Name:     payload.Name,
			Password: payload.Password,
		})
		if err != nil {
			if errors.Is(err, users.ErrEmailExists) {
				respondError(w, http.StatusConflict, "email already in use")
				return
			}
			respondError(w, http.StatusBadRequest, err.Error())
			return
		}

		token := auth.IssueFakeToken()

		respondJSON(w, http.StatusCreated, map[string]any{
			"user": map[string]any{
				"id":    user.ID,
				"email": user.Email,
				"name":  user.Name,
			},
			"token": map[string]any{
				"access_token": token.AccessToken,
				"token_type":   token.TokenType,
				"expires_at":   token.ExpiresAt,
			},
		})
	})

	mux.HandleFunc("/v1/auth/login", func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			w.WriteHeader(http.StatusMethodNotAllowed)
			return
		}
		var payload struct {
			Email    string `json:"email"`
			Password string `json:"password"`
		}
		if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
			respondError(w, http.StatusBadRequest, "invalid JSON payload")
			return
		}

		user, err := service.Authenticate(payload.Email, payload.Password)
		if err != nil {
			if errors.Is(err, users.ErrNotFound) || errors.Is(err, users.ErrInvalidPassword) {
				respondError(w, http.StatusUnauthorized, "invalid credentials")
				return
			}
			respondError(w, http.StatusBadRequest, err.Error())
			return
		}

		token := auth.IssueFakeToken()

		respondJSON(w, http.StatusOK, map[string]any{
			"message": "login successful",
			"user": map[string]any{
				"id":    user.ID,
				"email": user.Email,
				"name":  user.Name,
			},
			"token": map[string]any{
				"access_token": token.AccessToken,
				"token_type":   token.TokenType,
				"expires_at":   token.ExpiresAt,
			},
		})
	})
}
