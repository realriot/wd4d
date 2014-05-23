--
-- Table structure for table `wd4d_cache`
--

DROP TABLE IF EXISTS `wd4d_cache`;
CREATE TABLE `wd4d_cache` (
  `username` varchar(128) NOT NULL,
  `path` varchar(128) NOT NULL,
  `cachedata` text NOT NULL,
  `timestamp` bigint(20) NOT NULL,
  PRIMARY KEY (`username`,`path`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
