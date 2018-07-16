CREATE TABLE IF NOT EXISTS /*_*/resourceloaderarticles (
  `rla_id` int(11) NOT NULL,
  `rla_wiki` VARBINARY(255) NOT NULL,
  `rla_page` VARBINARY(255) NOT NULL,
  `rla_type` VARBINARY(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DATA DIRECTORY='/var/lib/mysql/innodb_data';

ALTER TABLE /*_*/resourceloaderarticles
  ADD PRIMARY KEY `rla_id` (`rla_id`),
  ADD KEY `rla_type` (`rla_type`),
  ADD KEY `rla_wiki` (`rla_wiki`);

ALTER TABLE /*_*/resourceloaderarticles
  MODIFY `rla_id` int(11) NOT NULL AUTO_INCREMENT;
