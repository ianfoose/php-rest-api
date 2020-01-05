CREATE TABLE IF NOT EXISTS `push_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(155) DEFAULT NULL,
  `deleted` tinyint(1) DEFAULT '0',
  `user_id` int(11) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `object_id` int(11) DEFAULT NULL,
  `type` varchar(45) DEFAULT NULL,
  `event` varchar(45) DEFAULT NULL,
  `payload` varchar(455) DEFAULT NULL,
  `deleted` TINYINT(1) DEFAULT '0',
  `read` TINYINT(1) DEFAULT '0' ,
  `date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;