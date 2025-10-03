-- Create or update demo player with fully boosted colony

SET @now := NOW();
SET @demoEmail := 'demo@example.com';
SET @demoUsername := 'Demo';
SET @password := '$2y$12$5Cnoc30GLizNBay2Zar2Pu0lb6j8zl1WcbUk3dfq8BhniCO.8YCMq';

-- Ensure player exists and capture id
INSERT INTO players (email, username, password_hash, created_at, updated_at)
VALUES (@demoEmail, @demoUsername, @password, @now, @now)
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    updated_at = VALUES(updated_at),
    id = LAST_INSERT_ID(id);

SET @player_id := LAST_INSERT_ID();

-- Create or update dedicated demo planet (galaxy/system/position kept stable for easy discovery)
INSERT INTO planets (
    player_id, name, galaxy, `system`, `position`, diameter,
    temperature_min, temperature_max, is_homeworld,
    metal, crystal, hydrogen, prod_metal_per_hour, prod_crystal_per_hour,
    prod_hydrogen_per_hour, prod_energy_per_hour, energy,
    metal_capacity, crystal_capacity, hydrogen_capacity, energy_capacity,
    last_resource_tick, created_at, updated_at
) VALUES (
    @player_id, 'Planète Démo', 9, 9, 9, 12800,
    -10, 30, 1,
    100000, 100000, 100000, 0, 0,
    0, 0, 0,
    1000000, 1000000, 1000000, 1000000,
    @now, @now, @now
)
ON DUPLICATE KEY UPDATE
    player_id = VALUES(player_id),
    name = VALUES(name),
    metal = VALUES(metal),
    crystal = VALUES(crystal),
    hydrogen = VALUES(hydrogen),
    metal_capacity = VALUES(metal_capacity),
    crystal_capacity = VALUES(crystal_capacity),
    hydrogen_capacity = VALUES(hydrogen_capacity),
    energy_capacity = VALUES(energy_capacity),
    last_resource_tick = VALUES(last_resource_tick),
    updated_at = VALUES(updated_at),
    id = LAST_INSERT_ID(id);

SET @planet_id := LAST_INSERT_ID();

-- Helper for inserting building levels
SET @buildingLevels := JSON_OBJECT(
    'metal_mine', 100,
    'crystal_mine', 100,
    'hydrogen_plant', 100,
    'solar_plant', 50,
    'fusion_reactor', 50,
    'antimatter_reactor', 50,
    'research_lab', 20,
    'shipyard', 20,
    'worker_factory', 10,
    'robot_factory', 10,
    'storage_depot', 5
);

-- Upsert building levels for demo planet
INSERT INTO planet_buildings (player_id, planet_id, building_id, level, created_at, updated_at)
SELECT
    @player_id,
    @planet_id,
    b.id,
    JSON_UNQUOTE(JSON_EXTRACT(@buildingLevels, CONCAT('$.', b.`key`))),
    @now,
    @now
FROM buildings b
WHERE JSON_EXTRACT(@buildingLevels, CONCAT('$.', b.`key`)) IS NOT NULL
ON DUPLICATE KEY UPDATE
    level = VALUES(level),
    updated_at = VALUES(updated_at);

-- Grant all researches level 10
INSERT INTO player_technologies (player_id, technology_id, level, created_at, updated_at)
SELECT
    @player_id,
    t.id,
    10,
    @now,
    @now
FROM technologies t
ON DUPLICATE KEY UPDATE
    level = VALUES(level),
    updated_at = VALUES(updated_at);

-- Guarantee resource stockpile values
UPDATE planets
SET metal = 100000,
    crystal = 100000,
    hydrogen = 100000,
    energy = 0,
    metal_capacity = 1000000,
    crystal_capacity = 1000000,
    hydrogen_capacity = 1000000,
    energy_capacity = 1000000,
    last_resource_tick = @now,
    updated_at = @now
WHERE id = @planet_id;
