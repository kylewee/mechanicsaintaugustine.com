package database

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"io/fs"
	"path"
	"sort"
	"strings"

	"log/slog"
)

// Migrator defines an interface capable of applying schema migrations.
type Migrator interface {
	Up(ctx context.Context) error
}

// SQLMigrator executes .sql migration files against a database connection.
type SQLMigrator struct {
	Logger *slog.Logger
	DB     *sql.DB
	FS     fs.FS
	Path   string
}

// NewSQLMigrator builds a migrator that runs SQL statements from the provided filesystem.
func NewSQLMigrator(db *sql.DB, f fs.FS, dir string, logger *slog.Logger) *SQLMigrator {
	return &SQLMigrator{DB: db, FS: f, Path: dir, Logger: logger}
}

// Up executes all *.up.sql files in lexical order.
func (m *SQLMigrator) Up(ctx context.Context) error {
	if m == nil {
		return errors.New("sql migrator is nil")
	}
	if m.DB == nil {
		return errors.New("sql migrator requires a database handle")
	}
	if m.FS == nil {
		return errors.New("sql migrator requires a filesystem")
	}
	if m.Path == "" {
		return errors.New("sql migrator requires a path")
	}

	logger := m.Logger
	if logger == nil {
		logger = slog.Default()
	}

	entries, err := fs.ReadDir(m.FS, m.Path)
	if err != nil {
		return fmt.Errorf("read migrations dir: %w", err)
	}

	sort.Slice(entries, func(i, j int) bool {
		return entries[i].Name() < entries[j].Name()
	})

	applied := 0
	for _, entry := range entries {
		if entry.IsDir() {
			continue
		}
		name := entry.Name()
		if !strings.HasSuffix(name, ".up.sql") {
			continue
		}

		contents, err := fs.ReadFile(m.FS, path.Join(m.Path, name))
		if err != nil {
			return fmt.Errorf("read migration %s: %w", name, err)
		}

		statements := splitSQLStatements(string(contents))
		if len(statements) == 0 {
			logger.Info("skipping empty migration", "file", name)
			continue
		}

		for i, stmt := range statements {
			if stmt == "" {
				continue
			}
			if _, err := m.DB.ExecContext(ctx, stmt); err != nil {
				return fmt.Errorf("exec %s [%d]: %w", name, i+1, err)
			}
		}
		applied++
		logger.Info("migration applied", "file", name)
	}

	if applied == 0 {
		logger.Info("no migrations to run")
	}
	return nil
}

func splitSQLStatements(sqlText string) []string {
	raw := strings.Split(sqlText, ";")
	out := make([]string, 0, len(raw))
	for _, stmt := range raw {
		trimmed := strings.TrimSpace(stmt)
		if trimmed != "" {
			out = append(out, trimmed)
		}
	}
	return out
}
