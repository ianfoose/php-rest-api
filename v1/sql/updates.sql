-- Audit logs
ALTER TABLE `audit_logs` RENAME `activity`;

ALTER TABLE `activity` ADD COLUMN `row_id` int(11) DEFAULT NULL AFTER `object_id`;