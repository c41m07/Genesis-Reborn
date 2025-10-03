SET @prev_collation_connection := @@collation_connection;
SET collation_connection = 'utf8mb4_unicode_ci';

SET @now := NOW();
SET @demo_email := 'demo@genesis.local';
SET @demo_username := 'DemoPlayer';
SET @demo_password_hash := '$2y$12$mdihcloCjxKrkLJxULiU2.phWYrELyCWEmh0PGA2jjOCKMg4a1twy';

INSERT INTO players (email, username, password_hash, created_at, updated_at)
VALUES (@demo_email, @demo_username, @demo_password_hash, @now, @now)
ON DUPLICATE KEY UPDATE
    username = VALUES(username),
    password_hash = VALUES(password_hash),
    updated_at = VALUES(updated_at);

SET @player_id := (SELECT id FROM players WHERE email = @demo_email);

INSERT INTO planets (
    player_id, name, galaxy, `system`, `position`,
    diameter, temperature_min, temperature_max,
    is_homeworld, metal, crystal, hydrogen, energy,
    prod_metal_per_hour, prod_crystal_per_hour, prod_hydrogen_per_hour, prod_energy_per_hour,
    metal_capacity, crystal_capacity, hydrogen_capacity, energy_capacity,
    last_resource_tick, created_at, updated_at
) VALUES (
    @player_id, 'Colonie Démo', 1, 1, 1,
    12800, -10, 40,
    1, 100000, 100000, 100000, 100000,
    0, 0, 0, 0,
    100000, 100000, 100000, 100000,
    @now, @now, @now
)
ON DUPLICATE KEY UPDATE
    id = LAST_INSERT_ID(id),
    player_id = VALUES(player_id),
    name = VALUES(name),
    is_homeworld = VALUES(is_homeworld),
    metal = VALUES(metal),
    crystal = VALUES(crystal),
    hydrogen = VALUES(hydrogen),
    energy = VALUES(energy),
    metal_capacity = GREATEST(metal_capacity, VALUES(metal_capacity)),
    crystal_capacity = GREATEST(crystal_capacity, VALUES(crystal_capacity)),
    hydrogen_capacity = GREATEST(hydrogen_capacity, VALUES(hydrogen_capacity)),
    energy_capacity = GREATEST(energy_capacity, VALUES(energy_capacity)),
    last_resource_tick = VALUES(last_resource_tick),
    updated_at = VALUES(updated_at);

SET @planet_id := LAST_INSERT_ID();

INSERT INTO planet_buildings (player_id, planet_id, building_id, level, created_at, updated_at)
SELECT
    @player_id,
    @planet_id,
    b.id,
    CASE b.`key`
        WHEN 'metal_mine' THEN 100
        WHEN 'crystal_mine' THEN 100
        WHEN 'hydrogen_plant' THEN 100
        WHEN 'solar_plant' THEN 50
        WHEN 'fusion_reactor' THEN 50
        WHEN 'antimatter_reactor' THEN 50
        WHEN 'shipyard' THEN 10
        WHEN 'research_lab' THEN 10
        WHEN 'worker_factory' THEN 5
        WHEN 'robot_factory' THEN 5
    END AS target_level,
    @now,
    @now
FROM buildings b
WHERE b.`key` IN (
    'metal_mine', 'crystal_mine', 'hydrogen_plant',
    'solar_plant', 'fusion_reactor', 'antimatter_reactor',
    'shipyard', 'research_lab',
    'worker_factory', 'robot_factory'
)
ON DUPLICATE KEY UPDATE
    level = VALUES(level),
    updated_at = VALUES(updated_at);

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

-- migrate:down
SET @demo_email := 'demo@genesis.local';
SET @player_id := (SELECT id FROM players WHERE email = @demo_email);
SET @planet_id := (SELECT id FROM planets WHERE player_id = @player_id);

DELETE FROM player_technologies WHERE player_id = @player_id;
DELETE FROM planet_buildings WHERE planet_id = @planet_id;
DELETE FROM planets WHERE id = @planet_id;
DELETE FROM players WHERE id = @player_id;

SET collation_connection = @prev_collation_connection;
