-- Copia y pega este código en la pestaña "SQL" de tu phpMyAdmin en Hostgator

-- 1. Actualizar la columna 'role' en la tabla users para incluir al administrador y recepcionista
ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'receptionist', 'therapist', 'patient') NOT NULL DEFAULT 'patient';

-- 2. Insertar al usuario Administrador y a la Recepcionista
-- Nota: La contraseña para todos estos usuarios está encriptada y equivale a la palabra "password" (sin comillas)
INSERT INTO `users` (`role`, `name`, `email`, `password`, `age`, `patient_code`) 
VALUES 
('admin', 'Super Admin', 'admin@fisioapp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL),
('receptionist', 'Ana Perez (Recep)', 'frontdesk@fisioapp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 25, NULL);

-- 3. Actualizar la contraseña del terapeuta y paciente de prueba a "password" (por si las modificaste manualmente antes sin encriptar)
UPDATE `users` 
SET `password` = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE `email` IN ('therapist@fisioapp.com', 'sarah@example.com', 'admin@fisioapp.com', 'frontdesk@fisioapp.com');
