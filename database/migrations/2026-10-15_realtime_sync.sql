-- Sincronización operativa en tiempo real (RF-19 / RN-16)
-- Ejecutar una sola vez en bases Ferozo existentes antes de publicar esta versión.

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
