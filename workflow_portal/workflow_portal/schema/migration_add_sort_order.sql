-- Migration: Add sort_order to assignments
-- Run this once in phpMyAdmin on your Reclaim Hosting database.
-- sort_order is optional (NULL = use creation date as fallback).

ALTER TABLE assignments
  ADD COLUMN sort_order TINYINT UNSIGNED NULL DEFAULT NULL
  AFTER title;
