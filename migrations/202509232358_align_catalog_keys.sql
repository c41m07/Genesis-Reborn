-- Align catalog tables with updated game configuration
START TRANSACTION;

-- Upsert technologies to ensure all expected research entries exist
INSERT INTO technologies (`key`, name, description, category, base_cost_metal, base_cost_crystal, base_cost_hydrogen, base_energy_cost, cost_multiplier, base_duration_seconds, unlock_requirements, created_at, updated_at) VALUES
    ('propulsion_basic', 'Propulsion spatiale', 'Maîtrise des moteurs ioniques et de la navigation subluminique.', 'fleet', 120, 80, 40, 0, 1.650, 50, '{"buildings":{"research_lab":1}}', NOW(), NOW()),
    ('life_support', 'Systèmes de survie', 'Contrôle environnemental, recyclage et maintien des équipages.', 'research', 90, 110, 40, 0, 1.600, 55, '{"buildings":{"research_lab":1}}', NOW(), NOW()),
    ('miniaturisation', 'Miniaturisation des systèmes', 'Réduction des composants pour optimiser la place à bord.', 'research', 140, 150, 60, 0, 1.650, 60, '{"technologies":{"life_support":2},"buildings":{"research_lab":1}}', NOW(), NOW()),
    ('armor_basic', 'Blindage de base', 'Plaques composites et renforts structurels élémentaires.', 'military', 160, 120, 40, 0, 1.650, 65, '{"technologies":{"miniaturisation":2},"buildings":{"research_lab":1}}', NOW(), NOW()),
    ('weapon_light', 'Armes légères', 'Lasers, canons automatiques et missiles à courte portée.', 'military', 150, 170, 60, 0, 1.650, 70, '{"technologies":{"miniaturisation":2},"buildings":{"research_lab":1}}', NOW(), NOW()),
    ('radar_basic', 'Détection basique', 'Radars tactiques et balayage à courte portée.', 'support', 110, 160, 60, 0, 1.600, 65, '{"technologies":{"miniaturisation":1},"buildings":{"research_lab":1}}', NOW(), NOW()),
    ('armor_enhanced', 'Blindage renforcé', 'Alliages avancés et champs de confinement structurels.', 'military', 260, 210, 90, 0, 1.700, 90, '{"technologies":{"armor_basic":5},"buildings":{"research_lab":2}}', NOW(), NOW()),
    ('shield_energy', 'Boucliers énergétiques', 'Générateurs de champs déflecteurs et boucliers phaseurs.', 'military', 240, 260, 140, 0, 1.720, 95, '{"technologies":{"armor_enhanced":3,"weapon_light":4},"buildings":{"research_lab":2}}', NOW(), NOW()),
    ('weapon_medium', 'Armes moyennes', 'Torpilles lourdes et batteries laser à longue portée.', 'military', 260, 280, 160, 0, 1.700, 100, '{"technologies":{"weapon_light":6},"buildings":{"research_lab":2}}', NOW(), NOW()),
    ('logistics', 'Logistique spatiale', 'Ravitaillement interplanétaire et coordination des convois.', 'research', 220, 200, 140, 0, 1.680, 90, '{"technologies":{"propulsion_basic":4,"life_support":4},"buildings":{"research_lab":2}}', NOW(), NOW()),
    ('engineering_heavy', 'Ingénierie lourde', 'Conception de structures massives et docks orbitaux.', 'research', 320, 260, 160, 0, 1.720, 110, '{"technologies":{"logistics":3},"buildings":{"research_lab":2}}', NOW(), NOW()),
    ('reactor_advanced', 'Réacteurs avancés', 'Réacteurs à fusion haute densité et confinement magnétique.', 'fleet', 380, 340, 200, 0, 1.750, 130, '{"technologies":{"propulsion_basic":5,"engineering_heavy":2},"buildings":{"research_lab":3}}', NOW(), NOW()),
    ('weapon_heavy', 'Armes lourdes', 'Railguns, canons à plasma et plates-formes multi-batteries.', 'military', 420, 380, 260, 0, 1.750, 140, '{"technologies":{"weapon_medium":5,"reactor_advanced":2},"buildings":{"research_lab":3}}', NOW(), NOW()),
    ('tactical_coordination', 'Coordination tactique', 'IA stratégique et communications quantiques longue portée.', 'support', 360, 420, 200, 0, 1.700, 135, '{"technologies":{"radar_basic":5,"logistics":5},"buildings":{"research_lab":3}}', NOW(), NOW()),
    ('superstructures', 'Superstructures', 'Coques modulaires géantes et architecture segmentée.', 'research', 520, 460, 260, 0, 1.780, 150, '{"technologies":{"engineering_heavy":5},"buildings":{"research_lab":4}}', NOW(), NOW()),
    ('reactor_antimatter', 'Réacteurs à antimatière', 'Confinement annulaire et catalyseurs d’antimatière.', 'fleet', 620, 540, 340, 0, 1.800, 170, '{"technologies":{"reactor_advanced":6,"superstructures":3},"buildings":{"research_lab":4}}', NOW(), NOW()),
    ('weapon_capital', 'Armement capital', 'Super-canons, bombardement orbital et charges de siège.', 'military', 680, 620, 360, 0, 1.820, 180, '{"technologies":{"weapon_heavy":6,"superstructures":4},"buildings":{"research_lab":4}}', NOW(), NOW()),
    ('defense_multilayer', 'Défense multi-couches', 'Combinaison de boucliers adaptatifs et d’armures composites.', 'military', 640, 560, 340, 0, 1.780, 175, '{"technologies":{"armor_enhanced":6,"shield_energy":5},"buildings":{"research_lab":4}}', NOW(), NOW()),
    ('stellar_shipyards', 'Chantiers stellaires', 'Assemblage orbital massif et infrastructures d’amarrage.', 'economy', 720, 660, 420, 0, 1.800, 185, '{"technologies":{"superstructures":5,"engineering_heavy":7},"buildings":{"research_lab":4}}', NOW(), NOW()),
    ('autonomous_hangars', 'Hangars autonomes', 'Réseaux de lancement automatisés pour escadrilles de chasseurs.', 'economy', 760, 680, 440, 0, 1.800, 190, '{"technologies":{"stellar_shipyards":2,"tactical_coordination":4},"buildings":{"research_lab":4}}', NOW(), NOW()),
    ('super_weapon', 'Armes de destruction massive', 'Rayons planétaires, bombardements orbitaux et super-armes.', 'military', 880, 760, 520, 0, 1.850, 210, '{"technologies":{"weapon_capital":4,"reactor_antimatter":4},"buildings":{"research_lab":5}}', NOW(), NOW()),
    ('fleet_networks', 'Réseaux stratégiques de flotte', 'Commandement interstellaire et coordination des armadas.', 'support', 820, 780, 480, 0, 1.820, 205, '{"technologies":{"tactical_coordination":6,"autonomous_hangars":3},"buildings":{"research_lab":5}}', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    category = VALUES(category),
    base_cost_metal = VALUES(base_cost_metal),
    base_cost_crystal = VALUES(base_cost_crystal),
    base_cost_hydrogen = VALUES(base_cost_hydrogen),
    base_energy_cost = VALUES(base_energy_cost),
    cost_multiplier = VALUES(cost_multiplier),
    base_duration_seconds = VALUES(base_duration_seconds),
    unlock_requirements = VALUES(unlock_requirements),
    updated_at = VALUES(updated_at);

DROP TEMPORARY TABLE IF EXISTS tmp_technology_key_map;
CREATE TEMPORARY TABLE tmp_technology_key_map (
    old_key VARCHAR(64) PRIMARY KEY,
    new_key VARCHAR(64) NOT NULL
);
INSERT INTO tmp_technology_key_map (old_key, new_key) VALUES
    ('energy_technology', 'life_support'),
    ('laser_technology', 'weapon_light'),
    ('ion_technology', 'weapon_medium'),
    ('plasma_technology', 'weapon_heavy'),
    ('combustion_drive', 'propulsion_basic'),
    ('impulse_drive', 'reactor_advanced'),
    ('hyperspace_drive', 'reactor_antimatter'),
    ('espionage_technology', 'radar_basic'),
    ('computer_technology', 'logistics'),
    ('astrophysics', 'fleet_networks');

UPDATE research_queue rq
JOIN tmp_technology_key_map map ON rq.rkey = map.old_key
SET rq.rkey = map.new_key;

INSERT INTO player_technologies (player_id, technology_id, level, created_at, updated_at)
SELECT pt.player_id, tnew.id, pt.level, pt.created_at, NOW()
FROM player_technologies pt
JOIN technologies told ON pt.technology_id = told.id
JOIN tmp_technology_key_map map ON told.`key` = map.old_key
JOIN technologies tnew ON tnew.`key` = map.new_key
ON DUPLICATE KEY UPDATE level = GREATEST(player_technologies.level, VALUES(level)), updated_at = VALUES(updated_at);

DELETE t FROM technologies t
JOIN tmp_technology_key_map map ON t.`key` = map.old_key;

INSERT INTO player_technologies (player_id, technology_id, level, created_at, updated_at)
SELECT p.id, t.id, 0, NOW(), NOW()
FROM players p
CROSS JOIN technologies t
LEFT JOIN player_technologies pt ON pt.player_id = p.id AND pt.technology_id = t.id
WHERE pt.id IS NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_technology_key_map;

-- Upsert buildings to match configuration keys
INSERT INTO buildings (`key`, name, description, category, base_cost_metal, base_cost_crystal, base_cost_hydrogen, base_energy_cost, cost_multiplier, base_production_metal, base_production_crystal, base_production_hydrogen, base_storage, base_energy_production, base_energy_consumption, unlock_requirements, created_at, updated_at) VALUES
    ('metal_mine', 'Mine de métal', 'Mine automatisée dédiée à l''extraction du métal brut.', 'production', 60, 15, 0, 0, 1.600, 100, 0, 0, 0, 0, 10, NULL, NOW(), NOW()),
    ('crystal_mine', 'Mine de cristal', 'Extrayez du cristal pur pour vos technologies avancées.', 'production', 48, 24, 0, 0, 1.600, 0, 50, 0, 0, 0, 15, NULL, NOW(), NOW()),
    ('solar_plant', 'Centrale solaire', 'Capte la lumière stellaire pour alimenter la colonie.', 'energy', 120, 60, 0, 0, 1.600, 0, 0, 0, 0, 100, 0, NULL, NOW(), NOW()),
    ('fusion_reactor', 'Réacteur à fusion', 'Production énergétique stable via la fusion d''hydrogène.', 'energy', 900, 360, 180, 0, 1.550, 0, 0, 0, 0, 320, 0, '{"buildings":{"solar_plant":5,"hydrogen_plant":3},"technologies":{"reactor_advanced":2}}', NOW(), NOW()),
    ('antimatter_reactor', 'Réacteur à antimatière', 'Confinement annulaire alimenté en antimatière pour une puissance colossale.', 'energy', 3200, 2200, 1200, 0, 1.550, 0, 0, 0, 0, 800, 0, '{"buildings":{"fusion_reactor":5,"research_lab":6},"technologies":{"reactor_antimatter":1}}', NOW(), NOW()),
    ('hydrogen_plant', 'Générateur d’hydrogène', 'Générateur atmosphérique spécialisé dans l''hydrogène lourd.', 'production', 150, 100, 0, 0, 1.600, 0, 0, 30, 0, 0, 20, '{"buildings":{"solar_plant":1}}', NOW(), NOW()),
    ('storage_depot', 'Entrepôt planétaire', 'Augmente la capacité de stockage globale des ressources.', 'storage', 1000, 400, 0, 0, 1.600, 0, 0, 0, 50000, 0, 0, '{"buildings":{"metal_mine":4,"crystal_mine":3},"technologies":{"logistics":2}}', NOW(), NOW()),
    ('research_lab', 'Laboratoire Helios', 'Centre scientifique pour développer de nouvelles technologies.', 'research', 200, 320, 80, 0, 1.650, 0, 0, 0, 0, 0, 22, '{"buildings":{"solar_plant":1,"crystal_mine":1}}', NOW(), NOW()),
    ('shipyard', 'Chantier spatial Asterion', 'Infrastructures lourdes pour construire les vaisseaux.', 'shipyard', 420, 260, 120, 0, 1.700, 0, 0, 0, 0, 0, 35, '{"buildings":{"research_lab":1},"technologies":{"engineering_heavy":1}}', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    category = VALUES(category),
    base_cost_metal = VALUES(base_cost_metal),
    base_cost_crystal = VALUES(base_cost_crystal),
    base_cost_hydrogen = VALUES(base_cost_hydrogen),
    base_energy_cost = VALUES(base_energy_cost),
    cost_multiplier = VALUES(cost_multiplier),
    base_production_metal = VALUES(base_production_metal),
    base_production_crystal = VALUES(base_production_crystal),
    base_production_hydrogen = VALUES(base_production_hydrogen),
    base_storage = VALUES(base_storage),
    base_energy_production = VALUES(base_energy_production),
    base_energy_consumption = VALUES(base_energy_consumption),
    unlock_requirements = VALUES(unlock_requirements),
    updated_at = VALUES(updated_at);

DROP TEMPORARY TABLE IF EXISTS tmp_building_key_map;
CREATE TEMPORARY TABLE tmp_building_key_map (
    old_key VARCHAR(64) PRIMARY KEY,
    new_key VARCHAR(64) NOT NULL
);
INSERT INTO tmp_building_key_map (old_key, new_key) VALUES ('hydrogen_extractor', 'hydrogen_plant');

UPDATE build_queue b
JOIN tmp_building_key_map map ON b.bkey = map.old_key
SET b.bkey = map.new_key;

UPDATE planet_buildings pb
JOIN buildings bold ON pb.building_id = bold.id
JOIN tmp_building_key_map map ON bold.`key` = map.old_key
JOIN buildings bnew ON bnew.`key` = map.new_key
SET pb.building_id = bnew.id;

DELETE b FROM buildings b
JOIN tmp_building_key_map map ON b.`key` = map.old_key;

DROP TEMPORARY TABLE IF EXISTS tmp_building_key_map;

-- Upsert ships to keep database aligned with ship catalog
INSERT INTO ships (`key`, name, description, class, base_cost_metal, base_cost_crystal, base_cost_hydrogen, base_energy_cost, build_time_seconds, base_speed, base_cargo, fuel_per_distance, attack, defense, shield, unlock_requirements, created_at, updated_at) VALUES
    ('fighter', 'Ailes Lyrae', 'Véhicule agile conçu pour intercepter rapidement les menaces proches et saturer la défense adverse.', 'fighter', 220, 90, 45, 0, 60, 18, 0, 0.000000, 6, 3, 3, '{"technologies":{"propulsion_basic":1,"life_support":2,"miniaturisation":2,"weapon_light":2,"radar_basic":1}}', NOW(), NOW()),
    ('bomber', 'Maraudeur Obsidien', 'Transporte un arsenal de torpilles magnétiques capables de briser le blindage des cibles capitales.', 'fighter', 420, 260, 120, 0, 140, 12, 0, 0.000000, 15, 6, 6, '{"technologies":{"life_support":2,"miniaturisation":2,"armor_basic":2,"weapon_light":6,"weapon_medium":1}}', NOW(), NOW()),
    ('interceptor', 'Éclair Stentor', 'Priorité à la vitesse et à la précision pour contrer les chasseurs ennemis et les éclaireurs.', 'fighter', 280, 160, 90, 0, 90, 24, 0, 0.000000, 7, 4, 4, '{"technologies":{"propulsion_basic":2,"life_support":3,"miniaturisation":3,"weapon_light":3,"radar_basic":3}}', NOW(), NOW()),
    ('heavy_fighter', 'Griffon Halcyon', 'Combine l’armement d’un bombardier à la mobilité d’un intercepteur pour dominer la ligne de front.', 'fighter', 520, 360, 160, 0, 180, 16, 0, 0.000000, 18, 10, 10, '{"technologies":{"propulsion_basic":3,"life_support":3,"miniaturisation":4,"weapon_light":4,"radar_basic":3,"armor_basic":3}}', NOW(), NOW()),
    ('corvette', 'Corvette Vigilum', 'Assure la reconnaissance, la couverture anti-chasseur et la projection rapide de puissance légère.', 'frigate', 950, 520, 220, 0, 300, 14, 0, 0.000000, 28, 22, 22, '{"technologies":{"propulsion_basic":4,"life_support":4,"logistics":1,"armor_basic":5,"armor_enhanced":1,"weapon_light":6,"weapon_medium":3,"radar_basic":3}}', NOW(), NOW()),
    ('gunship', 'Havoc Aster', 'Embarque des canons à plasma lourds pour perforer les coques des navires moyens.', 'frigate', 1250, 720, 260, 0, 420, 12, 0, 0.000000, 40, 28, 28, '{"technologies":{"armor_basic":5,"armor_enhanced":1,"weapon_light":6,"weapon_medium":2,"radar_basic":3}}', NOW(), NOW()),
    ('frigate', 'Frégate Solstice', 'Grille de missiles défensifs et batteries traçantes pour protéger les unités capitales.', 'frigate', 2200, 1350, 480, 0, 600, 10, 0, 0.000000, 55, 48, 48, '{"technologies":{"armor_basic":5,"armor_enhanced":3,"weapon_light":4,"shield_energy":1,"weapon_medium":4,"propulsion_basic":4,"life_support":4,"logistics":2,"tactical_coordination":1}}', NOW(), NOW()),
    ('destroyer', 'Destroyer Fulguris', 'Longue coque bardée de canons rails optimisés pour démanteler les escorteurs ennemis.', 'capital', 3600, 2400, 800, 0, 900, 9, 0, 0.000000, 80, 60, 60, '{"technologies":{"engineering_heavy":2,"propulsion_basic":5,"reactor_advanced":2,"weapon_light":6,"weapon_medium":5,"weapon_heavy":1,"tactical_coordination":3}}', NOW(), NOW()),
    ('light_cruiser', 'Croiseur Lumen', 'Équilibre propulsion, blindage et puissance de feu pour mener des flottes rapides.', 'capital', 5200, 3600, 1200, 0, 1200, 8, 0, 0.000000, 110, 95, 95, '{"technologies":{"engineering_heavy":3,"armor_basic":6,"armor_enhanced":3,"weapon_light":4,"shield_energy":3,"reactor_advanced":3,"weapon_medium":5,"weapon_heavy":2,"tactical_coordination":3}}', NOW(), NOW()),
    ('heavy_cruiser', 'Croiseur Axiom', 'Blindage renforcé, artillerie lourde et systèmes de projection pour les batailles d’attrition.', 'capital', 8800, 6200, 1800, 0, 1800, 7, 0, 0.000000, 150, 140, 140, '{"technologies":{"engineering_heavy":5,"superstructures":1,"reactor_advanced":4,"weapon_medium":5,"weapon_heavy":3,"armor_basic":6,"armor_enhanced":6,"weapon_light":5,"shield_energy":5,"defense_multilayer":2,"tactical_coordination":4}}', NOW(), NOW()),
    ('battleship', 'Cuirassé Helior', 'Boucliers étagés et batteries orbitales massives pour absorber et infliger d’énormes dégâts.', 'capital', 15000, 9800, 2600, 0, 2700, 6, 0, 0.000000, 220, 260, 260, '{"technologies":{"engineering_heavy":5,"superstructures":4,"reactor_advanced":6,"reactor_antimatter":1,"weapon_medium":6,"weapon_heavy":6,"weapon_capital":1,"armor_basic":6,"armor_enhanced":6,"weapon_light":5,"shield_energy":5,"defense_multilayer":3}}', NOW(), NOW()),
    ('battlecruiser', 'Arbiter Solaris', 'Compromis entre vitesse et artillerie lourde pour manœuvrer autour des cuirassés ennemis.', 'capital', 18800, 12600, 3200, 0, 3300, 7, 0, 0.000000, 240, 210, 210, '{"technologies":{"engineering_heavy":5,"superstructures":2,"reactor_advanced":5,"weapon_medium":5,"weapon_heavy":4,"tactical_coordination":5}}', NOW(), NOW()),
    ('dreadnought', 'Dreadnought Orichal', 'Pièce maîtresse d’une armada, concentrant le feu de multiples batteries super-lourdes.', 'capital', 26000, 18000, 4200, 0, 4200, 5, 0, 0.000000, 320, 320, 320, '{"technologies":{"engineering_heavy":7,"superstructures":4,"reactor_advanced":6,"reactor_antimatter":2,"weapon_heavy":6,"weapon_capital":2,"armor_enhanced":6,"shield_energy":5,"defense_multilayer":4,"tactical_coordination":6,"stellar_shipyards":2}}', NOW(), NOW()),
    ('carrier', 'Porte-chasseurs Aegira', 'Hangars magnétiques pouvant lancer simultanément des vagues complètes d’escadrilles.', 'capital', 22000, 21000, 5200, 0, 4500, 5, 10000, 0.000000, 180, 300, 300, '{"technologies":{"engineering_heavy":7,"superstructures":5,"stellar_shipyards":2,"tactical_coordination":4,"autonomous_hangars":3,"reactor_advanced":5,"weapon_medium":5,"shield_energy":5,"armor_enhanced":6,"fleet_networks":2}}', NOW(), NOW()),
    ('heavy_transport', 'Transporteur Atlas', 'Plate-forme d’atterrissage blindée pour déployer troupes, véhicules ou modules orbitaux.', 'utility', 12800, 9000, 6400, 0, 3000, 6, 50000, 0.000000, 70, 150, 150, '{"technologies":{"propulsion_basic":4,"life_support":4,"logistics":3,"engineering_heavy":2,"armor_basic":3,"weapon_light":3,"tactical_coordination":1}}', NOW(), NOW()),
    ('command_ship', 'Vaisseau de commandement Novarch', 'Coordinateur de flotte doté de suites tactiques avancées et de systèmes de communications quantiques.', 'utility', 24000, 20000, 7000, 0, 4800, 5, 5000, 0.000000, 160, 340, 340, '{"technologies":{"tactical_coordination":5,"fleet_networks":2,"autonomous_hangars":2,"shield_energy":4,"armor_enhanced":4,"weapon_medium":4}}', NOW(), NOW()),
    ('super_battleship', 'Super-cuirassé Empyrium', 'Vaisseau emblématique, quasiment indestructible, arborant une armure multi-couches et un armement d’anéantissement.', 'capital', 54000, 42000, 12000, 0, 9000, 4, 0, 0.000000, 420, 520, 520, '{"technologies":{"engineering_heavy":7,"superstructures":5,"stellar_shipyards":3,"reactor_advanced":6,"reactor_antimatter":3,"weapon_heavy":6,"weapon_capital":3,"armor_enhanced":6,"shield_energy":5,"defense_multilayer":5}}', NOW(), NOW()),
    ('siege_breaker', 'Cuirassé de siège Ragnarok', 'Optimisé pour raser les fortifications planétaires grâce à des canons gravitoniques massifs.', 'capital', 62000, 46000, 15000, 0, 9600, 4, 0, 0.000000, 480, 480, 480, '{"technologies":{"engineering_heavy":7,"superstructures":5,"stellar_shipyards":3,"weapon_capital":3,"super_weapon":1,"reactor_antimatter":3,"defense_multilayer":4,"shield_energy":5}}', NOW(), NOW()),
    ('super_dreadnought', 'Super-dreadnought Nemesis', 'Capable de déployer un canon stellaire, ce navire dicte l’issue de n’importe quelle bataille.', 'capital', 72000, 56000, 18000, 0, 10800, 3, 0, 0.000000, 560, 580, 580, '{"technologies":{"engineering_heavy":7,"superstructures":5,"stellar_shipyards":3,"reactor_advanced":6,"reactor_antimatter":4,"weapon_heavy":6,"weapon_capital":4,"super_weapon":1,"armor_enhanced":6,"shield_energy":5,"defense_multilayer":5,"fleet_networks":3}}', NOW(), NOW()),
    ('battle_station', 'Citadelle Astra', 'Forteresse orbitale dotée d’un anneau de super-lasers et de plates-formes autonomes.', 'capital', 95000, 82000, 25000, 0, 14400, 0, 0, 0.000000, 680, 760, 760, '{"technologies":{"engineering_heavy":7,"superstructures":6,"stellar_shipyards":4,"armor_enhanced":6,"shield_energy":5,"defense_multilayer":5,"weapon_capital":3,"super_weapon":1,"fleet_networks":3}}', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    class = VALUES(class),
    base_cost_metal = VALUES(base_cost_metal),
    base_cost_crystal = VALUES(base_cost_crystal),
    base_cost_hydrogen = VALUES(base_cost_hydrogen),
    base_energy_cost = VALUES(base_energy_cost),
    build_time_seconds = VALUES(build_time_seconds),
    base_speed = VALUES(base_speed),
    base_cargo = VALUES(base_cargo),
    fuel_per_distance = VALUES(fuel_per_distance),
    attack = VALUES(attack),
    defense = VALUES(defense),
    shield = VALUES(shield),
    unlock_requirements = VALUES(unlock_requirements),
    updated_at = VALUES(updated_at);

DROP TEMPORARY TABLE IF EXISTS tmp_ship_key_map;
CREATE TEMPORARY TABLE tmp_ship_key_map (
    old_key VARCHAR(64) PRIMARY KEY,
    new_key VARCHAR(64) NOT NULL
);
INSERT INTO tmp_ship_key_map (old_key, new_key) VALUES
    ('small_cargo', 'heavy_transport'),
    ('large_cargo', 'heavy_transport'),
    ('light_fighter', 'fighter'),
    ('heavy_fighter', 'heavy_fighter'),
    ('cruiser', 'light_cruiser'),
    ('battleship', 'battleship');

UPDATE ship_build_queue sbq
JOIN tmp_ship_key_map map ON sbq.skey = map.old_key
SET sbq.skey = map.new_key;

INSERT INTO fleet_ships (player_id, fleet_id, ship_id, quantity, created_at, updated_at)
SELECT fs.player_id, fs.fleet_id, snew.id, SUM(fs.quantity), NOW(), NOW()
FROM fleet_ships fs
JOIN ships sold ON sold.id = fs.ship_id
JOIN tmp_ship_key_map map ON sold.`key` = map.old_key
JOIN ships snew ON snew.`key` = map.new_key
GROUP BY fs.player_id, fs.fleet_id, snew.id
ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = VALUES(updated_at);

DELETE fs FROM fleet_ships fs
JOIN ships sold ON sold.id = fs.ship_id
JOIN tmp_ship_key_map map ON sold.`key` = map.old_key;

DELETE s FROM ships s
JOIN tmp_ship_key_map map ON s.`key` = map.old_key;

DROP TEMPORARY TABLE IF EXISTS tmp_ship_key_map;

COMMIT;
