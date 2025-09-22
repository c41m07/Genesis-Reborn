-- Genesis Reborn - Solo schema for multiplayer-ready foundation

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS "genesis_reborn" DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

DROP VIEW IF EXISTS users;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS player_pve_runs;
DROP TABLE IF EXISTS fleet_ships;
DROP TABLE IF EXISTS fleets;
DROP TABLE IF EXISTS ships;
DROP TABLE IF EXISTS player_technologies;
DROP TABLE IF EXISTS technologies;
DROP TABLE IF EXISTS planet_buildings;
DROP TABLE IF EXISTS buildings;
DROP TABLE IF EXISTS planets;
DROP TABLE IF EXISTS resources;
DROP TABLE IF EXISTS players;
DROP TABLE IF EXISTS pve_missions;

-- legacy tables from previous prototype
DROP TABLE IF EXISTS build_queue;
DROP TABLE IF EXISTS planet_fleet;
DROP TABLE IF EXISTS planet_research;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE players (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(180) NOT NULL UNIQUE,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    INDEX idx_players_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE resources (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(60) NOT NULL,
    description TEXT NULL,
    unit VARCHAR(16) NOT NULL DEFAULT 'unités',
    is_tradable TINYINT(1) NOT NULL DEFAULT 1,
    is_consumable TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE planets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    galaxy SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `system` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `position` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    diameter INT UNSIGNED NULL,
    temperature_min SMALLINT NULL,
    temperature_max SMALLINT NULL,
    is_homeworld TINYINT(1) NOT NULL DEFAULT 0,
    metal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    crystal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    hydrogen BIGINT UNSIGNED NOT NULL DEFAULT 0,
    energy BIGINT NOT NULL DEFAULT 0,
    metal_capacity BIGINT UNSIGNED NOT NULL DEFAULT 10000,
    crystal_capacity BIGINT UNSIGNED NOT NULL DEFAULT 10000,
    hydrogen_capacity BIGINT UNSIGNED NOT NULL DEFAULT 10000,
    energy_capacity BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_resource_tick DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_planet_coordinates (galaxy, `system`, `position`),
    INDEX idx_planets_player (player_id),
    CONSTRAINT fk_planets_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE buildings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    category ENUM('production','energy','storage','research','shipyard','support') NOT NULL DEFAULT 'production',
    base_cost_metal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    base_cost_crystal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    base_cost_hydrogen BIGINT UNSIGNED NOT NULL DEFAULT 0,
    base_energy_cost INT NOT NULL DEFAULT 0,
    cost_multiplier DECIMAL(6,3) NOT NULL DEFAULT 1.500,
    base_production_metal INT NOT NULL DEFAULT 0,
    base_production_crystal INT NOT NULL DEFAULT 0,
    base_production_hydrogen INT NOT NULL DEFAULT 0,
    base_storage BIGINT UNSIGNED NOT NULL DEFAULT 0,
    base_energy_production INT NOT NULL DEFAULT 0,
    base_energy_consumption INT NOT NULL DEFAULT 0,
    unlock_requirements JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE planet_buildings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    planet_id BIGINT UNSIGNED NOT NULL,
    building_id BIGINT UNSIGNED NOT NULL,
    level INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_planet_building (planet_id, building_id),
    INDEX idx_planet_buildings_player (player_id),
    CONSTRAINT fk_planet_buildings_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_planet_buildings_planet FOREIGN KEY (planet_id) REFERENCES planets(id) ON DELETE CASCADE,
    CONSTRAINT fk_planet_buildings_building FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE technologies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    category ENUM('research','military','economy','fleet','support') NOT NULL DEFAULT 'research',
    base_cost_metal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    base_cost_crystal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    base_cost_hydrogen BIGINT UNSIGNED NOT NULL DEFAULT 0,
    base_energy_cost INT NOT NULL DEFAULT 0,
    cost_multiplier DECIMAL(6,3) NOT NULL DEFAULT 1.600,
    base_duration_seconds INT UNSIGNED NOT NULL DEFAULT 60,
    unlock_requirements JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE player_technologies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    technology_id BIGINT UNSIGNED NOT NULL,
    level INT UNSIGNED NOT NULL DEFAULT 0,
    researching_planet_id BIGINT UNSIGNED NULL,
    queued_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_player_technology (player_id, technology_id),
    INDEX idx_player_technologies_player (player_id),
    CONSTRAINT fk_player_technologies_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_player_technologies_technology FOREIGN KEY (technology_id) REFERENCES technologies(id) ON DELETE CASCADE,
    CONSTRAINT fk_player_technologies_planet FOREIGN KEY (researching_planet_id) REFERENCES planets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    class ENUM('civilian','fighter','frigate','capital','utility','exploration') NOT NULL DEFAULT 'civilian',
    base_cost_metal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    base_cost_crystal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    base_cost_hydrogen BIGINT UNSIGNED NOT NULL DEFAULT 0,
    base_energy_cost INT NOT NULL DEFAULT 0,
    build_time_seconds INT UNSIGNED NOT NULL DEFAULT 60,
    base_speed INT UNSIGNED NOT NULL DEFAULT 0,
    base_cargo BIGINT UNSIGNED NOT NULL DEFAULT 0,
    fuel_per_distance DECIMAL(12,6) NOT NULL DEFAULT 0,
    attack INT UNSIGNED NOT NULL DEFAULT 0,
    defense INT UNSIGNED NOT NULL DEFAULT 0,
    shield INT UNSIGNED NOT NULL DEFAULT 0,
    unlock_requirements JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fleets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    origin_planet_id BIGINT UNSIGNED NOT NULL,
    destination_planet_id BIGINT UNSIGNED NULL,
    mission_type ENUM('idle','transport','attack','harvest','expedition','pve','explore') NOT NULL DEFAULT 'idle',
    status ENUM('idle','outbound','returning','holding','completed','failed') NOT NULL DEFAULT 'idle',
    mission_payload JSON NULL,
    departure_at DATETIME NULL,
    arrival_at DATETIME NULL,
    return_at DATETIME NULL,
    travel_time_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    fuel_consumed BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fleets_player (player_id),
    INDEX idx_fleets_status (status),
    CONSTRAINT fk_fleets_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_fleets_origin FOREIGN KEY (origin_planet_id) REFERENCES planets(id) ON DELETE CASCADE,
    CONSTRAINT fk_fleets_destination FOREIGN KEY (destination_planet_id) REFERENCES planets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fleet_ships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    fleet_id BIGINT UNSIGNED NOT NULL,
    ship_id BIGINT UNSIGNED NOT NULL,
    quantity BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_fleet_ship (fleet_id, ship_id),
    INDEX idx_fleet_ships_player (player_id),
    CONSTRAINT fk_fleet_ships_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_fleet_ships_fleet FOREIGN KEY (fleet_id) REFERENCES fleets(id) ON DELETE CASCADE,
    CONSTRAINT fk_fleet_ships_ship FOREIGN KEY (ship_id) REFERENCES ships(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pve_missions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    difficulty ENUM('easy','normal','hard','extreme','legendary') NOT NULL DEFAULT 'easy',
    recommended_power INT UNSIGNED NOT NULL DEFAULT 0,
    base_duration_seconds INT UNSIGNED NOT NULL DEFAULT 600,
    reward_metal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reward_crystal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reward_hydrogen BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reward_experience INT UNSIGNED NOT NULL DEFAULT 0,
    unlock_requirements JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE player_pve_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    mission_id BIGINT UNSIGNED NOT NULL,
    fleet_id BIGINT UNSIGNED NULL,
    status ENUM('queued','launched','resolving','succeeded','failed','aborted') NOT NULL DEFAULT 'queued',
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    outcome JSON NULL,
    reward_metal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reward_crystal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reward_hydrogen BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reward_experience INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_player_runs (player_id, status),
    CONSTRAINT fk_player_runs_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_player_runs_mission FOREIGN KEY (mission_id) REFERENCES pve_missions(id) ON DELETE CASCADE,
    CONSTRAINT fk_player_runs_fleet FOREIGN KEY (fleet_id) REFERENCES fleets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    related_player_id BIGINT UNSIGNED NULL,
    planet_id BIGINT UNSIGNED NULL,
    fleet_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(64) NOT NULL,
    severity ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    payload JSON NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_events_player (player_id, occurred_at DESC),
    INDEX idx_events_related (related_player_id),
    CONSTRAINT fk_events_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_events_related FOREIGN KEY (related_player_id) REFERENCES players(id) ON DELETE SET NULL,
    CONSTRAINT fk_events_planet FOREIGN KEY (planet_id) REFERENCES planets(id) ON DELETE SET NULL,
    CONSTRAINT fk_events_fleet FOREIGN KEY (fleet_id) REFERENCES fleets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW users AS
SELECT id, email, password_hash AS password FROM players;
