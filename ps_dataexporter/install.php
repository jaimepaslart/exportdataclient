<?php
/**
 * PS Data Exporter - Script d'installation manuelle
 *
 * Ce script permet d'installer/réinstaller les tables sans passer par le back-office.
 * Utile pour le développement ou en cas de problème d'installation.
 *
 * Usage CLI: php modules/ps_dataexporter/install.php [--force]
 * Usage Web: Accessible uniquement aux super-admins PrestaShop
 *
 * Options:
 *   --force : Supprime les tables existantes avant de les recréer
 */

// Vérification sécurité
if (php_sapi_name() !== 'cli') {
    $configPath = dirname(dirname(dirname(__FILE__))) . '/config/config.inc.php';
    if (file_exists($configPath)) {
        require_once $configPath;
        if (!defined('_PS_VERSION_')) {
            die('PrestaShop non chargé');
        }
        $context = Context::getContext();
        if (!$context->employee || !$context->employee->id) {
            die('Accès refusé - Connectez-vous au back-office');
        }
        if (!$context->employee->isSuperAdmin()) {
            die('Accès refusé - Super admin requis');
        }
    } else {
        die('Configuration PrestaShop non trouvée');
    }
    $force = isset($_GET['force']) && $_GET['force'] === 'yes';
} else {
    if (!file_exists(dirname(dirname(dirname(__FILE__))) . '/config/config.inc.php')) {
        die("Erreur: Exécutez depuis le répertoire PrestaShop\n");
    }
    require_once dirname(dirname(dirname(__FILE__))) . '/config/config.inc.php';
    $force = in_array('--force', $argv);
}

header('Content-Type: text/plain; charset=utf-8');

echo "=======================================================\n";
echo "   PS Data Exporter - Installation\n";
echo "=======================================================\n\n";

$errors = array();
$success = array();

// Option force : supprimer les tables existantes
if ($force) {
    echo "[0] Mode FORCE - Suppression des tables existantes...\n";
    $dropTables = array(
        'pde_export_log',
        'pde_export_file',
        'pde_export_job',
        'pde_geo_map',
    );
    foreach ($dropTables as $table) {
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $table . '`');
        echo "    ✓ Table $table supprimée\n";
    }
    echo "\n";
}

// 1. Création des tables
echo "[1] Création des tables...\n";

$sql = array();

// Table des jobs d'export
$sql['pde_export_job'] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pde_export_job` (
    `id_export_job` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_employee` INT(11) UNSIGNED NOT NULL,
    `id_shop` INT(11) UNSIGNED DEFAULT NULL,
    `export_type` VARCHAR(32) NOT NULL DEFAULT "orders" COMMENT "customers|orders|full",
    `export_level` VARCHAR(32) NOT NULL DEFAULT "essential" COMMENT "essential|complete|ultra",
    `export_mode` VARCHAR(32) NOT NULL DEFAULT "relational" COMMENT "relational|flat",
    `params_json` LONGTEXT NOT NULL COMMENT "Filtres et options JSON",
    `schema_snapshot` LONGTEXT DEFAULT NULL COMMENT "Snapshot colonnes au démarrage",
    `status` VARCHAR(32) NOT NULL DEFAULT "pending" COMMENT "pending|running|paused|completed|failed",
    `total_records` INT(11) UNSIGNED DEFAULT 0,
    `processed_records` INT(11) UNSIGNED DEFAULT 0,
    `current_entity` VARCHAR(64) DEFAULT NULL,
    `cursors_json` TEXT DEFAULT NULL COMMENT "Curseurs par entité JSON",
    `error_message` TEXT DEFAULT NULL,
    `started_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_export_job`),
    KEY `idx_status` (`status`),
    KEY `idx_employee` (`id_employee`),
    KEY `idx_date_add` (`date_add`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

// Table des fichiers générés
$sql['pde_export_file'] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pde_export_file` (
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
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

// Table de mapping géographique
$sql['pde_geo_map'] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pde_geo_map` (
    `id_geo_map` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `country_iso` VARCHAR(3) NOT NULL DEFAULT "FR",
    `dept_code` VARCHAR(5) NOT NULL,
    `dept_name` VARCHAR(128) NOT NULL,
    `region_code` VARCHAR(5) NOT NULL,
    `region_name` VARCHAR(128) NOT NULL,
    PRIMARY KEY (`id_geo_map`),
    UNIQUE KEY `idx_dept` (`country_iso`, `dept_code`),
    KEY `idx_region` (`region_code`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

// Table des logs
$sql['pde_export_log'] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pde_export_log` (
    `id_log` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_export_job` INT(11) UNSIGNED NOT NULL,
    `level` VARCHAR(16) NOT NULL DEFAULT "info" COMMENT "debug|info|warning|error",
    `message` TEXT NOT NULL,
    `context_json` TEXT DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_log`),
    KEY `idx_job` (`id_export_job`),
    KEY `idx_level` (`level`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

foreach ($sql as $table => $query) {
    if (Db::getInstance()->execute($query)) {
        $success[] = "Table $table créée";
        echo "    ✓ $table\n";
    } else {
        $errors[] = "Erreur création table $table";
        echo "    ✗ $table (ERREUR)\n";
    }
}

// 2. Insertion du mapping géographique France
echo "\n[2] Insertion du mapping géographique France...\n";

$geoCount = Db::getInstance()->getValue("SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "pde_geo_map` WHERE country_iso = 'FR'");
if ($geoCount >= 95) {
    echo "    - Déjà présent ($geoCount départements)\n";
} else {
    $mapping = array(
        // Auvergne-Rhône-Alpes
        array('01', 'Ain', 'ARA', 'Auvergne-Rhône-Alpes'),
        array('03', 'Allier', 'ARA', 'Auvergne-Rhône-Alpes'),
        array('07', 'Ardèche', 'ARA', 'Auvergne-Rhône-Alpes'),
        array('15', 'Cantal', 'ARA', 'Auvergne-Rhône-Alpes'),
        array('26', 'Drôme', 'ARA', 'Auvergne-Rhône-Alpes'),
        array('38', 'Isère', 'ARA', 'Auvergne-Rhône-Alpes'),
        array('42', 'Loire', 'ARA', 'Auvergne-Rhône-Alpes'),
        array('43', 'Haute-Loire', 'ARA', 'Auvergne-Rhône-Alpes'),
        array('63', 'Puy-de-Dôme', 'ARA', 'Auvergne-Rhône-Alpes'),
        array('69', 'Rhône', 'ARA', 'Auvergne-Rhône-Alpes'),
        array('73', 'Savoie', 'ARA', 'Auvergne-Rhône-Alpes'),
        array('74', 'Haute-Savoie', 'ARA', 'Auvergne-Rhône-Alpes'),
        // Bourgogne-Franche-Comté
        array('21', 'Côte-d\'Or', 'BFC', 'Bourgogne-Franche-Comté'),
        array('25', 'Doubs', 'BFC', 'Bourgogne-Franche-Comté'),
        array('39', 'Jura', 'BFC', 'Bourgogne-Franche-Comté'),
        array('58', 'Nièvre', 'BFC', 'Bourgogne-Franche-Comté'),
        array('70', 'Haute-Saône', 'BFC', 'Bourgogne-Franche-Comté'),
        array('71', 'Saône-et-Loire', 'BFC', 'Bourgogne-Franche-Comté'),
        array('89', 'Yonne', 'BFC', 'Bourgogne-Franche-Comté'),
        array('90', 'Territoire de Belfort', 'BFC', 'Bourgogne-Franche-Comté'),
        // Bretagne
        array('22', 'Côtes-d\'Armor', 'BRE', 'Bretagne'),
        array('29', 'Finistère', 'BRE', 'Bretagne'),
        array('35', 'Ille-et-Vilaine', 'BRE', 'Bretagne'),
        array('56', 'Morbihan', 'BRE', 'Bretagne'),
        // Centre-Val de Loire
        array('18', 'Cher', 'CVL', 'Centre-Val de Loire'),
        array('28', 'Eure-et-Loir', 'CVL', 'Centre-Val de Loire'),
        array('36', 'Indre', 'CVL', 'Centre-Val de Loire'),
        array('37', 'Indre-et-Loire', 'CVL', 'Centre-Val de Loire'),
        array('41', 'Loir-et-Cher', 'CVL', 'Centre-Val de Loire'),
        array('45', 'Loiret', 'CVL', 'Centre-Val de Loire'),
        // Corse
        array('2A', 'Corse-du-Sud', 'COR', 'Corse'),
        array('2B', 'Haute-Corse', 'COR', 'Corse'),
        // Grand Est
        array('08', 'Ardennes', 'GES', 'Grand Est'),
        array('10', 'Aube', 'GES', 'Grand Est'),
        array('51', 'Marne', 'GES', 'Grand Est'),
        array('52', 'Haute-Marne', 'GES', 'Grand Est'),
        array('54', 'Meurthe-et-Moselle', 'GES', 'Grand Est'),
        array('55', 'Meuse', 'GES', 'Grand Est'),
        array('57', 'Moselle', 'GES', 'Grand Est'),
        array('67', 'Bas-Rhin', 'GES', 'Grand Est'),
        array('68', 'Haut-Rhin', 'GES', 'Grand Est'),
        array('88', 'Vosges', 'GES', 'Grand Est'),
        // Hauts-de-France
        array('02', 'Aisne', 'HDF', 'Hauts-de-France'),
        array('59', 'Nord', 'HDF', 'Hauts-de-France'),
        array('60', 'Oise', 'HDF', 'Hauts-de-France'),
        array('62', 'Pas-de-Calais', 'HDF', 'Hauts-de-France'),
        array('80', 'Somme', 'HDF', 'Hauts-de-France'),
        // Île-de-France
        array('75', 'Paris', 'IDF', 'Île-de-France'),
        array('77', 'Seine-et-Marne', 'IDF', 'Île-de-France'),
        array('78', 'Yvelines', 'IDF', 'Île-de-France'),
        array('91', 'Essonne', 'IDF', 'Île-de-France'),
        array('92', 'Hauts-de-Seine', 'IDF', 'Île-de-France'),
        array('93', 'Seine-Saint-Denis', 'IDF', 'Île-de-France'),
        array('94', 'Val-de-Marne', 'IDF', 'Île-de-France'),
        array('95', 'Val-d\'Oise', 'IDF', 'Île-de-France'),
        // Normandie
        array('14', 'Calvados', 'NOR', 'Normandie'),
        array('27', 'Eure', 'NOR', 'Normandie'),
        array('50', 'Manche', 'NOR', 'Normandie'),
        array('61', 'Orne', 'NOR', 'Normandie'),
        array('76', 'Seine-Maritime', 'NOR', 'Normandie'),
        // Nouvelle-Aquitaine
        array('16', 'Charente', 'NAQ', 'Nouvelle-Aquitaine'),
        array('17', 'Charente-Maritime', 'NAQ', 'Nouvelle-Aquitaine'),
        array('19', 'Corrèze', 'NAQ', 'Nouvelle-Aquitaine'),
        array('23', 'Creuse', 'NAQ', 'Nouvelle-Aquitaine'),
        array('24', 'Dordogne', 'NAQ', 'Nouvelle-Aquitaine'),
        array('33', 'Gironde', 'NAQ', 'Nouvelle-Aquitaine'),
        array('40', 'Landes', 'NAQ', 'Nouvelle-Aquitaine'),
        array('47', 'Lot-et-Garonne', 'NAQ', 'Nouvelle-Aquitaine'),
        array('64', 'Pyrénées-Atlantiques', 'NAQ', 'Nouvelle-Aquitaine'),
        array('79', 'Deux-Sèvres', 'NAQ', 'Nouvelle-Aquitaine'),
        array('86', 'Vienne', 'NAQ', 'Nouvelle-Aquitaine'),
        array('87', 'Haute-Vienne', 'NAQ', 'Nouvelle-Aquitaine'),
        // Occitanie
        array('09', 'Ariège', 'OCC', 'Occitanie'),
        array('11', 'Aude', 'OCC', 'Occitanie'),
        array('12', 'Aveyron', 'OCC', 'Occitanie'),
        array('30', 'Gard', 'OCC', 'Occitanie'),
        array('31', 'Haute-Garonne', 'OCC', 'Occitanie'),
        array('32', 'Gers', 'OCC', 'Occitanie'),
        array('34', 'Hérault', 'OCC', 'Occitanie'),
        array('46', 'Lot', 'OCC', 'Occitanie'),
        array('48', 'Lozère', 'OCC', 'Occitanie'),
        array('65', 'Hautes-Pyrénées', 'OCC', 'Occitanie'),
        array('66', 'Pyrénées-Orientales', 'OCC', 'Occitanie'),
        array('81', 'Tarn', 'OCC', 'Occitanie'),
        array('82', 'Tarn-et-Garonne', 'OCC', 'Occitanie'),
        // Pays de la Loire
        array('44', 'Loire-Atlantique', 'PDL', 'Pays de la Loire'),
        array('49', 'Maine-et-Loire', 'PDL', 'Pays de la Loire'),
        array('53', 'Mayenne', 'PDL', 'Pays de la Loire'),
        array('72', 'Sarthe', 'PDL', 'Pays de la Loire'),
        array('85', 'Vendée', 'PDL', 'Pays de la Loire'),
        // Provence-Alpes-Côte d'Azur
        array('04', 'Alpes-de-Haute-Provence', 'PAC', 'Provence-Alpes-Côte d\'Azur'),
        array('05', 'Hautes-Alpes', 'PAC', 'Provence-Alpes-Côte d\'Azur'),
        array('06', 'Alpes-Maritimes', 'PAC', 'Provence-Alpes-Côte d\'Azur'),
        array('13', 'Bouches-du-Rhône', 'PAC', 'Provence-Alpes-Côte d\'Azur'),
        array('83', 'Var', 'PAC', 'Provence-Alpes-Côte d\'Azur'),
        array('84', 'Vaucluse', 'PAC', 'Provence-Alpes-Côte d\'Azur'),
        // DOM-TOM
        array('971', 'Guadeloupe', 'DOM', 'DOM-TOM'),
        array('972', 'Martinique', 'DOM', 'DOM-TOM'),
        array('973', 'Guyane', 'DOM', 'DOM-TOM'),
        array('974', 'La Réunion', 'DOM', 'DOM-TOM'),
        array('976', 'Mayotte', 'DOM', 'DOM-TOM'),
    );

    $inserted = 0;
    foreach ($mapping as $row) {
        $result = Db::getInstance()->insert('pde_geo_map', array(
            'country_iso' => 'FR',
            'dept_code' => pSQL($row[0]),
            'dept_name' => pSQL($row[1]),
            'region_code' => pSQL($row[2]),
            'region_name' => pSQL($row[3]),
        ), false, true, Db::INSERT_IGNORE);
        if ($result) {
            $inserted++;
        }
    }
    $success[] = "$inserted départements FR insérés";
    echo "    ✓ $inserted départements FR insérés\n";
}

// 3. Création du répertoire exports
echo "\n[3] Création du répertoire exports...\n";
$exportPath = dirname(__FILE__) . '/exports';

if (!is_dir($exportPath)) {
    if (mkdir($exportPath, 0755, true)) {
        $success[] = "Répertoire exports créé";
        echo "    ✓ Répertoire créé\n";
    } else {
        $errors[] = "Impossible de créer le répertoire exports";
        echo "    ✗ Erreur création répertoire\n";
    }
} else {
    echo "    - Répertoire existant\n";
}

// Fichiers de protection
$htaccess = $exportPath . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order Deny,Allow\n    Deny from all\n</IfModule>\n");
    echo "    ✓ .htaccess créé\n";
}

$indexFile = $exportPath . '/index.php';
if (!file_exists($indexFile)) {
    file_put_contents($indexFile, "<?php\nheader('Location: ../../../');\nexit;\n");
    echo "    ✓ index.php créé\n";
}

// 4. Configuration par défaut
echo "\n[4] Configuration par défaut...\n";
$configKeys = array(
    'PDE_BATCH_SIZE' => 500,
    'PDE_CSV_DELIMITER' => ';',
    'PDE_CSV_ENCLOSURE' => '"',
    'PDE_CSV_UTF8_BOM' => 1,
    'PDE_CREATE_ZIP' => 1,
    'PDE_DOWNLOAD_TTL' => 24,
    'PDE_DELETE_AFTER_DOWNLOAD' => 0,
    'PDE_INCLUDE_CUSTOM_COLUMNS' => 0,
    'PDE_INCLUDE_CUSTOM_TABLES' => 0,
    'PDE_ANONYMIZE_DATA' => 0,
    'PDE_AUTO_CLEANUP_DAYS' => 7,
);

foreach ($configKeys as $key => $value) {
    $existing = Configuration::get($key);
    if ($existing === false) {
        Configuration::updateValue($key, $value);
        echo "    ✓ $key = $value\n";
    } else {
        echo "    - $key déjà configuré ($existing)\n";
    }
}

// Générer token CRON si absent
if (!Configuration::get('PDE_CRON_TOKEN')) {
    $token = bin2hex(random_bytes(32));
    Configuration::updateValue('PDE_CRON_TOKEN', $token);
    echo "    ✓ PDE_CRON_TOKEN généré\n";
}

// Résumé
echo "\n=======================================================\n";
echo "   INSTALLATION TERMINÉE\n";
echo "=======================================================\n\n";

if (!empty($errors)) {
    echo "ERREURS:\n";
    foreach ($errors as $error) {
        echo "  ✗ $error\n";
    }
    echo "\n";
}

echo "SUCCÈS:\n";
foreach ($success as $item) {
    echo "  ✓ $item\n";
}

echo "\n";
if (empty($errors)) {
    echo "✓ Installation réussie.\n";
    echo "  Vous pouvez maintenant utiliser le module depuis :\n";
    echo "  Paramètres avancés > Data Exporter\n";
} else {
    echo "✗ Installation incomplète. Corrigez les erreurs ci-dessus.\n";
}

echo "\n";
