# ezmobilemechanic Platform Backend

Go service powering the shop-management platform. Current capabilities:

- Structured configuration via environment variables (see below).
- JSON logger built on `slog` with request timing middleware.
- HTTP server with `/healthz` and `/v1/ping` endpoints, graceful shutdown, and modular route registration.
- In-memory repositories for customers/vehicles/quotes powering the current `/v1/customers`, `/v1/vehicles`, and `/v1/quotes` endpoints (temporary).
- Postgres repository skeletons (customers, vehicles, quotes) and initial schema migration (`db/migrations/001_init_schema.up.sql`).
- Database package handling pooled SQL connections and migration hook stubs.
- Placeholder import of `github.com/shopmonkeyus/go-datamodel/v3` for upcoming domain modeling.

## Configuration

Set the following environment variables before running locally:

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `development` | Environment label (controls log level). |
| `HTTP_PORT` | `8080` | Port for the HTTP server. |
| `SHUTDOWN_TIMEOUT` | `10s` | Graceful shutdown timeout. |
| `READ_HEADER_TIMEOUT` | `5s` | Header read timeout. |
| `DATA_BACKEND` | `memory` | `memory` keeps using in-process stores; set to `postgres` to enable the SQL repositories. |
| `DATABASE_DRIVER` | `postgres` | SQL driver name passed to `database/sql` (used when `DATA_BACKEND=postgres`). |
| `DATABASE_URL` | _(required)_ | DSN/URL for the database connection (required when `DATA_BACKEND=postgres`). |
| `DB_MAX_OPEN_CONNS` | `10` | Max open connections. |
| `DB_MAX_IDLE_CONNS` | `5` | Max idle connections. |
| `DB_CONN_MAX_LIFETIME` | `1h` | Connection lifetime. |
| `DB_CONN_MAX_IDLE_TIME` | `30m` | Idle timeout. |
| `JWT_SECRET` | _(required)_ | Secret for signing JWTs. |
| `JWT_EXPIRY` | `24h` | Access token lifespan. |
| `REFRESH_TOKEN_TTL` | `720h` | Refresh token lifespan. |
| `REDIS_URL` | | Optional cache/message bus endpoint. |

## Running Locally

```bash
export DATABASE_URL="postgres://user:pass@localhost:5432/ezm?sslmode=disable"
export JWT_SECRET="dev-secret"

go run ./cmd/api
```

## Sample API Calls (temporary in-memory stores)

```bash
# create a customer
curl -s -X POST http://localhost:8080/v1/customers \
  -H 'Content-Type: application/json' \
  -d '{"first_name":"Alex","last_name":"Driver","email":"alex@example.com"}' | jq

# list customers
curl -s http://localhost:8080/v1/customers | jq

# create a vehicle for the customer id returned above
curl -s -X POST http://localhost:8080/v1/vehicles \
  -H 'Content-Type: application/json' \
  -d '{"customer_id":"<customer_id>","make":"Ford","model":"Transit","year":2020}' | jq

# create a quote referencing the customer & vehicle
curl -s -X POST http://localhost:8080/v1/quotes \
  -H 'Content-Type: application/json' \
  -d '{"customer_id":"<customer_id>","vehicle_id":"<vehicle_id>","line_items":[{"description":"Brake Pads","quantity":1,"unit_price":15000}]}' | jq

# update quote status
curl -s -X PATCH http://localhost:8080/v1/quotes/<quote_id> \
  -H 'Content-Type: application/json' \
  -d '{"status":"accepted"}' | jq

# register a user
curl -s -X POST http://localhost:8080/v1/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","name":"Demo User","password":"supersecret"}' | jq

# login as that user
curl -s -X POST http://localhost:8080/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"supersecret"}' | jq
```

> **Note:** The SQL driver (e.g., `github.com/jackc/pgx/v5/stdlib`) must be imported before connecting. Add it where appropriate once dependency downloads are permitted.

## Postgres via docker-compose

```bash
cd backend
make docker-up

# enable pgcrypto once
docker exec -it ezm-db psql -U ezm -d ezm -c "CREATE EXTENSION IF NOT EXISTS pgcrypto;"

# start API against Postgres
make run DATA_BACKEND=postgres DATABASE_URL="postgres://ezm:ezm@localhost:5432/ezm?sslmode=disable"

# tear down when finished
make docker-down
```

With the stack running you can browse Adminer at http://localhost:8081 (server `db`, user `ezm`, password `ezm`).

### Seed sample data

```bash
cd backend
export DATA_BACKEND=postgres
export DATABASE_URL="postgres://ezm:ezm@localhost:5432/ezm?sslmode=disable"
make seed
```

This inserts a couple of sample customers, vehicles, and quotes using the Postgres repositories.

### Integration tests against Postgres

The Postgres repositories include integration tests guarded by the `integration` build tag. Once the container stack is running and the schema migrated:

```bash
cd backend
export TEST_DATABASE_URL="postgres://ezm:ezm@localhost:5432/ezm?sslmode=disable"
make test-integration
```

The tests expect the `pgx` driver (`go get github.com/jackc/pgx/v5/stdlib`) to be available when the integration tag is used.

## Migrations

Store SQL files in `db/migrations`. Use the helper script (requires [golang-migrate](https://github.com/golang-migrate/migrate)):

```bash
export DATABASE_URL="postgres://user:pass@localhost:5432/ezm?sslmode=disable"
scripts/db/migrate.sh up
```

When `DATA_BACKEND=postgres`, the service will automatically execute `.up.sql` files from `db/migrations` at startup using a lightweight built-in migrator (statements are run in lexical order; keep files simple—no procedural SQL blocks).

> Ensure the `pgcrypto` extension is available (run `CREATE EXTENSION IF NOT EXISTS pgcrypto;`) before applying `001_init_schema.up.sql`, as the schema uses `gen_random_uuid()` for primary keys.

## Next Steps

1. Flip `DATA_BACKEND=postgres` (with database connectivity) to exercise the new repositories; follow up with integration tests.
2. Integrate a real migration runner (golang-migrate/Atlas) inside `database.RunMigrations`.
3. Flesh out authentication (JWT issuance, refresh flow) and user management endpoints.
4. Add telemetry (structured tracing/metrics) and request validation middleware.
5. Back the `/v1` routes with real business logic and integrate with the front-end/API outline.
6. Expand automated tests (unit + integration) and CI pipeline.
