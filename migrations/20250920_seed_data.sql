-- Genesis Reborn - Seed data for solo prototype

-- Resources
INSERT INTO resources (`key`, name, description, unit, is_tradable, is_consumable, created_at)
VALUES
    ('metal', 'Métal', 'Ressource structure de base extraite des mines planétaires.', 'tonnes', 1, 1, NOW()),
    ('crystal', 'Cristal', 'Cristal ionique indispensable aux systèmes électroniques.', 'kg', 1, 1, NOW()),
    ('hydrogen', 'Hydrogène', 'Hydrogène lourd raffiné utilisé comme carburant.', 'kg', 1, 1, NOW()),
    ('energy', 'Énergie', 'Production énergétique nette disponible pour les opérations.', 'MW', 0, 0, NOW());

-- Building definitions
INSERT INTO buildings (`key`, name, description, category, base_cost_metal, base_cost_crystal, base_cost_hydrogen, base_energy_cost, cost_multiplier,
    base_production_metal, base_production_crystal, base_production_hydrogen, base_storage, base_energy_production, base_energy_consumption, unlock_requirements, created_at, updated_at)
VALUES
    ('metal_mine', 'Mine de métal', 'Mine automatisée dédiée à l''extraction du métal brut.', 'production', 60, 15, 0, 0, 1.500, 30, 0, 0, 0, 0, 10, NULL, NOW(), NOW()),
    ('crystal_mine', 'Mine de cristal', 'Extrayez du cristal pur pour vos technologies avancées.', 'production', 48, 24, 0, 0, 1.500, 20, 0, 0, 0, 0, 10, NULL, NOW(), NOW()),
    ('hydrogen_extractor', 'Extracteur d''hydrogène', 'Pompe atmosphérique spécialisée pour l''hydrogène lourd.', 'production', 225, 75, 0, 0, 1.500, 0, 0, 12, 0, 0, 20, NULL, NOW(), NOW()),
    ('solar_plant', 'Centrale solaire', 'Capte la lumière stellaire pour alimenter la colonie.', 'energy', 75, 30, 0, 0, 1.400, 0, 0, 0, 0, 25, 0, NULL, NOW(), NOW()),
    ('fusion_reactor', 'Réacteur à fusion', 'Production énergétique stable via la fusion d''hydrogène.', 'energy', 900, 360, 180, 0, 1.550, 0, 0, 0, 0, 60, 0, JSON_OBJECT('technologies', JSON_OBJECT('energy_technology', 5)), NOW(), NOW()),
    ('storage_depot', 'Entrepôt planétaire', 'Augmente la capacité de stockage globale des ressources.', 'storage', 1000, 400, 0, 0, 1.600, 0, 0, 0, 50000, 0, 5, NULL, NOW(), NOW()),
    ('research_lab', 'Laboratoire de recherche', 'Centre scientifique pour développer de nouvelles technologies.', 'research', 200, 400, 200, 0, 1.700, 0, 0, 0, 0, 0, 30, NULL, NOW(), NOW()),
    ('shipyard', 'Chantier spatial', 'Infrastructures lourdes pour construire les vaisseaux.', 'shipyard', 400, 200, 100, 0, 1.650, 0, 0, 0, 0, 0, 20, JSON_OBJECT('buildings', JSON_OBJECT('research_lab', 1)), NOW(), NOW());

-- Technology definitions
INSERT INTO technologies (`key`, name, description, category, base_cost_metal, base_cost_crystal, base_cost_hydrogen, base_energy_cost, cost_multiplier, base_duration_seconds, unlock_requirements, created_at, updated_at)
VALUES
    ('energy_technology', 'Technologie Énergétique', 'Optimise la production et la consommation d''énergie.', 'research', 0, 400, 200, 0, 1.600, 300, NULL, NOW(), NOW()),
    ('laser_technology', 'Technologie Laser', 'Améliore les systèmes d''armement basés sur les lasers.', 'military', 200, 100, 0, 0, 1.700, 360, JSON_OBJECT('buildings', JSON_OBJECT('research_lab', 1)), NOW(), NOW()),
    ('ion_technology', 'Technologie Ionique', 'Débloque des armes ioniques perçantes.', 'military', 1000, 300, 100, 0, 1.750, 420, JSON_OBJECT('technologies', JSON_OBJECT('laser_technology', 4)), NOW(), NOW()),
    ('plasma_technology', 'Technologie Plasma', 'Manipulation avancée du plasma pour les vaisseaux.', 'military', 2000, 400, 100, 0, 1.800, 540, JSON_OBJECT('technologies', JSON_OBJECT('energy_technology', 4, 'ion_technology', 3)), NOW(), NOW()),
    ('combustion_drive', 'Propulsion à combustion', 'Augmente la vitesse des transporteurs légers.', 'fleet', 400, 600, 0, 0, 1.600, 480, JSON_OBJECT('technologies', JSON_OBJECT('energy_technology', 1)), NOW(), NOW()),
    ('impulse_drive', 'Propulsion à impulsion', 'Réacteurs à impulsion pour chasseurs avancés.', 'fleet', 2000, 2000, 1000, 0, 1.650, 600, JSON_OBJECT('technologies', JSON_OBJECT('combustion_drive', 5)), NOW(), NOW()),
    ('hyperspace_drive', 'Hyperespace', 'Ouvre les couloirs hyperspatiaux pour les capital ships.', 'fleet', 10000, 6000, 2000, 0, 1.700, 720, JSON_OBJECT('technologies', JSON_OBJECT('impulse_drive', 5, 'energy_technology', 5)), NOW(), NOW()),
    ('espionage_technology', 'Technologie d''Espionnage', 'Améliore la collecte de renseignements interstellaires.', 'support', 200, 1000, 0, 0, 1.550, 360, JSON_OBJECT('buildings', JSON_OBJECT('research_lab', 2)), NOW(), NOW()),
    ('computer_technology', 'Technologie Informatique', 'Optimise les réseaux de contrôle et la file de constructions.', 'economy', 200, 400, 0, 0, 1.650, 420, JSON_OBJECT('buildings', JSON_OBJECT('research_lab', 1)), NOW(), NOW()),
    ('astrophysics', 'Astrophysique', 'Permet l''exploration avancée et la colonisation lointaine.', 'support', 4000, 8000, 4000, 0, 1.800, 900, JSON_OBJECT('technologies', JSON_OBJECT('energy_technology', 5, 'computer_technology', 3)), NOW(), NOW());

-- Ship definitions
INSERT INTO ships (`key`, name, description, class, base_cost_metal, base_cost_crystal, base_cost_hydrogen, base_energy_cost, build_time_seconds, base_speed, base_cargo, fuel_per_distance, attack, defense, shield, unlock_requirements, created_at, updated_at)
VALUES
    ('small_cargo', 'Transporteur léger', 'Vaisseau de transport rapide pour les livraisons urgentes.', 'utility', 2000, 2000, 0, 0, 600, 8000, 5000, 0.500000, 5, 10, 10, JSON_OBJECT('buildings', JSON_OBJECT('shipyard', 2), 'technologies', JSON_OBJECT('combustion_drive', 2)), NOW(), NOW()),
    ('large_cargo', 'Transporteur lourd', 'Augmente la capacité de fret des flottes commerciales.', 'utility', 6000, 6000, 0, 0, 900, 6000, 25000, 0.800000, 5, 25, 25, JSON_OBJECT('buildings', JSON_OBJECT('shipyard', 4), 'technologies', JSON_OBJECT('combustion_drive', 6)), NOW(), NOW()),
    ('light_fighter', 'Chasseur léger', 'Unité polyvalente pour la défense de base.', 'fighter', 3000, 1000, 0, 0, 480, 12000, 50, 0.400000, 50, 10, 25, JSON_OBJECT('buildings', JSON_OBJECT('shipyard', 2), 'technologies', JSON_OBJECT('laser_technology', 2)), NOW(), NOW()),
    ('heavy_fighter', 'Chasseur lourd', 'Chasseur renforcé avec systèmes ioniques.', 'fighter', 6000, 4000, 1000, 0, 600, 10000, 100, 0.600000, 150, 25, 50, JSON_OBJECT('technologies', JSON_OBJECT('ion_technology', 3), 'buildings', JSON_OBJECT('shipyard', 4)), NOW(), NOW()),
    ('cruiser', 'Croiseur', 'Vaisseau d''assaut moyen, rapide et bien armé.', 'frigate', 20000, 7000, 2000, 0, 900, 15000, 800, 1.800000, 400, 150, 100, JSON_OBJECT('technologies', JSON_OBJECT('impulse_drive', 4, 'plasma_technology', 1)), NOW(), NOW()),
    ('battleship', 'Cuirassé', 'Puissance de feu massive pour dominer les batailles.', 'capital', 45000, 15000, 5000, 0, 1200, 10000, 1500, 3.000000, 1000, 400, 400, JSON_OBJECT('technologies', JSON_OBJECT('hyperspace_drive', 2, 'plasma_technology', 3)), NOW(), NOW());

-- PvE missions
INSERT INTO pve_missions (`key`, name, description, difficulty, recommended_power, base_duration_seconds, reward_metal, reward_crystal, reward_hydrogen, reward_experience, unlock_requirements, created_at, updated_at)
VALUES
    ('pirate_outpost', 'Avant-poste pirate', 'Éliminez une bande de pirates orbitant une lune minière.', 'easy', 1000, 900, 5000, 2000, 1000, 50, NULL, NOW(), NOW()),
    ('asteroid_belt', 'Ceinture d''astéroïdes', 'Récupérez des minéraux rares au cœur d''une ceinture dense.', 'normal', 2000, 1200, 8000, 5000, 1500, 80, JSON_OBJECT('technologies', JSON_OBJECT('combustion_drive', 3)), NOW(), NOW()),
    ('nebula_patrol', 'Patrouille de nébuleuse', 'Traquez des éclaireurs hostiles dissimulés dans la nébuleuse.', 'normal', 2500, 1800, 6000, 6000, 3000, 120, JSON_OBJECT('technologies', JSON_OBJECT('impulse_drive', 3)), NOW(), NOW()),
    ('derelict_station', 'Station en dérive', 'Explorez une station abandonnée et sécurisez son noyau.', 'hard', 5000, 2400, 12000, 9000, 4000, 180, JSON_OBJECT('technologies', JSON_OBJECT('plasma_technology', 2)), NOW(), NOW()),
    ('ancient_relay', 'Relais antique', 'Réactivez un relais précurseur pour obtenir des données uniques.', 'extreme', 8000, 3600, 20000, 16000, 8000, 300, JSON_OBJECT('technologies', JSON_OBJECT('astrophysics', 2)), NOW(), NOW());

-- Demo player
INSERT INTO players (email, username, password_hash, created_at, updated_at, last_login_at)
VALUES ('demo@genesis.test', 'demo', '$2y$12$e0UmNdog/qnt2fpgT6oGk.bhVZkN6nECqIFznKbkUM3bgzuHzMcpW', NOW(), NOW(), NULL);

-- Home planet for demo player
INSERT INTO planets (player_id, name, galaxy, `system`, `position`, diameter, temperature_min, temperature_max, is_homeworld,
    metal, crystal, hydrogen, energy, metal_capacity, crystal_capacity, hydrogen_capacity, energy_capacity, last_resource_tick, created_at, updated_at)
VALUES
    (LAST_INSERT_ID(), 'Nova Prime', 1, 7, 8, 12800, -10, 45, 1, 12000, 6000, 3000, 80, 120000, 90000, 60000, 150, NOW(), NOW(), NOW());

-- Ensure building levels for the demo colony
INSERT INTO planet_buildings (player_id, planet_id, building_id, level, created_at, updated_at)
SELECT p.player_id, p.planet_id, b.id, lvl.level, NOW(), NOW()
FROM (
    SELECT id AS planet_id, player_id FROM planets WHERE name = 'Nova Prime'
) AS p
JOIN (
    SELECT 'metal_mine' AS bkey, 6 AS level UNION ALL
    SELECT 'crystal_mine', 5 UNION ALL
    SELECT 'hydrogen_extractor', 3 UNION ALL
    SELECT 'solar_plant', 7 UNION ALL
    SELECT 'fusion_reactor', 3 UNION ALL
    SELECT 'storage_depot', 3 UNION ALL
    SELECT 'research_lab', 2 UNION ALL
    SELECT 'shipyard', 2
) AS lvl ON 1=1
JOIN buildings b ON b.`key` = lvl.bkey;

-- Initialise known technologies for the demo player at level 0
INSERT INTO player_technologies (player_id, technology_id, level, created_at, updated_at)
SELECT pl.id AS player_id, tech.id, 0 AS level, NOW(), NOW()
FROM players pl
JOIN technologies tech ON 1=1
WHERE pl.username = 'demo';

