/*[8:53:52 PM][1993 ms]*/
CREATE TABLE if not exists `x_s3_file`(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `x_s3_type_id` INT(11) NOT NULL,
  `x_s3_volume_id` INT(11) NOT NULL,
  `original_filename` TEXT,
  `filename` VARCHAR(255) NOT NULL,
  `bucket` VARCHAR(255) NOT NULL,
  `url` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(255) NOT NULL,
  `filesize` INT(11),
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0, PRIMARY KEY (`id`)
) ENGINE=INNODB CHARSET=utf8 COLLATE=utf8_general_ci;


CREATE TABLE if not exists `x_s3_image` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255),
  `original_file_id` int(11) NOT NULL DEFAULT '0',
  `thumb_file_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


CREATE TABLE if not exists `x_s3_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(64) NOT NULL DEFAULT '',
  `extension` varchar(5) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;



CREATE TABLE `x_s3_volume` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bucket` varchar(255) NOT NULL DEFAULT '',
  `stored_files_cnt` int(11) NOT NULL DEFAULT '0',
  `enabled` enum('Y','N') DEFAULT 'Y',
  `last_filenum` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bucket` (`bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;





INSERT INTO `x_s3_type` VALUES (1,'png','image/png','png');
INSERT INTO `x_s3_type` VALUES (2,'jpeg','image/jpeg','jpeg');
INSERT INTO `x_s3_type` VALUES (3,'gif','image/gif','gif');