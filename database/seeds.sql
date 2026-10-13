-- =============================================================================
-- SISTEMA DE TAREAS - ÁREA DE PLANEAMIENTO
-- Datos Semilla Iniciales (Seeds)
-- Compatible con DonWeb Ferozo / phpMyAdmin
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. Semilla: tableros_columnas (5 estados funcionales de referencia)
-- -----------------------------------------------------------------------------
INSERT INTO `tableros_columnas` (`id`, `nombre`, `orden`, `tipo_estado`, `color_hex`, `activo`) VALUES
(1, 'Pendiente', 1, 'PENDIENTE', '#64748b', 1),
(2, 'En proceso', 2, 'EN_PROCESO', '#2563eb', 1),
(3, 'Bloqueada', 3, 'BLOQUEADA', '#e11d48', 1),
(4, 'En revisión', 4, 'EN_REVISION', '#d97706', 1),
(5, 'Finalizada', 5, 'FINALIZADA', '#059669', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- -----------------------------------------------------------------------------
-- 2. Semilla: usuarios (Equipo de 5 personas del Área de Planeamiento)
-- Contraseña inicial para entorno de desarrollo/staging: Planeamiento2026!
-- Hash Bcrypt: $2y$12$uEG1MZnqkcoThSpgvrgduuqHdhIaVKPlwGHRsPA6prWncrmVvQeJK
-- -----------------------------------------------------------------------------
INSERT INTO `usuarios` (`id`, `codigo`, `nombre`, `email`, `password_hash`, `rol`, `cargo`, `activo`) VALUES
(1, 'ASIST-01', 'Marco Chumbes', 'mchumbes@planeamiento.tal', '$2y$12$uEG1MZnqkcoThSpgvrgduuqHdhIaVKPlwGHRsPA6prWncrmVvQeJK', 'ASISTENTE', 'Asistente de Información 1', 1),
(2, 'ASIST-02', 'Asistente Dos', 'asistente2@planeamiento.tal', '$2y$12$uEG1MZnqkcoThSpgvrgduuqHdhIaVKPlwGHRsPA6prWncrmVvQeJK', 'ASISTENTE', 'Asistente de Información 2', 1),
(3, 'ASIST-03', 'Asistente Tres', 'asistente3@planeamiento.tal', '$2y$12$uEG1MZnqkcoThSpgvrgduuqHdhIaVKPlwGHRsPA6prWncrmVvQeJK', 'ASISTENTE', 'Asistente de Información 3', 1),
(4, 'JEFE-PER', 'Jefe de Personal', 'jefe.personal@planeamiento.tal', '$2y$12$uEG1MZnqkcoThSpgvrgduuqHdhIaVKPlwGHRsPA6prWncrmVvQeJK', 'JEFE_PERSONAL', 'Jefe de Personal', 1),
(5, 'JEFE-PLA', 'Jefe de Planeamiento', 'jefe.planeamiento@planeamiento.tal', '$2y$12$uEG1MZnqkcoThSpgvrgduuqHdhIaVKPlwGHRsPA6prWncrmVvQeJK', 'JEFE_PLANEAMIENTO', 'Jefe del Área de Planeamiento', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);
