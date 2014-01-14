CREATE TABLE `tokens` (
  `id` bigint(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `token` varchar(100) DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `created` int(11) DEFAULT NULL,
  `token_path` varchar(250) DEFAULT NULL,
  `usable` int(11) DEFAULT NULL,
  `used` int(11) DEFAULT NULL,
  `total_bandwidth` float DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token_id` bigint(20) DEFAULT NULL,
  `url` text,
  `from_ip` varchar(11) DEFAULT '0',
  `transfer_status` int(11) DEFAULT '0',
  `transfer_time` int(11) DEFAULT '0',
  `transfer_size` bigint(20) DEFAULT '0',
  `created` int(11) DEFAULT NULL,
  `transfer_start` int(11) DEFAULT '0',
  `transfer_end` int(11) DEFAULT '0',
  `copy_file_url` varchar(250) DEFAULT NULL,
  `task_id` varchar(100) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `created` (`created`),
  KEY `task_id` (`task_id`),
  KEY `token_id` (`token_id`),
  KEY `transfer_end` (`transfer_end`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8;

CREATE TABLE `users` (
  `id` bigint(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) DEFAULT NULL,
  `email` varchar(250) DEFAULT NULL,
  `access_token` varchar(250) DEFAULT NULL,
  `token_secret` varchar(250) DEFAULT NULL,
  `created` int(11) DEFAULT NULL,
  `active` int(11) DEFAULT '1',
  `copy_user_id` bigint(20) DEFAULT NULL,
  `signature` varchar(12) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `created` (`created`),
  KEY `active` (`active`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

CREATE TABLE `pharstats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) DEFAULT NULL,
  `ip` varchar(32) DEFAULT NULL,
  `bytes` bigint(20) DEFAULT NULL,
  `created` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created` (`created`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;