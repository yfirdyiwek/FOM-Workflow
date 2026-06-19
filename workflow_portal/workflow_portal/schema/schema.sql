-- Workflow Portal PHP Starter Schema
-- Import this file first in phpMyAdmin.

CREATE TABLE IF NOT EXISTS committees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  short_code VARCHAR(20) NOT NULL UNIQUE,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO committees (name, short_code, description)
VALUES
  ('Steering Committee', 'SC', 'Committee handling governance and work routing.'),
  ('Communications Committee', 'CC', 'Committee handling communications and outreach.'),
  ('Archive, Research, and Documentation Committee', 'ARDC', 'Committee handling archival and research work.'),
  ('Finance Committee', 'FC', 'Committee handling finance and website posting responsibilities.')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  display_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role_level ENUM('superadmin','sc_admin','committee_admin','committee_member','read_only') NOT NULL DEFAULT 'committee_member',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_committee_memberships (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  committee_id INT UNSIGNED NOT NULL,
  membership_type VARCHAR(50) NOT NULL DEFAULT 'member',
  is_committee_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_membership_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_membership_committee FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_user_committee (user_id, committee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  short_description VARCHAR(255) NULL,
  full_description TEXT NULL,
  assigned_committee_id INT UNSIGNED NOT NULL,
  lead_user_id INT UNSIGNED NULL,
  priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  status ENUM('approved','assigned','in_progress','waiting_blocked','ready_for_review','completed','archived','pending_handoff','in_receiving_review','returned_for_revision','resubmitted') NOT NULL DEFAULT 'assigned',
  origin_source VARCHAR(255) NULL,
  work_type ENUM('official','handoff','revision') NOT NULL DEFAULT 'official',
  parent_assignment_id INT UNSIGNED NULL,
  source_assignment_id INT UNSIGNED NULL,
  date_assigned DATE NULL,
  due_date DATE NULL,
  completion_date DATE NULL,
  created_by_user_id INT UNSIGNED NULL,
  updated_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_assignment_committee FOREIGN KEY (assigned_committee_id) REFERENCES committees(id),
  CONSTRAINT fk_assignment_lead FOREIGN KEY (lead_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_assignment_parent FOREIGN KEY (parent_assignment_id) REFERENCES assignments(id) ON DELETE SET NULL,
  CONSTRAINT fk_assignment_source FOREIGN KEY (source_assignment_id) REFERENCES assignments(id) ON DELETE SET NULL,
  CONSTRAINT fk_assignment_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_assignment_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignment_supporting_members (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  added_by_user_id INT UNSIGNED NULL,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_support_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_support_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_support_added_by FOREIGN KEY (added_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_assignment_support_user (assignment_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignment_subtasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  assigned_user_id INT UNSIGNED NULL,
  created_by_user_id INT UNSIGNED NULL,
  priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  status ENUM('not_started','in_progress','waiting','completed') NOT NULL DEFAULT 'not_started',
  due_date DATE NULL,
  completion_date DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_subtask_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_subtask_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_subtask_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignment_updates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  update_text TEXT NOT NULL,
  visibility_level VARCHAR(50) NOT NULL DEFAULT 'internal',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_update_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_update_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignment_handoffs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_assignment_id INT UNSIGNED NOT NULL,
  receiving_assignment_id INT UNSIGNED NULL,
  source_committee_id INT UNSIGNED NOT NULL,
  receiving_committee_id INT UNSIGNED NOT NULL,
  handoff_note TEXT NOT NULL,
  requested_due_date DATE NULL,
  handoff_status ENUM('pending_handoff','in_receiving_review','returned_for_revision','resubmitted','completed') NOT NULL DEFAULT 'pending_handoff',
  return_note TEXT NULL,
  returned_at DATETIME NULL,
  resubmitted_at DATETIME NULL,
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_handoff_source_assignment FOREIGN KEY (source_assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_handoff_receiving_assignment FOREIGN KEY (receiving_assignment_id) REFERENCES assignments(id) ON DELETE SET NULL,
  CONSTRAINT fk_handoff_source_committee FOREIGN KEY (source_committee_id) REFERENCES committees(id),
  CONSTRAINT fk_handoff_receiving_committee FOREIGN KEY (receiving_committee_id) REFERENCES committees(id),
  CONSTRAINT fk_handoff_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NULL,
  committee_id INT UNSIGNED NULL,
  subtask_id INT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  original_file_name VARCHAR(255) NOT NULL,
  stored_file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_extension VARCHAR(20) NULL,
  mime_type VARCHAR(100) NULL,
  file_size BIGINT UNSIGNED NULL,
  category VARCHAR(100) NULL,
  version_number VARCHAR(50) NULL,
  description TEXT NULL,
  uploaded_by_user_id INT UNSIGNED NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_final_version TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_document_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE SET NULL,
  CONSTRAINT fk_document_committee FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE SET NULL,
  CONSTRAINT fk_document_subtask FOREIGN KEY (subtask_id) REFERENCES assignment_subtasks(id) ON DELETE SET NULL,
  CONSTRAINT fk_document_uploaded_by FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action_type VARCHAR(100) NOT NULL,
  target_type VARCHAR(100) NOT NULL,
  target_id INT UNSIGNED NULL,
  action_summary VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
