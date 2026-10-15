-- =============================================================================
-- SISTEMA DE TAREAS - ÁREA DE PLANEAMIENTO
-- Configuración inicial segura para una instalación nueva
-- Compatible con DonWeb Ferozo / phpMyAdmin
-- =============================================================================

SET NAMES utf8mb4;

-- La instalación no incluye usuarios, tareas ni credenciales de demostración.
-- El primer Jefe de Área se crea una sola vez desde /instalar.php.
INSERT INTO `tableros_columnas` (`id`, `nombre`, `orden`, `tipo_estado`, `color_hex`, `ambito`, `activo`) VALUES
(1, 'Pendiente', 1, 'PENDIENTE', '#64748b', 'AREA', 1),
(2, 'En proceso', 2, 'EN_PROCESO', '#2563eb', 'AREA', 1),
(3, 'Bloqueada', 3, 'BLOQUEADA', '#e11d48', 'AREA', 1),
(4, 'En revisión', 4, 'EN_REVISION', '#d97706', 'AREA', 1),
(5, 'Finalizada', 5, 'FINALIZADA', '#059669', 'AREA', 1)
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `orden` = VALUES(`orden`),
    `tipo_estado` = VALUES(`tipo_estado`),
    `color_hex` = VALUES(`color_hex`),
    `ambito` = VALUES(`ambito`),
    `activo` = VALUES(`activo`);
