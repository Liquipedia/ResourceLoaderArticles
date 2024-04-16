ALTER TABLE /*_*/resourceloaderarticles
  ADD COLUMN IF NOT EXISTS `rla_priority` int(11) NOT NULL;
 UPDATE /*_*/resourceloaderarticles SET `rla_priority` = 0;
