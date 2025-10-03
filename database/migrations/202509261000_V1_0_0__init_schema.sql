-- migrate:up
CREATE TABLE players (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(180) NOT NULL,
    username VARCHAR(60) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    science_spent BIGINT UNSIGNED NOT NULL DEFAULT 0,
    building_spent BIGINT UNSIGNED NOT NULL DEFAULT 0,
    fleet_spent BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    UNIQUE KEY uniq_players_email (email),
    UNIQUE KEY uniq_players_username (username),
    INDEX idx_players_email (email)
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
    prod_metal_per_hour INT NOT NULL DEFAULT 0,
    prod_crystal_per_hour INT NOT NULL DEFAULT 0,
    prod_hydrogen_per_hour INT NOT NULL DEFAULT 0,
    prod_energy_per_hour INT NOT NULL DEFAULT 0,
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
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_player_technology (player_id, technology_id),
    INDEX idx_player_technologies_player (player_id),
    CONSTRAINT fk_player_technologies_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_player_technologies_technology FOREIGN KEY (technology_id) REFERENCES technologies(id) ON DELETE CASCADE
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

CREATE TABLE build_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    planet_id BIGINT UNSIGNED NOT NULL,
    bkey VARCHAR(64) NOT NULL,
    target_level INT UNSIGNED NOT NULL,
    ends_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_build_queue_planet (planet_id),
    INDEX idx_build_queue_player (player_id),
    CONSTRAINT fk_build_queue_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_build_queue_planet FOREIGN KEY (planet_id) REFERENCES planets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE research_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    planet_id BIGINT UNSIGNED NOT NULL,
    rkey VARCHAR(64) NOT NULL,
    target_level INT UNSIGNED NOT NULL,
    ends_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_research_queue_planet (planet_id),
    INDEX idx_research_queue_player (player_id),
    CONSTRAINT fk_research_queue_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_research_queue_planet FOREIGN KEY (planet_id) REFERENCES planets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ship_build_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    planet_id BIGINT UNSIGNED NOT NULL,
    skey VARCHAR(64) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    ends_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ship_queue_planet (planet_id),
    INDEX idx_ship_queue_player (player_id),
    CONSTRAINT fk_ship_queue_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_ship_queue_planet FOREIGN KEY (planet_id) REFERENCES planets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
