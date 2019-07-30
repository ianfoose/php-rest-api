<<<<<<< HEAD
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `object_id` int(11) DEFAULT NULL,
  `editor_id` int(11) DEFAULT NULL,
  `type` varchar(45) DEFAULT NULL,
  `event` varchar(45) DEFAULT 'changed',
  `date` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `email_template_edits` (
=======
CREATE TABLE `email_template_edits` (
>>>>>>> 257d6930320df4e9081e84407e37f6474ea2ba33
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `body` text,
  `date` datetime DEFAULT CURRENT_TIMESTAMP,
  `name` varchar(45) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

<<<<<<< HEAD
CREATE TABLE IF NOT EXISTS `email_templates` (
=======
CREATE TABLE `email_templates` (
>>>>>>> 257d6930320df4e9081e84407e37f6474ea2ba33
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `deleted` tinyint(1) DEFAULT '0',
  `date` datetime DEFAULT CURRENT_TIMESTAMP,
  `user_id` int(11) DEFAULT NULL,
  `body` text,
  `name` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8;

<<<<<<< HEAD
CREATE TABLE IF NOT EXISTS `errors` (
=======
CREATE TABLE `errors` (
>>>>>>> 257d6930320df4e9081e84407e37f6474ea2ba33
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(455) DEFAULT NULL,
  `date` datetime DEFAULT CURRENT_TIMESTAMP,
  `type` varchar(455) DEFAULT NULL,
  `description` varchar(455) DEFAULT NULL,
  `message` varchar(455) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=470 DEFAULT CHARSET=utf8;

<<<<<<< HEAD
CREATE TABLE IF NOT EXISTS `email_subscriptions` (
=======
CREATE TABLE `email_subscriptions` (
>>>>>>> 257d6930320df4e9081e84407e37f6474ea2ba33
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) DEFAULT NULL,
  `subscriber` tinyint(1) DEFAULT '0',
  `date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

<<<<<<< HEAD
CREATE TABLE IF NOT EXISTS `push_tokens` (
=======
CREATE TABLE `push_tokens` (
>>>>>>> 257d6930320df4e9081e84407e37f6474ea2ba33
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(155) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT '0',
  `date` datetime DEFAULT CURRENT_TIMESTAMP,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

<<<<<<< HEAD
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `object_id` int(11) DEFAULT NULL,
  `object` varchar(45) DEFAULT NULL,
  `deleted` TINYINT(1) DEFAULT '0',
  `read` TINYINT(1) DEFAULT '0' ,
  `date` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tokens` (
=======
CREATE TABLE `tokens` (
>>>>>>> 257d6930320df4e9081e84407e37f6474ea2ba33
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `u_id` int(11) DEFAULT NULL,
  `date` datetime DEFAULT CURRENT_TIMESTAMP,
  `exp_date` datetime DEFAULT NULL,
  `revoked` tinyint(4) DEFAULT '0',
  `token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=186 DEFAULT CHARSET=utf8;

<<<<<<< HEAD
CREATE TABLE IF NOT EXISTS `traffic` (
=======
CREATE TABLE `traffic` (
>>>>>>> 257d6930320df4e9081e84407e37f6474ea2ba33
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` datetime DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(45) DEFAULT NULL,
  `client` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1764 DEFAULT CHARSET=utf8;
