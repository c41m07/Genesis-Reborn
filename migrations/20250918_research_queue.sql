CREATE TABLE research_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    planet_id INT NOT NULL,
    rkey VARCHAR(64) NOT NULL,
    target_level INT NOT NULL,
    ends_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_research_queue_active (planet_id, ends_at),
    CONSTRAINT fk_research_queue_planet FOREIGN KEY (planet_id) REFERENCES planets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
