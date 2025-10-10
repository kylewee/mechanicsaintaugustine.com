package postgres

import (
	"database/sql"
	"errors"
	"fmt"
	"time"

	"github.com/ezmobilemechanic/platform/internal/domain/vehicles"
)

// VehicleRepository persists vehicles in Postgres.
type VehicleRepository struct {
	db *sql.DB
}

// NewVehicleRepository constructs the repository.
func NewVehicleRepository(db *sql.DB) *VehicleRepository {
	return &VehicleRepository{db: db}
}

// FindByID fetches a vehicle by identifier.
func (r *VehicleRepository) FindByID(id string) (vehicles.Vehicle, error) {
	const query = `
        SELECT id, customer_id, vin, year, make, model, trim, engine, mileage,
               created_at, updated_at
          FROM vehicles
         WHERE id = $1
    `

	var v vehicles.Vehicle
	err := r.db.QueryRow(query, id).Scan(
		&v.ID,
		&v.CustomerID,
		&v.VIN,
		&v.Year,
		&v.Make,
		&v.Model,
		&v.Trim,
		&v.Engine,
		&v.Mileage,
		&v.CreatedAt,
		&v.UpdatedAt,
	)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return vehicles.Vehicle{}, vehicles.ErrNotFound
		}
		return vehicles.Vehicle{}, fmt.Errorf("find vehicle: %w", err)
	}

	return v, nil
}

// ListByCustomer returns vehicles for a customer ordered by creation.
func (r *VehicleRepository) ListByCustomer(customerID string) ([]vehicles.Vehicle, error) {
	const query = `
        SELECT id, customer_id, vin, year, make, model, trim, engine, mileage,
               created_at, updated_at
          FROM vehicles
         WHERE customer_id = $1
         ORDER BY created_at
    `

	rows, err := r.db.Query(query, customerID)
	if err != nil {
		return nil, fmt.Errorf("list vehicles: %w", err)
	}
	defer rows.Close()

	var result []vehicles.Vehicle
	for rows.Next() {
		var v vehicles.Vehicle
		if err := rows.Scan(
			&v.ID,
			&v.CustomerID,
			&v.VIN,
			&v.Year,
			&v.Make,
			&v.Model,
			&v.Trim,
			&v.Engine,
			&v.Mileage,
			&v.CreatedAt,
			&v.UpdatedAt,
		); err != nil {
			return nil, fmt.Errorf("scan vehicle: %w", err)
		}
		result = append(result, v)
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("rows error: %w", err)
	}

	return result, nil
}

// Save inserts or updates vehicle data.
func (r *VehicleRepository) Save(vehicle vehicles.Vehicle) (vehicles.Vehicle, error) {
	now := time.Now().UTC()

	if vehicle.ID == "" {
		const insert = `
            INSERT INTO vehicles (customer_id, vin, year, make, model, trim, engine, mileage, created_at, updated_at)
            VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)
            RETURNING id
        `
		if err := r.db.QueryRow(insert,
			vehicle.CustomerID,
			vehicle.VIN,
			vehicle.Year,
			vehicle.Make,
			vehicle.Model,
			vehicle.Trim,
			vehicle.Engine,
			vehicle.Mileage,
			now,
			now,
		).Scan(&vehicle.ID); err != nil {
			return vehicles.Vehicle{}, fmt.Errorf("insert vehicle: %w", err)
		}
		vehicle.CreatedAt = now
		vehicle.UpdatedAt = now
		return vehicle, nil
	}

	const update = `
        UPDATE vehicles
           SET customer_id = $2,
               vin = $3,
               year = $4,
               make = $5,
               model = $6,
               trim = $7,
               engine = $8,
               mileage = $9,
               updated_at = $10
         WHERE id = $1
        RETURNING created_at
    `

	var created time.Time
	err := r.db.QueryRow(update,
		vehicle.ID,
		vehicle.CustomerID,
		vehicle.VIN,
		vehicle.Year,
		vehicle.Make,
		vehicle.Model,
		vehicle.Trim,
		vehicle.Engine,
		vehicle.Mileage,
		now,
	).Scan(&created)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return vehicles.Vehicle{}, vehicles.ErrNotFound
		}
		return vehicles.Vehicle{}, fmt.Errorf("update vehicle: %w", err)
	}
	vehicle.CreatedAt = created
	vehicle.UpdatedAt = now
	return vehicle, nil
}
