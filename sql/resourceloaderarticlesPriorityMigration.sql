ALTER TABLE resourceloaderarticles
  ADD COLUMN IF NOT EXISTS `rla_priority` int(11) NOT NULL;
