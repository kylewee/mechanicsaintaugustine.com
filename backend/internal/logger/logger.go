package logger

import (
	"io"
	"log/slog"
	"os"
)

// New returns a slog.Logger configured based on the application environment.
func New(env string) *slog.Logger {
	handler := slog.NewJSONHandler(defaultWriter(), &slog.HandlerOptions{
		Level: parseLevel(env),
	})
	return slog.New(handler)
}

func defaultWriter() io.Writer {
	return os.Stdout
}

func parseLevel(env string) slog.Level {
	switch env {
	case "production":
		return slog.LevelInfo
	case "staging":
		return slog.LevelInfo
	default:
		return slog.LevelDebug
	}
}
