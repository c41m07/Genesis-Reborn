CREATE TABLE ship_build_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    planet_id INT NOT NULL,
    skey VARCHAR(64) NOT NULL,
    quantity INT NOT NULL,
    ends_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ship_build_queue_active (planet_id, ends_at),
    CONSTRAINT fk_ship_build_queue_planet FOREIGN KEY (planet_id) REFERENCES planets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
