-- Queue tables aligned with multiplayer-ready schema
CREATE TABLE IF NOT EXISTS build_queue (
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

CREATE TABLE IF NOT EXISTS research_queue (
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

CREATE TABLE IF NOT EXISTS ship_build_queue (
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
