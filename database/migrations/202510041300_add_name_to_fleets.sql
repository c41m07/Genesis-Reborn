ALTER TABLE fleets
    ADD COLUMN name VARCHAR(100) NULL AFTER destination_planet_id;
