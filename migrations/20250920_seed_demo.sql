-- Demo player seed data
INSERT INTO players (email, username, password_hash, created_at, updated_at, last_login_at)
VALUES ('demo@genesis.test', 'demo', '$2y$12$e0UmNdog/qnt2fpgT6oGk.bhVZkN6nECqIFznKbkUM3bgzuHzMcpW', NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE
    username = VALUES(username),
    password_hash = VALUES(password_hash),
    updated_at = VALUES(updated_at),
    last_login_at = VALUES(last_login_at),
    id = LAST_INSERT_ID(id);

SET @demo_player_id = LAST_INSERT_ID();

INSERT INTO planets (
    player_id, name, galaxy, `system`, `position`, diameter, temperature_min, temperature_max, is_homeworld,
    metal, crystal, hydrogen, energy,
    prod_metal_per_hour, prod_crystal_per_hour, prod_hydrogen_per_hour, prod_energy_per_hour,
    metal_capacity, crystal_capacity, hydrogen_capacity, energy_capacity,
    last_resource_tick, created_at, updated_at
) VALUES (
    @demo_player_id, 'Nova Prime', 1, 7, 8, 12800, -10, 45, 1,
    12000, 6000, 3000, 80,
    0, 0, 0, 0,
    120000, 90000, 60000, 150,
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

SET @demo_planet_id = LAST_INSERT_ID();

INSERT INTO planet_buildings (player_id, planet_id, building_id, level, created_at, updated_at)
SELECT @demo_player_id, @demo_planet_id, b.id, lvl.level, NOW(), NOW()
FROM (
    SELECT 'metal_mine' AS bkey, 6 AS level UNION ALL
    SELECT 'crystal_mine', 5 UNION ALL
    SELECT 'hydrogen_plant', 3 UNION ALL
    SELECT 'solar_plant', 7 UNION ALL
    SELECT 'fusion_reactor', 3 UNION ALL
    SELECT 'storage_depot', 3 UNION ALL
    SELECT 'research_lab', 2 UNION ALL
    SELECT 'shipyard', 2
) AS lvl
JOIN buildings b ON b.`key` = lvl.bkey
ON DUPLICATE KEY UPDATE
    level = VALUES(level),
    updated_at = VALUES(updated_at);

INSERT INTO player_technologies (player_id, technology_id, level, created_at, updated_at)
SELECT @demo_player_id, tech.id, 0, NOW(), NOW()
FROM technologies tech
ON DUPLICATE KEY UPDATE
    level = VALUES(level),
    updated_at = VALUES(updated_at);
