package users

import (
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"errors"
	"fmt"
	"strings"
	"time"
)

var (
	ErrNotImplemented  = errors.New("users repository: not implemented")
	ErrNotFound        = errors.New("user not found")
	ErrInvalidPassword = errors.New("invalid password")
	ErrEmailExists     = errors.New("email already in use")
)

// User represents an authenticated user record.
type User struct {
	ID           string
	Email        string
	Name         string
	PasswordHash string
	PasswordSalt string
	CreatedAt    time.Time
	UpdatedAt    time.Time
}

// Repository defines persistence behaviour for users.
type Repository interface {
	FindByID(id string) (User, error)
	FindByEmail(email string) (User, error)
	Save(user User) (User, error)
}

// NullRepository can be used when no storage is configured.
type NullRepository struct{}

func (NullRepository) FindByID(string) (User, error)    { return User{}, ErrNotImplemented }
func (NullRepository) FindByEmail(string) (User, error) { return User{}, ErrNotImplemented }
func (NullRepository) Save(User) (User, error)          { return User{}, ErrNotImplemented }

// Service exposes user registration and authentication logic.
type Service interface {
	Register(input RegisterInput) (User, error)
	Authenticate(email, password string) (User, error)
}

type service struct {
	repo Repository
}

// RegisterInput captures data required to create an account.
type RegisterInput struct {
	Email    string
	Name     string
	Password string
}

// NewService constructs a user service.
func NewService(repo Repository) Service {
	return &service{repo: repo}
}

func (s *service) Register(input RegisterInput) (User, error) {
	email := strings.TrimSpace(strings.ToLower(input.Email))
	if email == "" {
		return User{}, errors.New("email is required")
	}
	if len(input.Password) < 8 {
		return User{}, errors.New("password must be at least 8 characters")
	}

	if _, err := s.repo.FindByEmail(email); err == nil {
		return User{}, ErrEmailExists
	} else if !errors.Is(err, ErrNotFound) && !errors.Is(err, ErrNotImplemented) {
		return User{}, err
	}

	salt, hash, err := hashPassword(input.Password)
	if err != nil {
		return User{}, err
	}

	user := User{
		Email:        email,
		Name:         strings.TrimSpace(input.Name),
		PasswordHash: hash,
		PasswordSalt: salt,
	}

	saved, err := s.repo.Save(user)
	if err != nil {
		return User{}, err
	}
	return saved, nil
}

func (s *service) Authenticate(email, password string) (User, error) {
	email = strings.TrimSpace(strings.ToLower(email))
	if email == "" {
		return User{}, errors.New("email is required")
	}

	user, err := s.repo.FindByEmail(email)
	if err != nil {
		return User{}, err
	}

	if !verifyPassword(password, user.PasswordSalt, user.PasswordHash) {
		return User{}, ErrInvalidPassword
	}
	return user, nil
}

func hashPassword(password string) (salt, hash string, err error) {
	var buf [16]byte
	if _, err = rand.Read(buf[:]); err != nil {
		return "", "", fmt.Errorf("salt generation failed: %w", err)
	}
	salt = base64.StdEncoding.EncodeToString(buf[:])

	h := sha256.Sum256([]byte(salt + password))
	hash = base64.StdEncoding.EncodeToString(h[:])
	return salt, hash, nil
}

func verifyPassword(password, salt, expectedHash string) bool {
	h := sha256.Sum256([]byte(salt + password))
	return base64.StdEncoding.EncodeToString(h[:]) == expectedHash
}
