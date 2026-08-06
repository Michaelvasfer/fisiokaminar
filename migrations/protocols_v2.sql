-- migrations/protocols_v2.sql

-- Plantilla de Sesiones para los Protocolos Maestros
CREATE TABLE IF NOT EXISTS `protocol_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phase_id` int(11) NOT NULL,
  `session_number` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `activities` text DEFAULT NULL,
  `equipment` varchar(255) DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 45,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`phase_id`) REFERENCES `protocol_phases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sesiones reales asignadas a un paciente específico
CREATE TABLE IF NOT EXISTS `patient_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `phase_id` int(11) NOT NULL,
  `session_number` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `scheduled_date` date DEFAULT NULL,
  `completed_date` datetime DEFAULT NULL,
  -- Datos Clínicos de Seguimiento
  `observations` text DEFAULT NULL,
  `evolution` text DEFAULT NULL,
  `eva_score` int(11) DEFAULT NULL, -- Nivel de dolor 0-10
  `mobility_notes` text DEFAULT NULL,
  `treatment_changes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`plan_id`) REFERENCES `treatment_plans`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`phase_id`) REFERENCES `protocol_phases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Asegurar que treatment_plans tenga campos necesarios
ALTER TABLE `treatment_plans` ADD COLUMN IF NOT EXISTS `protocol_id` int(11) DEFAULT NULL;
ALTER TABLE `treatment_plans` ADD COLUMN IF NOT EXISTS `total_sessions` int(11) DEFAULT 0;
ALTER TABLE `treatment_plans` ADD COLUMN IF NOT EXISTS `completed_sessions` int(11) DEFAULT 0;
ALTER TABLE `treatment_plans` ADD COLUMN IF NOT EXISTS `status` enum('active','completed','on_hold') DEFAULT 'active';
ALTER TABLE `treatment_plans` ADD COLUMN IF NOT EXISTS `start_date` date DEFAULT NULL;
