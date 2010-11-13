SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Table structure for table `och_chemicals`
--

CREATE TABLE `och_chemicals` (
  `id` int(9) NOT NULL auto_increment,
  `name` varchar(256) NOT NULL,
  `formula` varchar(256) NOT NULL,
  `cas` varchar(50) NOT NULL,
  `location` int(9) NOT NULL,
  `size` varchar(30) NOT NULL,
  `amount` int(6) NOT NULL,
  `remarks` varchar(256) NOT NULL,
  `time` int(30) NOT NULL,
  PRIMARY KEY  (`id`),
  FULLTEXT KEY `name_index` (`name`,`formula`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=262 ;

-- --------------------------------------------------------

--
-- Table structure for table `och_locations`
--

CREATE TABLE `och_locations` (
  `id` int(9) NOT NULL auto_increment,
  `name` varchar(30) NOT NULL,
  `description` varchar(256) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=14 ;
