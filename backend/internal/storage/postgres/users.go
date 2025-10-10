package postgres

import (
	"database/sql"
	"errors"
	"fmt"
	"strings"
	"time"

	"github.com/ezmobilemechanic/platform/internal/domain/users"
)

// UserRepository persists users in Postgres.
type UserRepository struct {
	db *sql.DB
}

// NewUserRepository constructs a postgres-backed user repository.
func NewUserRepository(db *sql.DB) *UserRepository {
	return &UserRepository{db: db}
}

func (r *UserRepository) FindByID(id string) (users.User, error) {
	const query = `
        SELECT id, email, name, password_hash, password_salt, created_at, updated_at
          FROM users
         WHERE id = $1
    `
	var u users.User
	err := r.db.QueryRow(query, id).Scan(&u.ID, &u.Email, &u.Name, &u.PasswordHash, &u.PasswordSalt, &u.CreatedAt, &u.UpdatedAt)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return users.User{}, users.ErrNotFound
		}
		return users.User{}, fmt.Errorf("find user: %w", err)
	}
	return u, nil
}

func (r *UserRepository) FindByEmail(email string) (users.User, error) {
	const query = `
        SELECT id, email, name, password_hash, password_salt, created_at, updated_at
          FROM users
         WHERE LOWER(email) = LOWER($1)
    `
	var u users.User
	err := r.db.QueryRow(query, email).Scan(&u.ID, &u.Email, &u.Name, &u.PasswordHash, &u.PasswordSalt, &u.CreatedAt, &u.UpdatedAt)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return users.User{}, users.ErrNotFound
		}
		return users.User{}, fmt.Errorf("find user by email: %w", err)
	}
	return u, nil
}

func (r *UserRepository) Save(user users.User) (users.User, error) {
	now := time.Now().UTC()

	if user.ID == "" {
		const insert = `
            INSERT INTO users (email, name, password_hash, password_salt, created_at, updated_at)
            VALUES ($1,$2,$3,$4,$5,$6)
            RETURNING id
        `
		if err := r.db.QueryRow(insert,
			strings.ToLower(user.Email),
			user.Name,
			user.PasswordHash,
			user.PasswordSalt,
			now,
			now,
		).Scan(&user.ID); err != nil {
			return users.User{}, fmt.Errorf("insert user: %w", err)
		}
		user.CreatedAt = now
		user.UpdatedAt = now
		return user, nil
	}

	const update = `
        UPDATE users
           SET email = $2,
               name = $3,
               password_hash = $4,
               password_salt = $5,
               updated_at = $6
         WHERE id = $1
        RETURNING created_at
    `
	var created time.Time
	err := r.db.QueryRow(update,
		user.ID,
		strings.ToLower(user.Email),
		user.Name,
		user.PasswordHash,
		user.PasswordSalt,
		now,
	).Scan(&created)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return users.User{}, users.ErrNotFound
		}
		return users.User{}, fmt.Errorf("update user: %w", err)
	}
	user.CreatedAt = created
	user.UpdatedAt = now
	return user, nil
}

var _ users.Repository = (*UserRepository)(nil)
