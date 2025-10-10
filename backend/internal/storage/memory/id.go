package memory

import (
    "crypto/rand"
    "encoding/hex"
)

func newID() string {
    var b [16]byte
    if _, err := rand.Read(b[:]); err != nil {
        return hex.EncodeToString(b[:])
    }
    return hex.EncodeToString(b[:])
}
