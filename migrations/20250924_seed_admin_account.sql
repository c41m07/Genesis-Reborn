-- Seed an administrator account with maxed progression
START TRANSACTION;

-- Create or refresh the administrator player
INSERT INTO players (email, username, password_hash, created_at, updated_at, last_login_at)
VALUES ('admin@genesis.test', 'admin', '$2y$12$H0JYJBfAIQrkQ3VDtkS2QegxgU50fWXzPUATMAxsFGQ2ETFcy6d.a', NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    updated_at = VALUES(updated_at),
    last_login_at = VALUES(last_login_at);

SELECT id INTO @admin_id FROM players WHERE email = 'admin@genesis.test';

-- Ensure the administrator owns a dedicated homeworld
INSERT INTO planets (
    player_id, name, galaxy, `system`, `position`, diameter, temperature_min, temperature_max, is_homeworld,
    metal, crystal, hydrogen, energy, metal_capacity, crystal_capacity, hydrogen_capacity, energy_capacity,
    last_resource_tick, created_at, updated_at
)
VALUES (
    @admin_id, 'Aeternum Prime', 9, 9, 9, 16800, -25, 45, 1,
    500000000, 500000000, 500000000, 500000,
    1000000000, 1000000000, 1000000000, 1000000,
    NOW(), NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
    player_id = VALUES(player_id),
    name = VALUES(name),
    diameter = VALUES(diameter),
    temperature_min = VALUES(temperature_min),
    temperature_max = VALUES(temperature_max),
    is_homeworld = VALUES(is_homeworld),
    metal = VALUES(metal),
    crystal = VALUES(crystal),
    hydrogen = VALUES(hydrogen),
    energy = VALUES(energy),
    metal_capacity = VALUES(metal_capacity),
    crystal_capacity = VALUES(crystal_capacity),
    hydrogen_capacity = VALUES(hydrogen_capacity),
    energy_capacity = VALUES(energy_capacity),
    last_resource_tick = VALUES(last_resource_tick),
    updated_at = VALUES(updated_at);

SELECT id INTO @admin_planet_id
FROM planets
WHERE galaxy = 9 AND `system` = 9 AND `position` = 9;

-- Max out every building on the administrator planet
INSERT INTO planet_buildings (player_id, planet_id, building_id, level, created_at, updated_at)
SELECT @admin_id, @admin_planet_id, b.id, 99, NOW(), NOW()
FROM buildings b
ON DUPLICATE KEY UPDATE
    level = GREATEST(planet_buildings.level, VALUES(level)),
    updated_at = VALUES(updated_at);

-- Unlock every research at its maximum level (10)
INSERT INTO player_technologies (player_id, technology_id, level, created_at, updated_at)
SELECT @admin_id, t.id, 10, NOW(), NOW()
FROM technologies t
ON DUPLICATE KEY UPDATE
    level = GREATEST(player_technologies.level, VALUES(level)),
    updated_at = VALUES(updated_at);

-- Guarantee an idle garrison fleet exists for the administrator
INSERT INTO fleets (
    player_id, origin_planet_id, destination_planet_id, mission_type, status, mission_payload,
    departure_at, arrival_at, return_at, travel_time_seconds, fuel_consumed, created_at, updated_at
)
SELECT @admin_id, @admin_planet_id, NULL, 'idle', 'idle', NULL,
    NULL, NULL, NULL, 0, 0, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM fleets
    WHERE player_id = @admin_id
      AND origin_planet_id = @admin_planet_id
      AND mission_type = 'idle'
      AND status = 'idle'
      AND destination_planet_id IS NULL
    LIMIT 1
);

SELECT id INTO @admin_fleet_id
FROM fleets
WHERE player_id = @admin_id
  AND origin_planet_id = @admin_planet_id
  AND mission_type = 'idle'
  AND status = 'idle'
  AND destination_planet_id IS NULL
ORDER BY id DESC
LIMIT 1;

-- Provide at least one unit of every ship type
INSERT INTO fleet_ships (player_id, fleet_id, ship_id, quantity, created_at, updated_at)
SELECT @admin_id, @admin_fleet_id, s.id, 1, NOW(), NOW()
FROM ships s
ON DUPLICATE KEY UPDATE
    quantity = GREATEST(fleet_ships.quantity, VALUES(quantity)),
    updated_at = VALUES(updated_at);

COMMIT;
