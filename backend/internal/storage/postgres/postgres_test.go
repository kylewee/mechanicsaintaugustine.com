//go:build integration

package postgres_test

import (
	"context"
	"database/sql"
	"os"
	"testing"

	_ "github.com/jackc/pgx/v5/stdlib"
)

func setupTestDB(t *testing.T) *sql.DB {
	t.Helper()

	dsn := os.Getenv("TEST_DATABASE_URL")
	if dsn == "" {
		t.Skip("TEST_DATABASE_URL not set; skipping postgres integration tests")
	}

	db, err := sql.Open("pgx", dsn)
	if err != nil {
		t.Fatalf("open db: %v", err)
	}

	if err := db.PingContext(context.Background()); err != nil {
		db.Close()
		t.Fatalf("ping db: %v", err)
	}

	cleanupTables(t, db)

	return db
}

func cleanupTables(t *testing.T, db *sql.DB) {
	t.Helper()
	stmts := []string{
		"TRUNCATE quote_line_items CASCADE",
		"TRUNCATE quotes CASCADE",
		"TRUNCATE vehicles CASCADE",
		"TRUNCATE customers CASCADE",
	}
	for _, stmt := range stmts {
		if _, err := db.Exec(stmt); err != nil {
			t.Fatalf("cleanup %s: %v", stmt, err)
		}
	}
}
