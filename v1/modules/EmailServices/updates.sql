ALTER TABLE `email_subscriptions` ADD COLUMN `group` varchar(45) DEFAULT 'Default' AFTER `date`;
UPDATE TABLE `email_subscriptions` SET group='Default';