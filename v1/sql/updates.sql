-- Audit logs
ALTER TABLE `audit_logs` RENAME `activity`;

ALTER TABLE `activity` ADD COLUMN `row_id` int(11) DEFAULT NULL AFTER `object_id`;

-- Email Subscriptions

ALTER TABLE `email_subscriptions` ADD COLUMN `group` varchar(45) DEFAULT 'Default' AFTER `date`;
UPDATE TABLE `email_subscriptions` SET group='Default';