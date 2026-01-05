-- PS Data Exporter - Installation SQL
-- Tables pour la gestion des exports

-- Table des jobs d'export
CREATE TABLE IF NOT EXISTS `PREFIX_pde_export_job` (
    `id_export_job` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_employee` INT(11) UNSIGNED NOT NULL,
    `id_shop` INT(11) UNSIGNED DEFAULT NULL,
    `export_type` VARCHAR(32) NOT NULL DEFAULT 'orders',
    `export_level` VARCHAR(32) NOT NULL DEFAULT 'essential',
    `export_mode` VARCHAR(32) NOT NULL DEFAULT 'relational',
    `params_json` LONGTEXT,
    `schema_snapshot` LONGTEXT,
    `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    `total_records` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `processed_records` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `current_entity` VARCHAR(64) DEFAULT NULL,
    `cursors_json` TEXT,
    `error_message` TEXT,
    `started_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_export_job`),
    KEY `id_employee` (`id_employee`),
    KEY `status` (`status`),
    KEY `date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des fichiers générés
CREATE TABLE IF NOT EXISTS `PREFIX_pde_export_file` (
    `id_export_file` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_export_job` INT(11) UNSIGNED NOT NULL,
    `entity_name` VARCHAR(64) NOT NULL,
    `filepath` VARCHAR(512) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `filesize` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `row_count` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `checksum` VARCHAR(64) DEFAULT NULL,
    `download_token` VARCHAR(64) NOT NULL,
    `download_expires` DATETIME NOT NULL,
    `download_count` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_export_file`),
    KEY `id_export_job` (`id_export_job`),
    KEY `download_token` (`download_token`),
    KEY `download_expires` (`download_expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de mapping géographique France
CREATE TABLE IF NOT EXISTS `PREFIX_pde_geo_map` (
    `id_geo` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_code` VARCHAR(3) NOT NULL,
    `department_name` VARCHAR(64) NOT NULL,
    `region_name` VARCHAR(64) NOT NULL,
    PRIMARY KEY (`id_geo`),
    UNIQUE KEY `department_code` (`department_code`),
    KEY `region_name` (`region_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des logs d'export
CREATE TABLE IF NOT EXISTS `PREFIX_pde_export_log` (
    `id_log` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_export_job` INT(11) UNSIGNED NOT NULL,
    `level` VARCHAR(16) NOT NULL DEFAULT 'info',
    `message` TEXT NOT NULL,
    `context_json` TEXT,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_log`),
    KEY `id_export_job` (`id_export_job`),
    KEY `level` (`level`),
    KEY `date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
