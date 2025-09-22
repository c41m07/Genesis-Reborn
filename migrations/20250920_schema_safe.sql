-- Genesis Reborn - Safe schema creation for existing databases
-- Uses IF NOT EXISTS to avoid dropping existing tables with data

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS players (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(180) NOT NULL UNIQUE,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    INDEX idx_players_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resources (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(60) NOT NULL,
    description TEXT NULL,
    unit VARCHAR(16) NOT NULL DEFAULT 'unités',
    is_tradable TINYINT(1) NOT NULL DEFAULT 1,
    is_consumable TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS planets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    galaxy TINYINT UNSIGNED NOT NULL,
    `system` TINYINT UNSIGNED NOT NULL,
    `position` TINYINT UNSIGNED NOT NULL,
    diameter INT UNSIGNED NOT NULL,
    temperature_min SMALLINT NOT NULL,
    temperature_max SMALLINT NOT NULL,
    is_homeworld TINYINT(1) NOT NULL DEFAULT 0,
    metal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    crystal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    hydrogen BIGINT UNSIGNED NOT NULL DEFAULT 0,
    energy BIGINT NOT NULL DEFAULT 0,
    metal_capacity BIGINT UNSIGNED NOT NULL DEFAULT 1000,
    crystal_capacity BIGINT UNSIGNED NOT NULL DEFAULT 1000,
    hydrogen_capacity BIGINT UNSIGNED NOT NULL DEFAULT 1000,
    energy_capacity BIGINT UNSIGNED NOT NULL DEFAULT 1000,
    last_resource_tick DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_position (galaxy, `system`, `position`),
    INDEX idx_planets_player (player_id),
    INDEX idx_planets_coordinates (galaxy, `system`, `position`),
    CONSTRAINT fk_planets_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS buildings (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    category ENUM('production', 'storage', 'energy', 'research', 'military', 'special') NOT NULL DEFAULT 'production',
    base_cost_metal INT UNSIGNED NOT NULL DEFAULT 0,
    base_cost_crystal INT UNSIGNED NOT NULL DEFAULT 0,
    base_cost_hydrogen INT UNSIGNED NOT NULL DEFAULT 0,
    base_energy_cost INT NOT NULL DEFAULT 0,
    cost_multiplier DECIMAL(6,3) NOT NULL DEFAULT 1.500,
    base_production_metal INT NOT NULL DEFAULT 0,
    base_production_crystal INT NOT NULL DEFAULT 0,
    base_production_hydrogen INT NOT NULL DEFAULT 0,
    base_storage INT UNSIGNED NOT NULL DEFAULT 0,
    base_energy_production INT NOT NULL DEFAULT 0,
    base_energy_consumption INT NOT NULL DEFAULT 0,
    unlock_requirements JSON NULL COMMENT 'Prerequisites in JSON format',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_buildings_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS planet_buildings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    planet_id BIGINT UNSIGNED NOT NULL,
    building_key VARCHAR(64) NOT NULL,
    level INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_planet_building (planet_id, building_key),
    INDEX idx_planet_buildings_planet (planet_id),
    INDEX idx_planet_buildings_building (building_key),
    CONSTRAINT fk_planet_buildings_planet FOREIGN KEY (planet_id) REFERENCES planets(id) ON DELETE CASCADE,
    CONSTRAINT fk_planet_buildings_building FOREIGN KEY (building_key) REFERENCES buildings(`key`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS technologies (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    category ENUM('basic', 'advanced', 'military', 'drive', 'special') NOT NULL DEFAULT 'basic',
    base_cost_metal INT UNSIGNED NOT NULL DEFAULT 0,
    base_cost_crystal INT UNSIGNED NOT NULL DEFAULT 0,
    base_cost_hydrogen INT UNSIGNED NOT NULL DEFAULT 0,
    cost_multiplier DECIMAL(6,3) NOT NULL DEFAULT 2.000,
    unlock_requirements JSON NULL COMMENT 'Prerequisites in JSON format',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_technologies_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_technologies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    technology_key VARCHAR(64) NOT NULL,
    level INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_player_technology (player_id, technology_key),
    INDEX idx_player_technologies_player (player_id),
    INDEX idx_player_technologies_tech (technology_key),
    CONSTRAINT fk_player_technologies_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_player_technologies_technology FOREIGN KEY (technology_key) REFERENCES technologies(`key`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ships (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    category ENUM('civil', 'military', 'special') NOT NULL DEFAULT 'civil',
    cargo_capacity INT UNSIGNED NOT NULL DEFAULT 0,
    base_cost_metal INT UNSIGNED NOT NULL DEFAULT 0,
    base_cost_crystal INT UNSIGNED NOT NULL DEFAULT 0,
    base_cost_hydrogen INT UNSIGNED NOT NULL DEFAULT 0,
    attack_power INT UNSIGNED NOT NULL DEFAULT 0,
    defense_power INT UNSIGNED NOT NULL DEFAULT 0,
    shield_power INT UNSIGNED NOT NULL DEFAULT 0,
    speed INT UNSIGNED NOT NULL DEFAULT 1,
    fuel_consumption DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    unlock_requirements JSON NULL COMMENT 'Prerequisites in JSON format',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ships_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fleets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    from_galaxy TINYINT UNSIGNED NOT NULL,
    from_system TINYINT UNSIGNED NOT NULL,
    from_position TINYINT UNSIGNED NOT NULL,
    to_galaxy TINYINT UNSIGNED NOT NULL,
    to_system TINYINT UNSIGNED NOT NULL,
    to_position TINYINT UNSIGNED NOT NULL,
    mission_type ENUM('attack', 'transport', 'exploration', 'colonization', 'station', 'return') NOT NULL,
    status ENUM('idle', 'moving', 'returning', 'arrived') NOT NULL DEFAULT 'idle',
    departed_at DATETIME NULL,
    arrives_at DATETIME NULL,
    returns_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fleets_player (player_id),
    INDEX idx_fleets_status (status),
    INDEX idx_fleets_arrival (arrives_at),
    CONSTRAINT fk_fleets_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fleet_ships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fleet_id BIGINT UNSIGNED NOT NULL,
    ship_key VARCHAR(64) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_fleet_ship (fleet_id, ship_key),
    INDEX idx_fleet_ships_fleet (fleet_id),
    INDEX idx_fleet_ships_ship (ship_key),
    CONSTRAINT fk_fleet_ships_fleet FOREIGN KEY (fleet_id) REFERENCES fleets(id) ON DELETE CASCADE,
    CONSTRAINT fk_fleet_ships_ship FOREIGN KEY (ship_key) REFERENCES ships(`key`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pve_missions (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    min_fleet_power INT UNSIGNED NOT NULL DEFAULT 0,
    max_fleet_power INT UNSIGNED NOT NULL DEFAULT 0,
    base_rewards JSON NULL COMMENT 'Base rewards in JSON format',
    unlock_requirements JSON NULL COMMENT 'Prerequisites in JSON format',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_pve_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    mission_key VARCHAR(64) NOT NULL,
    fleet_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    rewards_claimed JSON NULL COMMENT 'Claimed rewards in JSON format',
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_player_pve_runs_player (player_id),
    INDEX idx_player_pve_runs_mission (mission_key),
    INDEX idx_player_pve_runs_fleet (fleet_id),
    INDEX idx_player_pve_runs_status (status),
    CONSTRAINT fk_player_pve_runs_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_player_pve_runs_mission FOREIGN KEY (mission_key) REFERENCES pve_missions(`key`) ON UPDATE CASCADE,
    CONSTRAINT fk_player_pve_runs_fleet FOREIGN KEY (fleet_id) REFERENCES fleets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    type ENUM('building', 'research', 'fleet', 'combat', 'resource', 'system') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    data JSON NULL COMMENT 'Event-specific data in JSON format',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_events_player (player_id),
    INDEX idx_events_type (type),
    INDEX idx_events_read (is_read),
    INDEX idx_events_created (created_at),
    CONSTRAINT fk_events_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create a view for legacy compatibility
CREATE OR REPLACE VIEW users AS
SELECT 
    id,
    email,
    username,
    password_hash,
    created_at,
    updated_at,
    last_login_at
FROM players;