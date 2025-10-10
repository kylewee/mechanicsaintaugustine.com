package memory

import (
	"sort"
	"strings"
	"sync"
	"time"

	"github.com/ezmobilemechanic/platform/internal/domain/users"
)

// UserRepository implements users.Repository in-memory.
type UserRepository struct {
	mu    sync.RWMutex
	store map[string]users.User
}

// NewUserRepository constructs repository.
func NewUserRepository() *UserRepository {
	return &UserRepository{store: make(map[string]users.User)}
}

func (r *UserRepository) FindByID(id string) (users.User, error) {
	r.mu.RLock()
	defer r.mu.RUnlock()
	user, ok := r.store[id]
	if !ok {
		return users.User{}, users.ErrNotFound
	}
	return user, nil
}

func (r *UserRepository) FindByEmail(email string) (users.User, error) {
	r.mu.RLock()
	defer r.mu.RUnlock()
	for _, u := range r.store {
		if strings.EqualFold(u.Email, email) {
			return u, nil
		}
	}
	return users.User{}, users.ErrNotFound
}

func (r *UserRepository) Save(user users.User) (users.User, error) {
	r.mu.Lock()
	defer r.mu.Unlock()

	now := time.Now().UTC()
	if user.ID == "" {
		user.ID = newID()
		user.CreatedAt = now
	} else if existing, ok := r.store[user.ID]; ok {
		if user.CreatedAt.IsZero() {
			user.CreatedAt = existing.CreatedAt
		}
	}
	user.UpdatedAt = now
	r.store[user.ID] = user
	return user, nil
}

func (r *UserRepository) List() []users.User {
	r.mu.RLock()
	defer r.mu.RUnlock()
	res := make([]users.User, 0, len(r.store))
	for _, u := range r.store {
		res = append(res, u)
	}
	sort.Slice(res, func(i, j int) bool {
		return res[i].CreatedAt.Before(res[j].CreatedAt)
	})
	return res
}

// Ensure interface satisfaction at compile time.
var _ users.Repository = (*UserRepository)(nil)
