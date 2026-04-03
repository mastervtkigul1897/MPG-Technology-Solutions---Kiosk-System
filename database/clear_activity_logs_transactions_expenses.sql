-- One-time wipe: activity logs, sales transactions (and line items), linked inventory movements, and all expenses.
-- Run manually or via: mysql ... < this_file.sql
-- Order respects typical FKs (child rows first).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM transaction_items;
DELETE FROM inventory_movements WHERE transaction_id IS NOT NULL;
DELETE FROM transactions;
DELETE FROM expenses;
DELETE FROM activity_logs;

SET FOREIGN_KEY_CHECKS = 1;
