-- Migration: add sc_member to role_level ENUM
-- Run this in phpMyAdmin or via CLI before deploying the updated PHP files.

ALTER TABLE `users`
  MODIFY COLUMN `role_level`
    ENUM('superadmin','sc_admin','sc_member','committee_admin','committee_member','read_only')
    NOT NULL DEFAULT 'committee_member';
