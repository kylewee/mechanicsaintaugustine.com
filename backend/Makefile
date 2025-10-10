DATA_BACKEND ?= memory

.PHONY: run test test-integration docker-up docker-down seed

run:
	@echo "Running API with DATA_BACKEND=$(DATA_BACKEND)"
	DATA_BACKEND=$(DATA_BACKEND) go run ./cmd/api

test:
	GOCACHE=$(PWD)/.gocache go test ./...

test-integration:
	@if [ -z "$(TEST_DATABASE_URL)" ]; then \
		echo "TEST_DATABASE_URL must be set" >&2; exit 1; \
	fi
	GOCACHE=$(PWD)/.gocache TEST_DATABASE_URL=$(TEST_DATABASE_URL) go test -tags=integration ./internal/storage/postgres

docker-up:
	scripts/db/up.sh

docker-down:
	scripts/db/down.sh

seed:
	@if [ "$(DATA_BACKEND)" != "postgres" ]; then \
		echo "DATA_BACKEND must be postgres" >&2; exit 1; \
	fi
	@if [ -z "$(DATABASE_URL)" ]; then \
		echo "DATABASE_URL must be set" >&2; exit 1; \
	fi
	DATA_BACKEND=$(DATA_BACKEND) DATABASE_URL=$(DATABASE_URL) go run ./cmd/seed
