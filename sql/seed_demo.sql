-- ═══════════════════════════════════════════════════════════
-- LOGE-MOI — Jeu de données de DÉMONSTRATION (facultatif)
-- But : alimenter l'espace agent + le module d'analyse Python.
-- À exécuter APRÈS database.sql et migrations.sql, dans ymmo_db.
--
-- Compte agent de test créé par ce script :
--     email    : demo.agent@logemoi.test
--     password : demo1234
-- (Re-exécutable : nettoie d'abord les données de démo précédentes.)
-- ═══════════════════════════════════════════════════════════

USE ymmo_db;

-- ── Nettoyage idempotent (les FK ON DELETE CASCADE suppriment biens, transactions, favoris) ──
DELETE FROM users    WHERE email IN ('demo.agent@logemoi.test', 'demo.client@logemoi.test');
DELETE FROM agencies WHERE name = 'Agence Démo Loge-Moi';

-- ── Agence de démonstration ──
INSERT INTO agencies (name, city, address, phone, email, description, is_active)
VALUES ('Agence Démo Loge-Moi', 'Paris', '10 rue de la Démo', '0102030405',
        'contact@demo-logemoi.test', 'Agence de démonstration pour les rapports.', 1);
SET @agency_id = LAST_INSERT_ID();

-- ── Utilisateurs de démonstration ──
-- mot de passe « demo1234 » (hash bcrypt compatible password_verify)
INSERT INTO users (name, email, password, role, phone, city, profile_image, is_active)
VALUES ('Agent Démo', 'demo.agent@logemoi.test',
        '$2y$10$JwAMARjzIWBtqWnIT2AxaelKUSqLSA20aABbnTl9UJUdupkV6ZO1.',
        'agent', '0601020304', 'Paris', 'default.png', 1);
SET @agent_id = LAST_INSERT_ID();

INSERT INTO users (name, email, password, role, phone, city, profile_image, is_active)
VALUES ('Client Démo', 'demo.client@logemoi.test',
        '$2y$10$JwAMARjzIWBtqWnIT2AxaelKUSqLSA20aABbnTl9UJUdupkV6ZO1.',
        'client', '0605060708', 'Lyon', 'default.png', 1);
SET @client_id = LAST_INSERT_ID();

-- ── Rattachement de l'agent à l'agence ──
INSERT INTO agency_agents (agency_id, agent_id) VALUES (@agency_id, @agent_id);

-- ── Biens (12) répartis par ville / type / statut / date ──
INSERT INTO properties
  (title, description, price, surface, city, postal_code, property_type, rooms, bathrooms,
   agent_id, agency_id, status, is_featured, views_count, created_at)
VALUES
  ('Appartement lumineux Bastille', 'Beau 3 pièces rénové.', 615000, 64, 'Paris', '75011', 'apartment', 3, 1, @agent_id, @agency_id, 'sold',      1, 482, '2026-01-12 10:00:00'),
  ('Studio cosy République',        'Idéal investissement.', 268000, 24, 'Paris', '75003', 'apartment', 1, 1, @agent_id, @agency_id, 'available', 0, 305, '2026-02-03 10:00:00'),
  ('Maison familiale Croix-Rousse', 'Jardin et garage.',     540000, 130,'Lyon',  '69004', 'house',     5, 2, @agent_id, @agency_id, 'available', 1, 412, '2026-02-20 10:00:00'),
  ('T2 Presqu''île',                'Plein centre.',          295000, 45, 'Lyon',  '69002', 'apartment', 2, 1, @agent_id, @agency_id, 'rented',    0, 198, '2026-03-05 10:00:00'),
  ('Villa vue mer',                 'Terrasse 40 m².',        890000, 160,'Marseille','13008','house',   6, 3, @agent_id, @agency_id, 'available', 1, 521, '2026-03-18 10:00:00'),
  ('Local commercial Vieux-Port',   'Fort passage.',          430000, 95, 'Marseille','13002','commercial',0,1,@agent_id, @agency_id, 'sold',      0, 144, '2026-03-28 10:00:00'),
  ('Appartement neuf Euralille',    'Balcon, parking.',       249000, 58, 'Lille', '59000', 'apartment', 3, 1, @agent_id, @agency_id, 'available', 0, 233, '2026-04-08 10:00:00'),
  ('Maison de ville Wazemmes',      'À rafraîchir.',          312000, 105,'Lille', '59000', 'house',     4, 1, @agent_id, @agency_id, 'sold',      0, 176, '2026-04-22 10:00:00'),
  ('Terrain constructible',         '600 m² viabilisé.',      150000, 600,'Nantes','44000', 'land',      0, 0, @agent_id, @agency_id, 'available', 0, 89,  '2026-05-02 10:00:00'),
  ('Loft Île de Nantes',           'Volumes atypiques.',      398000, 88, 'Nantes','44200', 'apartment', 2, 1, @agent_id, @agency_id, 'available', 1, 367, '2026-05-15 10:00:00'),
  ('Penthouse Part-Dieu',           'Dernier étage.',         720000, 110,'Lyon',  '69003', 'apartment', 4, 2, @agent_id, @agency_id, 'sold',      1, 654, '2026-05-27 10:00:00'),
  ('Duplex Canebière',              'Charme ancien.',         365000, 78, 'Marseille','13001','apartment',3, 1, @agent_id, @agency_id, 'available', 0, 281, '2026-06-06 10:00:00');
SET @p0 = LAST_INSERT_ID();   -- id du 1er bien inséré (les suivants sont @p0+1, @p0+2, …)

-- ── Transactions finalisées réparties sur 6 mois (pour le rapport de ventes + prévision) ──
INSERT INTO transactions (property_id, buyer_id, seller_id, price, transaction_date, status)
VALUES
  (@p0+0,  @client_id, @agent_id, 615000, '2026-01-25 14:00:00', 'completed'),
  (@p0+5,  @client_id, @agent_id, 430000, '2026-02-14 14:00:00', 'completed'),
  (@p0+3,  @client_id, @agent_id, 295000, '2026-02-27 14:00:00', 'completed'),
  (@p0+7,  @client_id, @agent_id, 312000, '2026-03-10 14:00:00', 'completed'),
  (@p0+1,  @client_id, @agent_id, 268000, '2026-03-22 14:00:00', 'completed'),
  (@p0+6,  @client_id, @agent_id, 249000, '2026-04-05 14:00:00', 'completed'),
  (@p0+8,  @client_id, @agent_id, 150000, '2026-04-18 14:00:00', 'completed'),
  (@p0+2,  @client_id, @agent_id, 540000, '2026-04-29 14:00:00', 'completed'),
  (@p0+10, @client_id, @agent_id, 720000, '2026-05-12 14:00:00', 'completed'),
  (@p0+9,  @client_id, @agent_id, 398000, '2026-05-24 14:00:00', 'completed'),
  (@p0+4,  @client_id, @agent_id, 890000, '2026-06-03 14:00:00', 'completed'),
  (@p0+11, @client_id, @agent_id, 365000, '2026-06-12 14:00:00', 'completed');

-- ── Quelques favoris (biens populaires) ──
INSERT INTO favorites (user_id, property_id) VALUES
  (@client_id, @p0+4), (@client_id, @p0+10), (@client_id, @p0+2),
  (@client_id, @p0+9), (@client_id, @p0+0);

-- Récapitulatif
SELECT CONCAT('Démo OK — agence #', @agency_id, ', agent #', @agent_id,
              ', 12 biens, 12 ventes.') AS resultat;
