-- 202510231200_update_ship_cargo.sql
-- Align ship cargo capacities with role-based minimums

UPDATE ships
SET base_cargo = CASE
        WHEN `key` = 'heavy_transport' THEN defense * 90
        WHEN `key` IN ('super_battleship', 'siege_breaker', 'super_dreadnought') THEN 0
        ELSE defense * 20
    END
WHERE `key` IN (
    'fighter','bomber','interceptor','heavy_fighter','corvette','gunship','frigate',
    'destroyer','light_cruiser','heavy_cruiser','battleship','battlecruiser','dreadnought',
    'carrier','heavy_transport','command_ship','super_battleship','siege_breaker',
    'super_dreadnought','battle_station'
);
