CREATE TABLE IF NOT EXISTS `activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `object_id` int(11) DEFAULT NULL,
  `row_id` int(11) DEFAULT NULL,
  `editor_id` int(11) DEFAULT NULL,
  `type` varchar(45) DEFAULT NULL,
  `event` varchar(45) DEFAULT 'changed',
  `date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;