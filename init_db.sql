-- --------------------------------------------------------
-- Host:                         localhost
-- Wersja serwera:               5.7.24 - MySQL Community Server (GPL)
-- Serwer OS:                    Win64
-- HeidiSQL Wersja:              10.2.0.5599
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;


-- Zrzut struktury bazy danych betting
CREATE DATABASE IF NOT EXISTS `betting` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;
USE `betting`;

-- Zrzut struktury tabela bettingseb.tbl_formulas
CREATE TABLE IF NOT EXISTS `tbl_formulas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `secpoint` varchar(20) DEFAULT NULL,
  `timer` varchar(20) DEFAULT NULL,
  `position_percentage` varchar(3) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Eksport danych został odznaczony.

-- Zrzut struktury tabela bettingseb.tbl_hist_results
CREATE TABLE IF NOT EXISTS `tbl_hist_results` (
  `hist_id` int(11) NOT NULL AUTO_INCREMENT,
  `race_id` int(11) NOT NULL,
  `race_date` varchar(45) NOT NULL,
  `race_distance` varchar(45) NOT NULL,
  `horse_id` int(11) DEFAULT NULL,
  `h_num` varchar(5) DEFAULT NULL,
  `horse_position` int(11) DEFAULT NULL,
  `horse_weight` varchar(45) NOT NULL,
  `horse_fixed_odds` varchar(45) DEFAULT NULL,
  `horse_h2h` varchar(45) DEFAULT NULL,
  `temp_h2h` float DEFAULT NULL,
  `prize` varchar(45) NOT NULL,
  `race_time` varchar(45) NOT NULL,
  `length` float NOT NULL,
  `sectional` varchar(45) NOT NULL,
  `avgsec` double NOT NULL DEFAULT '0',
  `avgsectional` float NOT NULL DEFAULT '0',
  `handicap` float NOT NULL,
  `rating` float NOT NULL,
  `rank` float NOT NULL,
  PRIMARY KEY (`hist_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8714 DEFAULT CHARSET=utf8;

-- Eksport danych został odznaczony.

-- Zrzut struktury tabela bettingseb.tbl_horses
CREATE TABLE IF NOT EXISTS `tbl_horses` (
  `horse_id` int(11) NOT NULL AUTO_INCREMENT,
  `horse_name` varchar(255) NOT NULL,
  `horse_slug` varchar(255) NOT NULL,
  `horse_latest_results` varchar(45) DEFAULT NULL,
  `added_on` varchar(25) NOT NULL,
  PRIMARY KEY (`horse_id`),
  UNIQUE KEY `horse_name` (`horse_name`)
) ENGINE=InnoDB AUTO_INCREMENT=618 DEFAULT CHARSET=utf8;

-- Eksport danych został odznaczony.

-- Zrzut struktury tabela bettingseb.tbl_meetings
CREATE TABLE IF NOT EXISTS `tbl_meetings` (
  `meeting_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `meeting_date` varchar(45) DEFAULT NULL,
  `meeting_name` varchar(45) DEFAULT NULL,
  `meeting_url` varchar(255) DEFAULT NULL,
  `added_on` varchar(25) NOT NULL,
  PRIMARY KEY (`meeting_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

-- Eksport danych został odznaczony.

-- Zrzut struktury tabela bettingseb.tbl_races
CREATE TABLE IF NOT EXISTS `tbl_races` (
  `race_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `old_race_id` int(11) NOT NULL,
  `meeting_id` int(11) DEFAULT NULL,
  `race_order` int(11) DEFAULT NULL,
  `race_schedule_time` varchar(45) DEFAULT NULL,
  `race_title` varchar(45) DEFAULT NULL,
  `race_slug` varchar(45) NOT NULL,
  `race_distance` int(11) NOT NULL DEFAULT '0',
  `round_distance` int(11) NOT NULL,
  `race_url` varchar(255) NOT NULL,
  `rank_status` int(11) NOT NULL,
  `sec_status` int(11) DEFAULT NULL,
  PRIMARY KEY (`race_id`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8;

-- Eksport danych został odznaczony.

-- Zrzut struktury tabela bettingseb.tbl_results
CREATE TABLE IF NOT EXISTS `tbl_results` (
  `result_id` int(11) NOT NULL AUTO_INCREMENT,
  `race_id` int(11) NOT NULL,
  `horse_id` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  PRIMARY KEY (`result_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Eksport danych został odznaczony.

-- Zrzut struktury tabela bettingseb.tbl_temp_hraces
CREATE TABLE IF NOT EXISTS `tbl_temp_hraces` (
  `race_id` int(11) NOT NULL COMMENT 'h',
  `horse_id` int(11) NOT NULL,
  `horse_num` int(11) NOT NULL,
  `horse_fxodds` varchar(45) NOT NULL,
  `horse_h2h` varchar(45) NOT NULL,
  `horse_weight` varchar(45) DEFAULT NULL,
  `horse_win` varchar(45) DEFAULT NULL,
  `horse_plc` varchar(45) DEFAULT NULL,
  `horse_avg` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Eksport danych został odznaczony.

ALTER TABLE `tbl_hist_results` ADD INDEX `avg_sectional_update_index` (`race_id`, `race_distance`, `horse_id`) USING BTREE;
ALTER TABLE `tbl_temp_hraces` ADD INDEX `odds_results_index` (`race_id`, `horse_id`) USING BTREE;
ALTER TABLE `tbl_results` ADD INDEX `results_check_unique_entry_index` (`race_id`, `horse_id`, `position`) USING BTREE;
ALTER TABLE `tbl_horses` ADD INDEX `horse_slug` (`horse_slug`) USING BTREE;
ALTER TABLE `tbl_meetings` ADD UNIQUE `meeting_search_index` (`meeting_date`, `meeting_name`);

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
