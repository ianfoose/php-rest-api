CREATE TABLE IF NOT EXISTS `errors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(455) DEFAULT NULL,
  `type` varchar(455) DEFAULT NULL,
  `description` varchar(455) DEFAULT NULL,
  `message` varchar(455) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;