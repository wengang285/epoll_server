CREATE TABLE tbCKVErrorLog (
  `id` int(11) AUTO_INCREMENT NOT NULL,
  `iUin` bigint(20) NOT NULL DEFAULT 0,
  `iArea` int(11) NOT NULL DEFAULT 0,
  `iActivityId` int(11) NOT NULL DEFAULT 0,
  `sService` varchar(255) NOT NULL DEFAULT '',
  `dtCreateTime` datetime NOT NULL DEFAULT '0000-00-00',
  primary key `id` (`id`),
  KEY `iUin` (`iUin`),
  KEY `iActivityId` (`iActivityId`),
  KEY `sService` (`sService`)
) ENGINE=InnoDB DEFAULT CHARSET=gbk

