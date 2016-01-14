/* SYNTAX: mysql -useandb -pPASSWORD < ~/proj/common/table.sql */
USE seandb;

CREATE TABLE IF NOT EXISTS `airports` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(75) NOT NULL,
    `city` VARCHAR(35) NOT NULL,
    `iata` VARCHAR(3) NOT NULL,
    `x` DECIMAL(10, 7) NOT NULL,
    `y` DECIMAL(10, 7) NOT NULL,
    PRIMARY KEY `id` (`id`),
    KEY `x` (`x`),
    KEY `y` (`y`)
) ENGINE=InnoDB;

/* worse than proper cache, better than nothing */
CREATE TABLE IF NOT EXISTS `airports_food` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `in_id` INT NOT NULL,
    `in_iata` VARCHAR(4) NOT NULL,
    `in_name` VARCHAR(64) NOT NULL,
    `in_city` VARCHAR(64) NOT NULL,
    `name` VARCHAR(64) NOT NULL,
    `x` DECIMAL(10, 7) NOT NULL,
    `y` DECIMAL(10, 7) NOT NULL,
    `yelp` VARCHAR(256) NOT NULL,
    `gmap` VARCHAR(256) NOT NULL,
    `last_updated` INT(11) NOT NULL,
    PRIMARY KEY `id` (`id`),
    KEY `in_id` (`in_id`),
    KEY `x` (`x`),
    KEY `y` (`y`)
) ENGINE=InnoDB;


/*
To see what airports are within ~11km 
http://gis.stackexchange.com/questions/8650/how-to-measure-the-accuracy-of-latitude-and-longitude
select * from airports where round(x, 1) = 40.7 and round(y, 1) = -74.0; 
*/