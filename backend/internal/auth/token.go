package auth

import (
	"crypto/rand"
	"encoding/base64"
	"time"
)

// Token represents a temporary authentication token response.
type Token struct {
	AccessToken string
	TokenType   string
	ExpiresAt   time.Time
}

// IssueFakeToken returns a placeholder token while JWT infrastructure is being built.
func IssueFakeToken() Token {
	var b [32]byte
	_, _ = rand.Read(b[:])
	return Token{
		AccessToken: base64.RawURLEncoding.EncodeToString(b[:]),
		TokenType:   "bearer",
		ExpiresAt:   time.Now().Add(24 * time.Hour),
	}
}
