-- PS Data Exporter - Installation SQL
-- Compatible PrestaShop 1.7.6.5+ / PHP 7.2+

-- Table des jobs d'export
CREATE TABLE IF NOT EXISTS `PREFIX_pde_export_job` (
    `id_export_job` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_employee` INT(11) UNSIGNED NOT NULL,
    `id_shop` INT(11) UNSIGNED DEFAULT NULL,
    `export_type` VARCHAR(32) NOT NULL DEFAULT 'orders' COMMENT 'customers|orders|full',
    `export_level` VARCHAR(32) NOT NULL DEFAULT 'essential' COMMENT 'essential|complete|ultra',
    `export_mode` VARCHAR(32) NOT NULL DEFAULT 'relational' COMMENT 'relational|flat',
    `params_json` LONGTEXT NOT NULL COMMENT 'Filtres et options JSON',
    `schema_snapshot` LONGTEXT DEFAULT NULL COMMENT 'Snapshot colonnes au démarrage',
    `status` VARCHAR(32) NOT NULL DEFAULT 'pending' COMMENT 'pending|running|paused|completed|failed',
    `total_records` INT(11) UNSIGNED DEFAULT 0,
    `processed_records` INT(11) UNSIGNED DEFAULT 0,
    `current_entity` VARCHAR(64) DEFAULT NULL,
    `cursors_json` TEXT DEFAULT NULL COMMENT 'Curseurs par entité JSON',
    `error_message` TEXT DEFAULT NULL,
    `started_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_export_job`),
    KEY `idx_status` (`status`),
    KEY `idx_employee` (`id_employee`),
    KEY `idx_date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des fichiers générés
CREATE TABLE IF NOT EXISTS `PREFIX_pde_export_file` (
    `id_export_file` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_export_job` INT(11) UNSIGNED NOT NULL,
    `entity_name` VARCHAR(64) NOT NULL,
    `filepath` VARCHAR(512) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `filesize` BIGINT UNSIGNED DEFAULT 0,
    `row_count` INT(11) UNSIGNED DEFAULT 0,
    `checksum` VARCHAR(64) DEFAULT NULL,
    `download_token` VARCHAR(64) NOT NULL,
    `download_expires` DATETIME NOT NULL,
    `download_count` INT(11) UNSIGNED DEFAULT 0,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_export_file`),
    KEY `idx_job` (`id_export_job`),
    KEY `idx_token` (`download_token`),
    KEY `idx_expires` (`download_expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de mapping géographique France (département -> région)
CREATE TABLE IF NOT EXISTS `PREFIX_pde_geo_map` (
    `id_geo_map` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `country_iso` VARCHAR(3) NOT NULL DEFAULT 'FR',
    `dept_code` VARCHAR(5) NOT NULL,
    `dept_name` VARCHAR(128) NOT NULL,
    `region_code` VARCHAR(5) NOT NULL,
    `region_name` VARCHAR(128) NOT NULL,
    PRIMARY KEY (`id_geo_map`),
    UNIQUE KEY `idx_dept` (`country_iso`, `dept_code`),
    KEY `idx_region` (`region_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des logs d'export (sans PII)
CREATE TABLE IF NOT EXISTS `PREFIX_pde_export_log` (
    `id_log` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_export_job` INT(11) UNSIGNED NOT NULL,
    `level` VARCHAR(16) NOT NULL DEFAULT 'info' COMMENT 'debug|info|warning|error',
    `message` TEXT NOT NULL,
    `context_json` TEXT DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_log`),
    KEY `idx_job` (`id_export_job`),
    KEY `idx_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
