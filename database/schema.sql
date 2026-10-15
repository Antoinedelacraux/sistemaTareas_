-- =============================================================================
-- SISTEMA DE TAREAS - ÁREA DE PLANEAMIENTO
-- Script DDL para MySQL 8 / MariaDB (InnoDB, UTF8mb4)
-- Compatible con DonWeb Ferozo / phpMyAdmin
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Tabla: usuarios
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(20) NOT NULL UNIQUE,
  `nombre` VARCHAR(100) NOT NULL,
  `email` VARCHAR(120) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `rol` ENUM('ASISTENTE', 'JEFE_PERSONAL', 'JEFE_PLANEAMIENTO') NOT NULL,
  `cargo` VARCHAR(100) NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_usuarios_rol` (`rol`),
  INDEX `idx_usuarios_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Tabla: sesiones
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sesiones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL UNIQUE,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `expira_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_sesiones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  INDEX `idx_sesiones_token` (`token_hash`),
  INDEX `idx_sesiones_expira` (`expira_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Tabla: tableros_columnas (Estados funcionales del Kanban)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tableros_columnas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(50) NOT NULL,
  `orden` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `tipo_estado` ENUM('PENDIENTE', 'EN_PROCESO', 'BLOQUEADA', 'EN_REVISION', 'FINALIZADA') NOT NULL,
  `color_hex` VARCHAR(7) NOT NULL DEFAULT '#64748b',
  `ambito` ENUM('AREA', 'PERSONAL') NOT NULL DEFAULT 'AREA',
  `usuario_id` INT UNSIGNED NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_columnas_tipo_estado` (`tipo_estado`),
  INDEX `idx_columnas_orden` (`orden`),
  INDEX `idx_columnas_ambito_usuario` (`ambito`, `usuario_id`, `activo`, `orden`),
  CONSTRAINT `fk_columnas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Tabla: tareas
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tareas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(30) NOT NULL UNIQUE,
  `titulo` VARCHAR(255) NOT NULL,
  `descripcion` TEXT NULL,
  `tipo_tarea` ENUM('PERSONAL', 'AREA') NOT NULL DEFAULT 'AREA',
  `columna_id` INT UNSIGNED NOT NULL,
  `prioridad` ENUM('BAJA', 'MEDIA', 'ALTA', 'URGENTE') NOT NULL DEFAULT 'MEDIA',
  `creador_id` INT UNSIGNED NOT NULL,
  `responsable_id` INT UNSIGNED NOT NULL,
  `requiere_aprobacion` TINYINT(1) NOT NULL DEFAULT 0,
  `revisor_id` INT UNSIGNED NULL,
  `fecha_limite` DATE NULL,
  `fecha_finalizada` DATETIME NULL,
  `historial_inmediato` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_tareas_columna` FOREIGN KEY (`columna_id`) REFERENCES `tableros_columnas` (`id`),
  CONSTRAINT `fk_tareas_creador` FOREIGN KEY (`creador_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_tareas_responsable` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_tareas_revisor` FOREIGN KEY (`revisor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  INDEX `idx_tareas_tipo_resp` (`tipo_tarea`, `responsable_id`),
  INDEX `idx_tareas_columna` (`columna_id`),
  INDEX `idx_tareas_prioridad` (`prioridad`),
  INDEX `idx_tareas_fecha_limite` (`fecha_limite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Tabla: subtareas
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subtareas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tarea_id` INT UNSIGNED NOT NULL,
  `titulo` VARCHAR(255) NOT NULL,
  `completada` TINYINT(1) NOT NULL DEFAULT 0,
  `orden` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_subtareas_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tareas` (`id`) ON DELETE CASCADE,
  INDEX `idx_subtareas_tarea` (`tarea_id`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Tabla: tarea_dependencias (Grafo Acíclico Dirigido - DAG)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tarea_dependencias` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tarea_id` INT UNSIGNED NOT NULL,
  `predecesora_id` INT UNSIGNED NOT NULL,
  `tipo_dependencia` ENUM('AND_FINISH_TO_START') NOT NULL DEFAULT 'AND_FINISH_TO_START',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tarea_predecesora` (`tarea_id`, `predecesora_id`),
  CONSTRAINT `fk_dep_tarea_sucesora` FOREIGN KEY (`tarea_id`) REFERENCES `tareas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dep_tarea_predecesora` FOREIGN KEY (`predecesora_id`) REFERENCES `tareas` (`id`) ON DELETE CASCADE,
  INDEX `idx_dep_predecesora` (`predecesora_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Tabla: tarea_aprobaciones
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tarea_aprobaciones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tarea_id` INT UNSIGNED NOT NULL,
  `revisor_id` INT UNSIGNED NOT NULL,
  `estado` ENUM('PENDIENTE', 'APROBADA', 'DEVUELTA') NOT NULL DEFAULT 'PENDIENTE',
  `comentario_entrega` TEXT NULL,
  `observaciones_devolucion` TEXT NULL,
  `fecha_solicitud` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_resolucion` DATETIME NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_aprob_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tareas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aprob_revisor` FOREIGN KEY (`revisor_id`) REFERENCES `usuarios` (`id`),
  INDEX `idx_aprob_tarea_estado` (`tarea_id`, `estado`),
  INDEX `idx_aprob_revisor_estado` (`revisor_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Tabla: tarea_comentarios (Comentarios y Evidencias de Entrega)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tarea_comentarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tarea_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `comentario` TEXT NOT NULL,
  `es_evidencia` TINYINT(1) NOT NULL DEFAULT 0,
  `archivo_nombre_original` VARCHAR(255) NULL,
  `archivo_ruta_hash` VARCHAR(255) NULL,
  `archivo_mime` VARCHAR(100) NULL,
  `archivo_size_bytes` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_coment_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tareas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_coment_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  INDEX `idx_coment_tarea` (`tarea_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Tabla: tarea_colaboradores
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tarea_colaboradores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tarea_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tarea_colaborador` (`tarea_id`, `usuario_id`),
  CONSTRAINT `fk_colab_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `tareas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_colab_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. Tabla: auditoria_eventos (Historial inmutable de trazabilidad)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `auditoria_eventos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NULL,
  `entidad_tipo` ENUM('TAREA', 'SUBTAREA', 'DEPENDENCIA', 'APROBACION', 'AUTH') NOT NULL,
  `entidad_id` INT UNSIGNED NOT NULL,
  `accion` VARCHAR(50) NOT NULL,
  `estado_anterior` VARCHAR(50) NULL,
  `estado_nuevo` VARCHAR(50) NULL,
  `detalles_json` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_auditoria_entidad` (`entidad_tipo`, `entidad_id`),
  INDEX `idx_auditoria_usuario` (`usuario_id`),
  INDEX `idx_auditoria_fecha` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. Tablas: sincronización operativa en tiempo real compatible con Ferozo
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sincronizacion_eventos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo` VARCHAR(60) NOT NULL,
  `entidad_tipo` VARCHAR(30) NOT NULL,
  `entidad_id` INT UNSIGNED NOT NULL,
  `tarea_id` INT UNSIGNED NULL,
  `actor_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_sync_eventos_created` (`created_at`),
  INDEX `idx_sync_eventos_tarea` (`tarea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sincronizacion_evento_destinatarios` (
  `evento_id` BIGINT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`evento_id`, `usuario_id`),
  INDEX `idx_sync_destinatario_cursor` (`usuario_id`, `evento_id`),
  CONSTRAINT `fk_sync_dest_evento` FOREIGN KEY (`evento_id`) REFERENCES `sincronizacion_eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
