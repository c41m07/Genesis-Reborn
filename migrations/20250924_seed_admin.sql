-- Ensure support buildings exist for construction speed bonuses
INSERT INTO buildings (`key`, name, description, category, base_cost_metal, base_cost_crystal, base_cost_hydrogen, base_energy_cost,
    cost_multiplier, base_production_metal, base_production_crystal, base_production_hydrogen, base_storage, base_energy_production,
    base_energy_consumption, unlock_requirements, created_at, updated_at)
VALUES
    ('workers_hub', 'Centre d’ouvriers', 'Optimise la main-d’œuvre et réduit les délais de construction.', 'support',
        450, 220, 0, 0, 1.550, 0, 0, 0, 0, 0, 14,
        '{"buildings":{"metal_mine":6,"crystal_mine":5},"technologies":{"logistics":3}}', NOW(), NOW()),
    ('robotics_center', 'Centre robotique', 'Automatise les chantiers grâce à des drones spécialisés.', 'support',
        1200, 650, 200, 0, 1.600, 0, 0, 0, 0, 0, 30,
        '{"buildings":{"workers_hub":5,"research_lab":4},"technologies":{"engineering_heavy":4}}', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    category = VALUES(category),
    base_cost_metal = VALUES(base_cost_metal),
    base_cost_crystal = VALUES(base_cost_crystal),
    base_cost_hydrogen = VALUES(base_cost_hydrogen),
    cost_multiplier = VALUES(cost_multiplier),
    base_energy_consumption = VALUES(base_energy_consumption),
    unlock_requirements = VALUES(unlock_requirements),
    updated_at = VALUES(updated_at);

-- Seed administrator account with fully unlocked empire
INSERT INTO players (email, username, password_hash, created_at, updated_at, last_login_at)
VALUES ('admin@genesis.test', 'Admin', '$2y$12$4Ir9LyckFCa6VjX22Op4EezPPZPdz2ohw8H1tS8yAO5zaRk.C0GpO', NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE
    username = VALUES(username),
    password_hash = VALUES(password_hash),
    updated_at = VALUES(updated_at),
    last_login_at = VALUES(last_login_at),
    id = LAST_INSERT_ID(id);

SET @admin_player_id = LAST_INSERT_ID();

INSERT INTO planets (
    player_id, name, galaxy, `system`, `position`, diameter, temperature_min, temperature_max, is_homeworld,
    metal, crystal, hydrogen, prod_metal_per_hour, prod_crystal_per_hour, prod_hydrogen_per_hour, prod_energy_per_hour,
    energy, metal_capacity, crystal_capacity, hydrogen_capacity, energy_capacity,
    last_resource_tick, created_at, updated_at
) VALUES (
    @admin_player_id, 'Aeternum Prime', 9, 9, 9, 16000, -35, 55, 1,
    5000000, 3000000, 2000000, 0, 0, 0, 0,
    2500000, 9000000, 7000000, 5000000, 1500000,
    NOW(), NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
    player_id = VALUES(player_id),
    name = VALUES(name),
    metal = VALUES(metal),
    crystal = VALUES(crystal),
    hydrogen = VALUES(hydrogen),
    energy = VALUES(energy),
    metal_capacity = VALUES(metal_capacity),
    crystal_capacity = VALUES(crystal_capacity),
    hydrogen_capacity = VALUES(hydrogen_capacity),
    energy_capacity = VALUES(energy_capacity),
    updated_at = VALUES(updated_at),
    id = LAST_INSERT_ID(id);

SET @admin_planet_id = LAST_INSERT_ID();

INSERT INTO planet_buildings (player_id, planet_id, building_id, level, created_at, updated_at)
SELECT @admin_player_id, @admin_planet_id, b.id, 100, NOW(), NOW()
FROM buildings b
ON DUPLICATE KEY UPDATE level = VALUES(level), updated_at = VALUES(updated_at);

INSERT INTO player_technologies (player_id, technology_id, level, created_at, updated_at)
SELECT @admin_player_id, t.id, 10, NOW(), NOW()
FROM technologies t
ON DUPLICATE KEY UPDATE level = VALUES(level), updated_at = VALUES(updated_at);

INSERT INTO fleets (
    player_id, origin_planet_id, destination_planet_id, mission_type, status, mission_payload,
    departure_at, arrival_at, return_at, travel_time_seconds, fuel_consumed, created_at, updated_at
)
SELECT @admin_player_id, @admin_planet_id, NULL, 'idle', 'idle', NULL, NULL, NULL, NULL, 0, 0, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM fleets WHERE player_id = @admin_player_id AND origin_planet_id = @admin_planet_id
);

SELECT id INTO @admin_fleet_id FROM fleets WHERE player_id = @admin_player_id AND origin_planet_id = @admin_planet_id LIMIT 1;

INSERT INTO fleet_ships (player_id, fleet_id, ship_id, quantity, created_at, updated_at)
SELECT @admin_player_id, @admin_fleet_id, s.id, 1, NOW(), NOW()
FROM ships s
ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = VALUES(updated_at);
