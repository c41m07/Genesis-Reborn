-- 202510201400_update_ship_logistics.sql
-- Adjust ship logistics to store UA/h speeds and hourly fuel consumption

ALTER TABLE ships
    MODIFY base_speed DECIMAL(10,2) NOT NULL DEFAULT 0,
    CHANGE fuel_per_distance fuel_per_hour DECIMAL(12,6) NOT NULL DEFAULT 0;

-- Convert legacy speeds (stored in twentieths of unit speed) into UA/h
UPDATE ships
SET base_speed = ROUND(base_speed * 1.25, 2);

-- Existing fuel values were distance-based and unused, reset after the rename
UPDATE ships
SET fuel_per_hour = 0
WHERE fuel_per_hour IS NULL;
