# Database Migrations

Store SQL migration files here following the `<version>_<name>.up.sql` / `.down.sql` naming convention compatible with tools like [golang-migrate](https://github.com/golang-migrate/migrate).

Example:

```
001_init_schema.up.sql
001_init_schema.down.sql
```

During development run migrations via the helper script in `scripts/db/migrate.sh` (to be implemented) or through the CI pipeline. The Go service currently calls `RunMigrations` in `internal/database` — once a migration engine is wired in, it should execute files from this directory at startup.
