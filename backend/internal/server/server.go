package server

import (
	"context"
	"fmt"
	"net/http"
	"time"

	"log/slog"

	"github.com/ezmobilemechanic/platform/internal/config"
)

// Server wraps the HTTP server and related dependencies.
type Server struct {
	cfg    config.Config
	logger *slog.Logger
	server *http.Server
	mux    *http.ServeMux
}

// New constructs a server with base routes and middleware wiring.
func New(cfg config.Config, logger *slog.Logger) *Server {
	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"status":"ok"}`))
	})

	srv := &http.Server{
		Addr:              fmt.Sprintf(":%d", cfg.HTTPPort),
		Handler:           loggingMiddleware(logger, mux),
		ReadHeaderTimeout: cfg.ReadHeaderTimeout,
	}

	return &Server{
		cfg:    cfg,
		logger: logger,
		server: srv,
		mux:    mux,
	}
}

// Run starts the HTTP server and blocks until it exits or errors.
func (s *Server) Run() error {
	s.logger.Info("api server listening", "addr", s.server.Addr, "env", s.cfg.Env)
	if err := s.server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
		return err
	}
	return nil
}

// Shutdown gracefully stops the server within the provided context timeout.
func (s *Server) Shutdown(ctx context.Context) error {
	s.logger.Info("shutting down server")
	if err := s.server.Shutdown(ctx); err != nil {
		return err
	}
	s.logger.Info("server stopped")
	return nil
}

func loggingMiddleware(logger *slog.Logger, next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		next.ServeHTTP(w, r)
		logger.Info("request", "method", r.Method, "path", r.URL.Path, "duration", time.Since(start))
	})
}

// Mux exposes the underlying mux for route registration by other packages.
func (s *Server) Mux() *http.ServeMux {
	return s.mux
}
