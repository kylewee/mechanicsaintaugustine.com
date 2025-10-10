package users_test

import (
    "testing"

    "github.com/ezmobilemechanic/platform/internal/domain/users"
    memstore "github.com/ezmobilemechanic/platform/internal/storage/memory"
)

func TestServiceRegisterAndAuthenticate(t *testing.T) {
    repo := memstore.NewUserRepository()
    svc := users.NewService(repo)

    user, err := svc.Register(users.RegisterInput{
        Email:    "test@example.com",
        Name:     "Test User",
        Password: "supersecret",
    })
    if err != nil {
        t.Fatalf("register failed: %v", err)
    }
    if user.ID == "" {
        t.Fatalf("expected ID to be set")
    }
    if user.PasswordHash == "" || user.PasswordSalt == "" {
        t.Fatalf("expected password hash and salt")
    }

    authed, err := svc.Authenticate("test@example.com", "supersecret")
    if err != nil {
        t.Fatalf("authenticate failed: %v", err)
    }
    if authed.ID != user.ID {
        t.Fatalf("expected same user ID")
    }

    if _, err := svc.Authenticate("test@example.com", "wrong"); err == nil {
        t.Fatalf("expected error for wrong password")
    }
}
