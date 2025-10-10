package database

import (
	"io/fs"
	"os"
)

// MigrationsFS returns a filesystem rooted at the db/migrations directory.
// The directory lives at the module root and is meant to be consumed by
// external migration tools until a compiled-in strategy is adopted.
func MigrationsFS() fs.FS {
	return os.DirFS("db/migrations")
}
