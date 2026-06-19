-- Migration: add cc_admin, fc_admin, ardc_admin to role_level ENUM
-- Run this in phpMyAdmin before deploying updated PHP files.

ALTER TABLE `users`
  MODIFY COLUMN `role_level`
    ENUM('superadmin','sc_admin','sc_member','cc_admin','fc_admin','ardc_admin','committee_admin','committee_member','read_only')
    NOT NULL DEFAULT 'committee_member';
