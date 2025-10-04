-- Create planet hangar table to store ships awaiting assignment to fleets.
CREATE TABLE planet_hangar_ships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    planet_id BIGINT UNSIGNED NOT NULL,
    ship_id BIGINT UNSIGNED NOT NULL,
    quantity BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_planet_hangar_ship (planet_id, ship_id),
    INDEX idx_planet_hangar_player (player_id),
    CONSTRAINT fk_planet_hangar_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_planet_hangar_planet FOREIGN KEY (planet_id) REFERENCES planets(id) ON DELETE CASCADE,
    CONSTRAINT fk_planet_hangar_ship FOREIGN KEY (ship_id) REFERENCES ships(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
