-- FOM seed data for users, committee memberships, and sample assignments.
-- Safe to import after setup-admin.php because it only inserts users if the display name does not already exist.
-- If a matching display_name already exists (for example, Yitna Firdyiwek as SuperAdmin), that account is reused.
-- Default password for newly inserted seed users: ChangeMe123!
-- Existing users keep their current password.

START TRANSACTION;

-- 1) Seed users (insert only if the display name does not already exist)
INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Yitna', 'Firdyiwek', 'Yitna Firdyiwek', 'seed.yitna.firdyiwek@example.invalid', 'yitna.firdyiwek', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'superadmin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Yitna Firdyiwek');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Yadwa', 'Yawand-Wossen', 'Yadwa Yawand-Wossen', 'seed.yadwa.yawand-wossen@example.invalid', 'yadwa.yawandwossen', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'sc_admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Yadwa Yawand-Wossen');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Meqdes', 'Mesfin', 'Meqdes Mesfin', 'seed.meqdes.mesfin@example.invalid', 'meqdes.mesfin', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_member'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Meqdes Mesfin');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Wassy', 'Tesfa', 'Wassy Tesfa', 'seed.wassy.tesfa@example.invalid', 'wassy.tesfa', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Wassy Tesfa');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Elfy', 'Getachew', 'Elfy Getachew', 'seed.elfy.getachew@example.invalid', 'elfy.getachew', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Elfy Getachew');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Dagmawi', 'Yimer', 'Dagmawi Yimer', 'seed.dagmawi.yimer@example.invalid', 'dagmawi.yimer', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_member'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Dagmawi Yimer');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Gabriella', 'Ghermandi', 'Gabriella Ghermandi', 'seed.gabriella.ghermandi@example.invalid', 'gabriella.ghermandi', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_member'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Gabriella Ghermandi');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Ejigayehu', 'Demissie', 'Ejigayehu Demissie', 'seed.ejigayehu.demissie@example.invalid', 'ejigayehu.demissie', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_member'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Ejigayehu Demissie');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Temesgen', 'Sahlemichael', 'Temesgen Sahlemichael', 'seed.temesgen.sahlemichael@example.invalid', 'temesgen.sahlemichael', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_member'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Temesgen Sahlemichael');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Gohalem', 'Assefa', 'Gohalem Assefa', 'seed.gohalem.assefa@example.invalid', 'gohalem.assefa', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_member'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Gohalem Assefa');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Eden', 'Debebe', 'Eden Debebe', 'seed.eden.debebe@example.invalid', 'eden.debebe', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_member'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Eden Debebe');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Teklemariam', 'Assefa', 'Teklemariam Assefa', 'seed.teklemariam.assefa@example.invalid', 'teklemariam.assefa', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_member'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Teklemariam Assefa');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Ferdawek', 'Mammo', 'Ferdawek Mammo', 'seed.ferdawek.mammo@example.invalid', 'ferdawek.mammo', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_member'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Ferdawek Mammo');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Laketch', 'Mekonnen', 'Laketch Mekonnen', 'seed.laketch.mekonnen@example.invalid', 'laketch.mekonnen', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_member'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Laketch Mekonnen');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Nikodemos', 'Mekonnen', 'Nikodemos Mekonnen', 'seed.nikodemos.mekonnen@example.invalid', 'nikodemos.mekonnen', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_member'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Nikodemos Mekonnen');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Yenu', 'Bizuneh', 'Yenu Bizuneh', 'seed.yenu.bizuneh@example.invalid', 'yenu.bizuneh', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_member'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Yenu Bizuneh');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Messay', 'Fetene', 'Messay Fetene', 'seed.messay.fetene@example.invalid', 'messay.fetene', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_member'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Messay Fetene');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Rahel', 'Getachew', 'Rahel Getachew', 'seed.rahel.getachew@example.invalid', 'rahel.getachew', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_member'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Rahel Getachew');

INSERT INTO users (first_name, last_name, display_name, email, username, password_hash, role_level)
SELECT 'Abaynesh', 'Asrat', 'Abaynesh Asrat', 'seed.abaynesh.asrat@example.invalid', 'abaynesh.asrat', '$2y$12$.r6I8hvZoudiyt5icWA8gOMns58RuvymIkiyW2Q/UD1/JcJ7YLfYK', 'committee_member'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE display_name = 'Abaynesh Asrat');

-- Ensure expected role levels for the known admin-pattern users.
UPDATE users SET role_level = 'superadmin' WHERE display_name = 'Yitna Firdyiwek';
UPDATE users SET role_level = 'sc_admin' WHERE display_name = 'Yadwa Yawand-Wossen';
UPDATE users SET role_level = 'committee_admin' WHERE display_name IN ('Wassy Tesfa', 'Elfy Getachew') AND role_level <> 'superadmin';

-- 2) Seed committee memberships
-- Steering Committee
INSERT INTO user_committee_memberships (user_id, committee_id, membership_type, is_committee_admin)
SELECT u.id, c.id, 'member', 0
FROM users u CROSS JOIN committees c
WHERE u.display_name = 'Yitna Firdyiwek' AND c.short_code = 'SC'
  AND NOT EXISTS (
    SELECT 1 FROM user_committee_memberships m WHERE m.user_id = u.id AND m.committee_id = c.id
  );

INSERT INTO user_committee_memberships (user_id, committee_id, membership_type, is_committee_admin)
SELECT u.id, c.id, 'member', 0
FROM users u CROSS JOIN committees c
WHERE u.display_name IN ('Yadwa Yawand-Wossen','Meqdes Mesfin','Wassy Tesfa','Elfy Getachew') AND c.short_code = 'SC'
  AND NOT EXISTS (
    SELECT 1 FROM user_committee_memberships m WHERE m.user_id = u.id AND m.committee_id = c.id
  );

-- Communications Committee
INSERT INTO user_committee_memberships (user_id, committee_id, membership_type, is_committee_admin)
SELECT u.id, c.id,
       CASE WHEN u.display_name = 'Wassy Tesfa' THEN 'admin'
            WHEN u.display_name IN ('Dagmawi Yimer','Gabriella Ghermandi','Ejigayehu Demissie','Temesgen Sahlemichael','Gohalem Assefa') THEN 'support'
            ELSE 'member' END,
       CASE WHEN u.display_name = 'Wassy Tesfa' THEN 1 ELSE 0 END
FROM users u CROSS JOIN committees c
WHERE u.display_name IN ('Wassy Tesfa','Yadwa Yawand-Wossen','Dagmawi Yimer','Gabriella Ghermandi','Ejigayehu Demissie','Temesgen Sahlemichael','Gohalem Assefa')
  AND c.short_code = 'CC'
  AND NOT EXISTS (
    SELECT 1 FROM user_committee_memberships m WHERE m.user_id = u.id AND m.committee_id = c.id
  );

-- ARDC
INSERT INTO user_committee_memberships (user_id, committee_id, membership_type, is_committee_admin)
SELECT u.id, c.id,
       CASE WHEN u.display_name = 'Elfy Getachew' THEN 'admin'
            WHEN u.display_name IN ('Eden Debebe','Gohalem Assefa','Teklemariam Assefa','Ferdawek Mammo','Gabriella Ghermandi') THEN 'support'
            ELSE 'member' END,
       CASE WHEN u.display_name = 'Elfy Getachew' THEN 1 ELSE 0 END
FROM users u CROSS JOIN committees c
WHERE u.display_name IN ('Elfy Getachew','Meqdes Mesfin','Eden Debebe','Gohalem Assefa','Teklemariam Assefa','Ferdawek Mammo','Gabriella Ghermandi')
  AND c.short_code = 'ARDC'
  AND NOT EXISTS (
    SELECT 1 FROM user_committee_memberships m WHERE m.user_id = u.id AND m.committee_id = c.id
  );

-- Finance Committee
INSERT INTO user_committee_memberships (user_id, committee_id, membership_type, is_committee_admin)
SELECT u.id, c.id,
       CASE WHEN u.display_name = 'Yitna Firdyiwek' THEN 'admin'
            WHEN u.display_name IN ('Laketch Mekonnen','Nikodemos Mekonnen','Yenu Bizuneh','Messay Fetene','Rahel Getachew','Abaynesh Asrat') THEN 'support'
            ELSE 'member' END,
       CASE WHEN u.display_name = 'Yitna Firdyiwek' THEN 1 ELSE 0 END
FROM users u CROSS JOIN committees c
WHERE u.display_name IN ('Yitna Firdyiwek','Wassy Tesfa','Laketch Mekonnen','Nikodemos Mekonnen','Yenu Bizuneh','Messay Fetene','Rahel Getachew','Abaynesh Asrat')
  AND c.short_code = 'FC'
  AND NOT EXISTS (
    SELECT 1 FROM user_committee_memberships m WHERE m.user_id = u.id AND m.committee_id = c.id
  );

-- 3) Seed sample official assignments (idempotent by title + committee)
INSERT INTO assignments (
  title, short_description, full_description, assigned_committee_id, lead_user_id,
  priority, status, origin_source, work_type, date_assigned, due_date,
  created_by_user_id, updated_by_user_id
)
SELECT
  'Newsletter Special Edition - Yekatit 12',
  'A commemorative publication remembering the horrific events of Yekatit 12 and the subsequent imprisonment and exile of Ethiopians in Asinara and elsewhere.',
  'Seed note: Needs to be handed off to FC for publication.',
  c.id,
  u.id,
  'high',
  'assigned',
  'Seed Data',
  'official',
  '2026-01-31',
  '2026-02-17',
  cb.id,
  cb.id
FROM committees c
JOIN users u ON u.display_name = 'Wassy Tesfa'
JOIN users cb ON cb.display_name = 'Yitna Firdyiwek'
WHERE c.short_code = 'CC'
  AND NOT EXISTS (
    SELECT 1 FROM assignments a WHERE a.title = 'Newsletter Special Edition - Yekatit 12' AND a.assigned_committee_id = c.id
  );

INSERT INTO assignments (
  title, short_description, full_description, assigned_committee_id, lead_user_id,
  priority, status, origin_source, work_type, date_assigned, due_date,
  created_by_user_id, updated_by_user_id
)
SELECT
  'Newsletter Special Edition - Adwa',
  'A commemorative publication honoring the Battle of Adwa in which Emperor Menelik II defeated the Italian army.',
  'Seed note: Needs to be handed off to FC for publication.',
  c.id,
  u.id,
  'high',
  'assigned',
  'Seed Data',
  'official',
  '2026-02-10',
  '2026-02-28',
  cb.id,
  cb.id
FROM committees c
JOIN users u ON u.display_name = 'Wassy Tesfa'
JOIN users cb ON cb.display_name = 'Yitna Firdyiwek'
WHERE c.short_code = 'CC'
  AND NOT EXISTS (
    SELECT 1 FROM assignments a WHERE a.title = 'Newsletter Special Edition - Adwa' AND a.assigned_committee_id = c.id
  );

INSERT INTO assignments (
  title, short_description, full_description, assigned_committee_id, lead_user_id,
  priority, status, origin_source, work_type, date_assigned, due_date,
  created_by_user_id, updated_by_user_id
)
SELECT
  '2025 Taxes',
  'Completion and submission of all 2025 tax preparation.',
  'Seed note: Finance-led tax preparation assignment.',
  c.id,
  u.id,
  'urgent',
  'assigned',
  'Seed Data',
  'official',
  '2026-02-15',
  '2026-03-05',
  cb.id,
  cb.id
FROM committees c
JOIN users u ON u.display_name = 'Yitna Firdyiwek'
JOIN users cb ON cb.display_name = 'Yitna Firdyiwek'
WHERE c.short_code = 'FC'
  AND NOT EXISTS (
    SELECT 1 FROM assignments a WHERE a.title = '2025 Taxes' AND a.assigned_committee_id = c.id
  );

INSERT INTO assignments (
  title, short_description, full_description, assigned_committee_id, lead_user_id,
  priority, status, origin_source, work_type, date_assigned, due_date,
  created_by_user_id, updated_by_user_id
)
SELECT
  '2026 Fundraising',
  'The preparation of a fundraising announcement letter and an editable personalized letter to be sent to all members on the contact list.',
  'Seed note: Needs to be handed off to CC for distribution.',
  c.id,
  u.id,
  'high',
  'assigned',
  'Seed Data',
  'official',
  '2026-03-01',
  '2026-03-20',
  cb.id,
  cb.id
FROM committees c
JOIN users u ON u.display_name = 'Wassy Tesfa'
JOIN users cb ON cb.display_name = 'Yitna Firdyiwek'
WHERE c.short_code = 'FC'
  AND NOT EXISTS (
    SELECT 1 FROM assignments a WHERE a.title = '2026 Fundraising' AND a.assigned_committee_id = c.id
  );

INSERT INTO assignments (
  title, short_description, full_description, assigned_committee_id, lead_user_id,
  priority, status, origin_source, work_type, date_assigned, due_date,
  created_by_user_id, updated_by_user_id
)
SELECT
  'Resolution of term "Prisoners"',
  'Statement declaring FOM''s position on the use of the term "prisoners" (not "deportees") when referring to Ethiopians incarcerated on Asinara.',
  'Seed note: Needs to be handed off to CC for distribution and FC for integration into website.',
  c.id,
  u.id,
  'high',
  'assigned',
  'Seed Data',
  'official',
  '2025-12-15',
  '2026-01-17',
  cb.id,
  cb.id
FROM committees c
JOIN users u ON u.display_name = 'Meqdes Mesfin'
JOIN users cb ON cb.display_name = 'Yitna Firdyiwek'
WHERE c.short_code = 'ARDC'
  AND NOT EXISTS (
    SELECT 1 FROM assignments a WHERE a.title = 'Resolution of term "Prisoners"' AND a.assigned_committee_id = c.id
  );

INSERT INTO assignments (
  title, short_description, full_description, assigned_committee_id, lead_user_id,
  priority, status, origin_source, work_type, date_assigned, due_date,
  created_by_user_id, updated_by_user_id
)
SELECT
  'FOM Archive Guidelines',
  'The FOM Archive Guidelines will set the parameters for working in the FOM Archive.',
  'Seed note: Needs to be handed off to FC for integration into website.',
  c.id,
  u.id,
  'medium',
  'assigned',
  'Seed Data',
  'official',
  '2026-03-10',
  NULL,
  cb.id,
  cb.id
FROM committees c
JOIN users u ON u.display_name = 'Elfy Getachew'
JOIN users cb ON cb.display_name = 'Yitna Firdyiwek'
WHERE c.short_code = 'ARDC'
  AND NOT EXISTS (
    SELECT 1 FROM assignments a WHERE a.title = 'FOM Archive Guidelines' AND a.assigned_committee_id = c.id
  );

-- 4) Seed a few supporting-member links for richer testing
INSERT INTO assignment_supporting_members (assignment_id, user_id, added_by_user_id)
SELECT a.id, u.id, ad.id
FROM assignments a
JOIN users u ON u.display_name = 'Dagmawi Yimer'
JOIN users ad ON ad.display_name = 'Wassy Tesfa'
WHERE a.title = 'Newsletter Special Edition - Yekatit 12'
  AND NOT EXISTS (
    SELECT 1 FROM assignment_supporting_members s WHERE s.assignment_id = a.id AND s.user_id = u.id
  );

INSERT INTO assignment_supporting_members (assignment_id, user_id, added_by_user_id)
SELECT a.id, u.id, ad.id
FROM assignments a
JOIN users u ON u.display_name = 'Gabriella Ghermandi'
JOIN users ad ON ad.display_name = 'Wassy Tesfa'
WHERE a.title = 'Newsletter Special Edition - Yekatit 12'
  AND NOT EXISTS (
    SELECT 1 FROM assignment_supporting_members s WHERE s.assignment_id = a.id AND s.user_id = u.id
  );

INSERT INTO assignment_supporting_members (assignment_id, user_id, added_by_user_id)
SELECT a.id, u.id, ad.id
FROM assignments a
JOIN users u ON u.display_name = 'Laketch Mekonnen'
JOIN users ad ON ad.display_name = 'Yitna Firdyiwek'
WHERE a.title = '2025 Taxes'
  AND NOT EXISTS (
    SELECT 1 FROM assignment_supporting_members s WHERE s.assignment_id = a.id AND s.user_id = u.id
  );

INSERT INTO assignment_supporting_members (assignment_id, user_id, added_by_user_id)
SELECT a.id, u.id, ad.id
FROM assignments a
JOIN users u ON u.display_name = 'Eden Debebe'
JOIN users ad ON ad.display_name = 'Elfy Getachew'
WHERE a.title = 'Resolution of term "Prisoners"'
  AND NOT EXISTS (
    SELECT 1 FROM assignment_supporting_members s WHERE s.assignment_id = a.id AND s.user_id = u.id
  );

-- 5) Seed a few activity-log entries for visibility in the starter
INSERT INTO activity_log (user_id, action_type, target_type, target_id, action_summary)
SELECT u.id, 'seed_import', 'assignment', a.id, CONCAT('Seeded sample assignment: ', a.title)
FROM users u
JOIN assignments a ON a.origin_source = 'Seed Data'
WHERE u.display_name = 'Yitna Firdyiwek'
  AND NOT EXISTS (
    SELECT 1 FROM activity_log al WHERE al.target_type = 'assignment' AND al.target_id = a.id AND al.action_type = 'seed_import'
  );

COMMIT;
