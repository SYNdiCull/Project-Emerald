SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `match_stats` (
  `match_id` varchar(255) NOT NULL,
  `match_date` date DEFAULT NULL,
  `match_duration` int(11) NOT NULL,
  `team_ids` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `overall_stats` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_kills` int(11) DEFAULT NULL,
  `total_deaths` int(11) DEFAULT NULL,
  `total_assists` int(11) DEFAULT NULL,
  `total_kd` double DEFAULT NULL,
  `total_kad` double DEFAULT NULL,
  `total_cs` int(11) DEFAULT NULL,
  `total_csm` double DEFAULT NULL,
  `total_dmg` int(11) DEFAULT NULL,
  `total_dmm` double DEFAULT NULL,
  `total_vision_score` int(11) DEFAULT NULL,
  `total_kp` double DEFAULT NULL,
  `games` int(11) DEFAULT NULL,
  `total_average_kills` double DEFAULT NULL,
  `total_average_deaths` double DEFAULT NULL,
  `total_average_assists` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `players` (
  `ID` int(11) NOT NULL,
  `time_registered` text NOT NULL,
  `read_terms` varchar(3) NOT NULL,
  `agree_to_terms` varchar(3) NOT NULL,
  `discord_name` text NOT NULL,
  `team_captain` varchar(5) NOT NULL,
  `name` text NOT NULL,
  `rank` varchar(30) NOT NULL,
  `rank_previous` varchar(30) NOT NULL,
  `role_preferred` varchar(12) NOT NULL,
  `role_alternative` varchar(12) NOT NULL,
  `puuid` text DEFAULT NULL,
  `team_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `player_stats` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `kills` int(11) NOT NULL,
  `deaths` int(11) NOT NULL,
  `assists` int(11) NOT NULL,
  `kd` int(11) NOT NULL,
  `kad` int(11) NOT NULL,
  `cs` int(11) NOT NULL,
  `csm` int(11) NOT NULL,
  `dmg` int(11) NOT NULL,
  `dmm` int(11) NOT NULL,
  `vision_score` int(11) NOT NULL,
  `kp` int(11) NOT NULL,
  `match_id` varchar(255) DEFAULT NULL,
  `team_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `teams` (
  `team_id` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `match_stats`
  ADD PRIMARY KEY (`match_id`);

ALTER TABLE `overall_stats`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `players`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `team_id` (`team_id`);

ALTER TABLE `player_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `fk_match_id` (`team_id`),
  ADD KEY `match_id` (`match_id`);

ALTER TABLE `teams`
  ADD PRIMARY KEY (`team_id`);


ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `overall_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `players`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `player_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE `players`
  ADD CONSTRAINT `players_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`team_id`);

ALTER TABLE `player_stats`
  ADD CONSTRAINT `player_stats_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `match_stats` (`match_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
