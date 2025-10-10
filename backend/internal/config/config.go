package config

import (
	"fmt"
	"os"
	"strconv"
	"time"
)

// Config holds application configuration loaded from environment variables.
type Config struct {
	Env               string
	HTTPPort          int
	ShutdownTimeout   time.Duration
	ReadHeaderTimeout time.Duration

	DataBackend       string

	DatabaseDriver    string
	DatabaseURL       string
	DBMaxOpenConns    int
	DBMaxIdleConns    int
	DBConnMaxLifetime time.Duration
	DBConnMaxIdleTime time.Duration

	RedisURL string

	JWTSecret       string
	JWTExpiry       time.Duration
	RefreshTokenTTL time.Duration
}

const (
	defaultEnv               = "development"
	defaultHTTPPort          = 8080
	defaultShutdownTimeout   = 10 * time.Second
	defaultReadHeaderTimeout = 5 * time.Second

	defaultDataBackend       = "memory"

	defaultDatabaseDriver    = "postgres"
	defaultDBMaxOpenConns    = 10
	defaultDBMaxIdleConns    = 5
	defaultDBConnMaxLifetime = time.Hour
	defaultDBConnMaxIdleTime = 30 * time.Minute

	defaultJWTExpiry       = 24 * time.Hour
	defaultRefreshTokenTTL = 30 * 24 * time.Hour
)

// Load reads configuration values from the environment, applying defaults where necessary.
func Load() (Config, error) {
	cfg := Config{
		Env:               getEnv("APP_ENV", defaultEnv),
		HTTPPort:          getInt("HTTP_PORT", defaultHTTPPort),
		ShutdownTimeout:   getDuration("SHUTDOWN_TIMEOUT", defaultShutdownTimeout),
		ReadHeaderTimeout: getDuration("READ_HEADER_TIMEOUT", defaultReadHeaderTimeout),

		DataBackend:       getEnv("DATA_BACKEND", defaultDataBackend),

		DatabaseDriver:    getEnv("DATABASE_DRIVER", defaultDatabaseDriver),
		DatabaseURL:       os.Getenv("DATABASE_URL"),
		DBMaxOpenConns:    getInt("DB_MAX_OPEN_CONNS", defaultDBMaxOpenConns),
		DBMaxIdleConns:    getInt("DB_MAX_IDLE_CONNS", defaultDBMaxIdleConns),
		DBConnMaxLifetime: getDuration("DB_CONN_MAX_LIFETIME", defaultDBConnMaxLifetime),
		DBConnMaxIdleTime: getDuration("DB_CONN_MAX_IDLE_TIME", defaultDBConnMaxIdleTime),

		RedisURL: os.Getenv("REDIS_URL"),

		JWTSecret:       os.Getenv("JWT_SECRET"),
		JWTExpiry:       getDuration("JWT_EXPIRY", defaultJWTExpiry),
		RefreshTokenTTL: getDuration("REFRESH_TOKEN_TTL", defaultRefreshTokenTTL),
	}

	if cfg.JWTSecret == "" {
		return Config{}, fmt.Errorf("JWT_SECRET is required")
	}

	switch cfg.DataBackend {
	case "memory":
		// no-op
	case "postgres":
		if cfg.DatabaseURL == "" {
			return Config{}, fmt.Errorf("DATABASE_URL is required when DATA_BACKEND=postgres")
		}
	default:
		return Config{}, fmt.Errorf("unknown DATA_BACKEND value: %s", cfg.DataBackend)
	}

	return cfg, nil
}

func getEnv(key string, defaultValue string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return defaultValue
}

func getInt(key string, defaultValue int) int {
	if v := os.Getenv(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return defaultValue
}

func getDuration(key string, defaultValue time.Duration) time.Duration {
	if v := os.Getenv(key); v != "" {
		if d, err := time.ParseDuration(v); err == nil {
			return d
		}
	}
	return defaultValue
}
