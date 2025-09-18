-- Génésis Reborn - schéma initial modernisé (InnoDB + FK)

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(180) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE planets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(60) NOT NULL DEFAULT 'Planète mère',
    metal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    crystal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    hydrogen BIGINT UNSIGNED NOT NULL DEFAULT 0,
    energy INT NOT NULL DEFAULT 0,
    prod_metal_per_hour INT NOT NULL DEFAULT 0,
    prod_crystal_per_hour INT NOT NULL DEFAULT 0,
    prod_hydrogen_per_hour INT NOT NULL DEFAULT 0,
    prod_energy_per_hour INT NOT NULL DEFAULT 0,
    last_update TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_planets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE planet_buildings (
    planet_id INT NOT NULL,
    bkey VARCHAR(64) NOT NULL,
    level INT NOT NULL DEFAULT 0,
    PRIMARY KEY (planet_id, bkey),
    CONSTRAINT fk_planet_buildings_planet FOREIGN KEY (planet_id) REFERENCES planets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE planet_research (
    planet_id INT NOT NULL,
    rkey VARCHAR(64) NOT NULL,
    level INT NOT NULL DEFAULT 0,
    PRIMARY KEY (planet_id, rkey),
    CONSTRAINT fk_planet_research_planet FOREIGN KEY (planet_id) REFERENCES planets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE planet_fleet (
    planet_id INT NOT NULL,
    skey VARCHAR(64) NOT NULL,
    quantity BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (planet_id, skey),
    CONSTRAINT fk_planet_fleet_planet FOREIGN KEY (planet_id) REFERENCES planets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE build_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    planet_id INT NOT NULL,
    bkey VARCHAR(64) NOT NULL,
    target_level INT NOT NULL,
    ends_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_queue_active (planet_id, ends_at),
    CONSTRAINT fk_build_queue_planet FOREIGN KEY (planet_id) REFERENCES planets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
