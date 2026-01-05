<?php
/**
 * PS Data Exporter - Script de réparation/réinstallation
 *
 * Usage: Accéder via le navigateur avec le token de sécurité
 * URL: /modules/ps_dataexporter/repair.php?token=VOTRE_TOKEN&action=ACTION
 *
 * Actions disponibles:
 * - check : Vérifie l'état du module (par défaut)
 * - tables : Recrée les tables manquantes
 * - config : Réinitialise la configuration
 * - tab : Recrée l'onglet admin
 * - full : Réparation complète
 * - cleanup : Nettoie les jobs/fichiers orphelins
 */

// Initialiser PrestaShop
$psRoot = dirname(dirname(dirname(__FILE__)));
require_once $psRoot . '/config/config.inc.php';

// Vérification du token de sécurité
$token = Tools::getValue('token');
$expectedToken = Configuration::get('PDE_CRON_TOKEN');

// Si pas de token configuré, en générer un temporaire
if (empty($expectedToken)) {
    // Permettre l'accès avec un token basé sur _COOKIE_KEY_ pour la première installation
    $tempToken = substr(md5(_COOKIE_KEY_ . 'pde_repair'), 0, 16);
    if ($token !== $tempToken) {
        header('HTTP/1.1 403 Forbidden');
        echo "Accès refusé. Token invalide.\n";
        echo "Token temporaire (première installation): " . $tempToken . "\n";
        exit;
    }
} elseif ($token !== $expectedToken) {
    header('HTTP/1.1 403 Forbidden');
    die('Accès refusé. Token invalide.');
}

// Headers
header('Content-Type: text/plain; charset=utf-8');

$action = Tools::getValue('action', 'check');
$results = array();

echo "=== PS Data Exporter - Réparation ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Action: " . $action . "\n";
echo "=====================================\n\n";

/**
 * Vérifie si une table existe
 */
function tableExists($tableName)
{
    $result = Db::getInstance()->executeS(
        'SHOW TABLES LIKE \'' . _DB_PREFIX_ . pSQL($tableName) . '\''
    );
    return !empty($result);
}

/**
 * Crée les tables du module
 */
function createTables()
{
    $results = array();

    // Table des jobs
    if (!tableExists('pde_export_job')) {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pde_export_job` (
            `id_export_job` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_employee` INT(11) UNSIGNED NOT NULL,
            `id_shop` INT(11) UNSIGNED DEFAULT NULL,
            `export_type` VARCHAR(32) NOT NULL DEFAULT "orders",
            `export_level` VARCHAR(32) NOT NULL DEFAULT "essential",
            `export_mode` VARCHAR(32) NOT NULL DEFAULT "relational",
            `params_json` LONGTEXT NOT NULL,
            `schema_snapshot` LONGTEXT DEFAULT NULL,
            `status` VARCHAR(32) NOT NULL DEFAULT "pending",
            `total_records` INT(11) UNSIGNED DEFAULT 0,
            `processed_records` INT(11) UNSIGNED DEFAULT 0,
            `current_entity` VARCHAR(64) DEFAULT NULL,
            `cursors_json` TEXT DEFAULT NULL,
            `error_message` TEXT DEFAULT NULL,
            `started_at` DATETIME DEFAULT NULL,
            `completed_at` DATETIME DEFAULT NULL,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_export_job`),
            KEY `idx_status` (`status`),
            KEY `idx_employee` (`id_employee`),
            KEY `idx_date_add` (`date_add`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        if (Db::getInstance()->execute($sql)) {
            $results[] = "[OK] Table pde_export_job créée";
        } else {
            $results[] = "[ERREUR] Échec création pde_export_job: " . Db::getInstance()->getMsgError();
        }
    } else {
        $results[] = "[OK] Table pde_export_job existe déjà";
    }

    // Table des fichiers
    if (!tableExists('pde_export_file')) {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pde_export_file` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        if (Db::getInstance()->execute($sql)) {
            $results[] = "[OK] Table pde_export_file créée";
        } else {
            $results[] = "[ERREUR] Échec création pde_export_file: " . Db::getInstance()->getMsgError();
        }
    } else {
        $results[] = "[OK] Table pde_export_file existe déjà";
    }

    // Table geo_map
    if (!tableExists('pde_geo_map')) {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pde_geo_map` (
            `id_geo_map` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `country_iso` VARCHAR(3) NOT NULL DEFAULT "FR",
            `dept_code` VARCHAR(5) NOT NULL,
            `dept_name` VARCHAR(128) NOT NULL,
            `region_code` VARCHAR(5) NOT NULL,
            `region_name` VARCHAR(128) NOT NULL,
            PRIMARY KEY (`id_geo_map`),
            UNIQUE KEY `idx_dept` (`country_iso`, `dept_code`),
            KEY `idx_region` (`region_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        if (Db::getInstance()->execute($sql)) {
            $results[] = "[OK] Table pde_geo_map créée";
            // Insérer les données
            insertGeoData();
            $results[] = "[OK] Données géographiques insérées";
        } else {
            $results[] = "[ERREUR] Échec création pde_geo_map: " . Db::getInstance()->getMsgError();
        }
    } else {
        $results[] = "[OK] Table pde_geo_map existe déjà";
    }

    // Table logs
    if (!tableExists('pde_export_log')) {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pde_export_log` (
            `id_log` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_export_job` INT(11) UNSIGNED NOT NULL,
            `level` VARCHAR(16) NOT NULL DEFAULT "info",
            `message` TEXT NOT NULL,
            `context_json` TEXT DEFAULT NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_log`),
            KEY `idx_job` (`id_export_job`),
            KEY `idx_level` (`level`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        if (Db::getInstance()->execute($sql)) {
            $results[] = "[OK] Table pde_export_log créée";
        } else {
            $results[] = "[ERREUR] Échec création pde_export_log: " . Db::getInstance()->getMsgError();
        }
    } else {
        $results[] = "[OK] Table pde_export_log existe déjà";
    }

    return $results;
}

/**
 * Insère les données géographiques FR
 */
function insertGeoData()
{
    $sqlFile = _PS_MODULE_DIR_ . 'ps_dataexporter/sql/install.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        // Extraire uniquement la partie INSERT
        if (preg_match('/INSERT INTO.*?;/s', $sql, $matches)) {
            $insertSql = str_replace('PREFIX_', _DB_PREFIX_, $matches[0]);
            Db::getInstance()->execute($insertSql);
        }
    }
}

/**
 * Initialise la configuration
 */
function initConfig()
{
    $results = array();

    $defaults = array(
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

    foreach ($defaults as $key => $value) {
        $current = Configuration::get($key);
        if ($current === false) {
            Configuration::updateValue($key, $value);
            $results[] = "[OK] Configuration $key initialisée à: $value";
        } else {
            $results[] = "[OK] Configuration $key existe: $current";
        }
    }

    // Token cron
    $cronToken = Configuration::get('PDE_CRON_TOKEN');
    if (empty($cronToken)) {
        $cronToken = bin2hex(random_bytes(32));
        Configuration::updateValue('PDE_CRON_TOKEN', $cronToken);
        $results[] = "[OK] Token cron généré: " . substr($cronToken, 0, 8) . "...";
    }

    return $results;
}

/**
 * Crée l'onglet admin
 */
function createAdminTab()
{
    $results = array();

    $tabId = (int) Tab::getIdFromClassName('AdminPsDataExporter');

    if ($tabId > 0) {
        $results[] = "[OK] Onglet admin existe déjà (ID: $tabId)";
        return $results;
    }

    $tab = new Tab();
    $tab->class_name = 'AdminPsDataExporter';
    $tab->module = 'ps_dataexporter';
    $tab->id_parent = (int) Tab::getIdFromClassName('AdminAdvancedParameters');
    $tab->icon = 'cloud_download';

    $languages = Language::getLanguages(false);
    foreach ($languages as $lang) {
        $tab->name[$lang['id_lang']] = 'Data Exporter';
    }

    if ($tab->add()) {
        $results[] = "[OK] Onglet admin créé (ID: " . $tab->id . ")";
    } else {
        $results[] = "[ERREUR] Échec création onglet admin";
    }

    return $results;
}

/**
 * Crée le répertoire exports
 */
function createExportsDir()
{
    $results = array();
    $exportDir = _PS_MODULE_DIR_ . 'ps_dataexporter/exports/';

    if (!is_dir($exportDir)) {
        if (mkdir($exportDir, 0755, true)) {
            $results[] = "[OK] Répertoire exports créé";
        } else {
            $results[] = "[ERREUR] Échec création répertoire exports";
        }
    } else {
        $results[] = "[OK] Répertoire exports existe";
    }

    // .htaccess
    $htaccess = $exportDir . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Order Deny,Allow\nDeny from all\n");
        $results[] = "[OK] .htaccess créé";
    }

    // index.php
    $index = $exportDir . 'index.php';
    if (!file_exists($index)) {
        file_put_contents($index, "<?php\nheader('Location: ../../../');\nexit;\n");
        $results[] = "[OK] index.php créé";
    }

    return $results;
}

/**
 * Nettoie les jobs/fichiers orphelins
 */
function cleanupOrphans()
{
    $results = array();

    // Fichiers sans job parent
    $orphanFiles = Db::getInstance()->executeS(
        'SELECT f.* FROM `' . _DB_PREFIX_ . 'pde_export_file` f
         LEFT JOIN `' . _DB_PREFIX_ . 'pde_export_job` j ON f.id_export_job = j.id_export_job
         WHERE j.id_export_job IS NULL'
    );

    if ($orphanFiles) {
        foreach ($orphanFiles as $file) {
            if (file_exists($file['filepath'])) {
                @unlink($file['filepath']);
            }
        }
        Db::getInstance()->execute(
            'DELETE f FROM `' . _DB_PREFIX_ . 'pde_export_file` f
             LEFT JOIN `' . _DB_PREFIX_ . 'pde_export_job` j ON f.id_export_job = j.id_export_job
             WHERE j.id_export_job IS NULL'
        );
        $results[] = "[OK] " . count($orphanFiles) . " fichier(s) orphelin(s) supprimé(s)";
    }

    // Logs sans job parent
    Db::getInstance()->execute(
        'DELETE l FROM `' . _DB_PREFIX_ . 'pde_export_log` l
         LEFT JOIN `' . _DB_PREFIX_ . 'pde_export_job` j ON l.id_export_job = j.id_export_job
         WHERE j.id_export_job IS NULL'
    );

    // Jobs bloqués depuis plus de 24h
    $stuckJobs = Db::getInstance()->executeS(
        'SELECT id_export_job FROM `' . _DB_PREFIX_ . 'pde_export_job`
         WHERE status = "running" AND date_upd < DATE_SUB(NOW(), INTERVAL 24 HOUR)'
    );

    if ($stuckJobs) {
        foreach ($stuckJobs as $job) {
            Db::getInstance()->update('pde_export_job', array(
                'status' => 'failed',
                'error_message' => 'Timeout: job bloqué depuis plus de 24h',
            ), 'id_export_job = ' . (int) $job['id_export_job']);
        }
        $results[] = "[OK] " . count($stuckJobs) . " job(s) bloqué(s) marqué(s) comme échoué(s)";
    }

    // Fichiers expirés
    Db::getInstance()->execute(
        'DELETE FROM `' . _DB_PREFIX_ . 'pde_export_file`
         WHERE download_expires < DATE_SUB(NOW(), INTERVAL 7 DAY)'
    );

    $results[] = "[OK] Nettoyage terminé";

    return $results;
}

// Exécuter l'action demandée
switch ($action) {
    case 'check':
        echo "Mode vérification (aucune modification)\n\n";

        $tables = array('pde_export_job', 'pde_export_file', 'pde_export_log', 'pde_geo_map');
        foreach ($tables as $table) {
            echo ($tableExists($table) ? "[OK]" : "[MANQUANT]") . " Table $table\n";
        }

        $tabId = (int) Tab::getIdFromClassName('AdminPsDataExporter');
        echo ($tabId > 0 ? "[OK]" : "[MANQUANT]") . " Onglet admin\n";

        $exportDir = _PS_MODULE_DIR_ . 'ps_dataexporter/exports/';
        echo (is_dir($exportDir) ? "[OK]" : "[MANQUANT]") . " Répertoire exports\n";
        echo (is_writable($exportDir) ? "[OK]" : "[ERREUR]") . " Permissions écriture\n";

        echo "\nPour réparer: ?token=...&action=full\n";
        break;

    case 'tables':
        echo "Création des tables...\n\n";
        $results = createTables();
        echo implode("\n", $results) . "\n";
        break;

    case 'config':
        echo "Initialisation de la configuration...\n\n";
        $results = initConfig();
        echo implode("\n", $results) . "\n";
        break;

    case 'tab':
        echo "Création de l'onglet admin...\n\n";
        $results = createAdminTab();
        echo implode("\n", $results) . "\n";
        break;

    case 'exports':
        echo "Création du répertoire exports...\n\n";
        $results = createExportsDir();
        echo implode("\n", $results) . "\n";
        break;

    case 'cleanup':
        echo "Nettoyage des données orphelines...\n\n";
        $results = cleanupOrphans();
        echo implode("\n", $results) . "\n";
        break;

    case 'full':
        echo "Réparation complète...\n\n";

        echo "1. Tables\n";
        $results = createTables();
        echo implode("\n", $results) . "\n\n";

        echo "2. Configuration\n";
        $results = initConfig();
        echo implode("\n", $results) . "\n\n";

        echo "3. Onglet admin\n";
        $results = createAdminTab();
        echo implode("\n", $results) . "\n\n";

        echo "4. Répertoire exports\n";
        $results = createExportsDir();
        echo implode("\n", $results) . "\n\n";

        echo "Réparation terminée!\n";
        break;

    default:
        echo "Action inconnue: $action\n";
        echo "Actions disponibles: check, tables, config, tab, exports, cleanup, full\n";
}

echo "\n=== Fin ===\n";
