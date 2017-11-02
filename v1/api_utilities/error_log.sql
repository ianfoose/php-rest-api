CREATE TABLE `errors_tbl` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(455) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `type` varchar(45) DEFAULT NULL,
  `description` varchar(45) DEFAULT NULL,
  'deleted' int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;