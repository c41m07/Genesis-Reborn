-- Add production rate columns to planets for legacy service compatibility
ALTER TABLE planets
    ADD COLUMN prod_metal_per_hour INT NOT NULL DEFAULT 0 AFTER hydrogen,
    ADD COLUMN prod_crystal_per_hour INT NOT NULL DEFAULT 0 AFTER prod_metal_per_hour,
    ADD COLUMN prod_hydrogen_per_hour INT NOT NULL DEFAULT 0 AFTER prod_crystal_per_hour,
    ADD COLUMN prod_energy_per_hour INT NOT NULL DEFAULT 0 AFTER prod_hydrogen_per_hour;
