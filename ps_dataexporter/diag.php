<?php
/**
 * PS Data Exporter - Script de diagnostic
 *
 * Usage: Accéder via le navigateur avec le token de sécurité
 * URL: /modules/ps_dataexporter/diag.php?token=VOTRE_TOKEN
 *
 * Le token doit correspondre à PDE_CRON_TOKEN dans la configuration
 */

// Initialiser PrestaShop
$psRoot = dirname(dirname(dirname(__FILE__)));
require_once $psRoot . '/config/config.inc.php';

// Vérification du token de sécurité
$token = Tools::getValue('token');
$expectedToken = Configuration::get('PDE_CRON_TOKEN');

if (empty($expectedToken) || $token !== $expectedToken) {
    header('HTTP/1.1 403 Forbidden');
    die('Accès refusé. Token invalide.');
}

// Headers
header('Content-Type: text/html; charset=utf-8');

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
 * Compte les enregistrements d'une table
 */
function countRows($tableName)
{
    if (!tableExists($tableName)) {
        return 'Table inexistante';
    }
    return (int) Db::getInstance()->getValue(
        'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . pSQL($tableName) . '`'
    );
}

// Collecter les informations de diagnostic
$diag = array();

// 1. Informations système
$diag['system'] = array(
    'php_version' => PHP_VERSION,
    'prestashop_version' => _PS_VERSION_,
    'mysql_version' => Db::getInstance()->getVersion(),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
);

// 2. Vérification des tables
$tables = array('pde_export_job', 'pde_export_file', 'pde_export_log', 'pde_geo_map');
$diag['tables'] = array();
foreach ($tables as $table) {
    $diag['tables'][$table] = array(
        'exists' => tableExists($table),
        'rows' => countRows($table),
    );
}

// 3. Configuration du module
$configKeys = array(
    'PDE_BATCH_SIZE',
    'PDE_CSV_DELIMITER',
    'PDE_CSV_ENCLOSURE',
    'PDE_CSV_UTF8_BOM',
    'PDE_CREATE_ZIP',
    'PDE_DOWNLOAD_TTL',
    'PDE_DELETE_AFTER_DOWNLOAD',
    'PDE_CRON_TOKEN',
    'PDE_INCLUDE_CUSTOM_COLUMNS',
    'PDE_INCLUDE_CUSTOM_TABLES',
    'PDE_ANONYMIZE_DATA',
    'PDE_AUTO_CLEANUP_DAYS',
);
$diag['config'] = array();
foreach ($configKeys as $key) {
    $value = Configuration::get($key);
    // Masquer le token
    if ($key === 'PDE_CRON_TOKEN' && $value) {
        $value = substr($value, 0, 8) . '...';
    }
    $diag['config'][$key] = $value !== false ? $value : '(non défini)';
}

// 4. Vérification du répertoire exports
$exportDir = _PS_MODULE_DIR_ . 'ps_dataexporter/exports/';
$diag['exports_dir'] = array(
    'path' => $exportDir,
    'exists' => is_dir($exportDir),
    'writable' => is_writable($exportDir),
);

// 5. Vérification de l'onglet admin
$tabId = (int) Tab::getIdFromClassName('AdminPsDataExporter');
$diag['admin_tab'] = array(
    'exists' => $tabId > 0,
    'id' => $tabId,
);

// 6. Jobs récents
$diag['recent_jobs'] = array();
if (tableExists('pde_export_job')) {
    $jobs = Db::getInstance()->executeS(
        'SELECT id_export_job, export_type, export_level, export_mode, status,
                total_records, processed_records, date_add, completed_at
         FROM `' . _DB_PREFIX_ . 'pde_export_job`
         ORDER BY date_add DESC
         LIMIT 5'
    );
    $diag['recent_jobs'] = $jobs ? $jobs : array();
}

// 7. Fichiers du module
$requiredFiles = array(
    'ps_dataexporter.php',
    'config.xml',
    'controllers/admin/AdminPsDataExporterController.php',
    'classes/ExportJob.php',
    'classes/Exporters/BatchExporter.php',
    'classes/Filters/QueryBuilder.php',
    'classes/Filters/FilterBuilder.php',
    'classes/Writers/CsvWriter.php',
    'classes/Services/ExportPlanBuilder.php',
    'classes/Services/SchemaInspector.php',
);
$diag['files'] = array();
foreach ($requiredFiles as $file) {
    $fullPath = _PS_MODULE_DIR_ . 'ps_dataexporter/' . $file;
    $diag['files'][$file] = file_exists($fullPath);
}

// 8. Extensions PHP requises
$requiredExtensions = array('zip', 'json', 'pdo', 'pdo_mysql');
$diag['extensions'] = array();
foreach ($requiredExtensions as $ext) {
    $diag['extensions'][$ext] = extension_loaded($ext);
}

// Affichage HTML
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>PS Data Exporter - Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        h1 { color: #333; border-bottom: 2px solid #25b9d7; padding-bottom: 10px; }
        h2 { color: #25b9d7; margin-top: 30px; }
        .section { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        table { border-collapse: collapse; width: 100%; }
        th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .ok { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .status-pending { background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; }
        .status-running { background: #17a2b8; color: #fff; padding: 2px 8px; border-radius: 3px; }
        .status-completed { background: #28a745; color: #fff; padding: 2px 8px; border-radius: 3px; }
        .status-failed { background: #dc3545; color: #fff; padding: 2px 8px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>PS Data Exporter - Diagnostic</h1>
    <p>Date: <?php echo date('Y-m-d H:i:s'); ?></p>

    <div class="section">
        <h2>Système</h2>
        <table>
            <?php foreach ($diag['system'] as $key => $value): ?>
            <tr>
                <th><?php echo htmlspecialchars($key); ?></th>
                <td><?php echo htmlspecialchars($value); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="section">
        <h2>Tables de base de données</h2>
        <table>
            <tr><th>Table</th><th>Existe</th><th>Enregistrements</th></tr>
            <?php foreach ($diag['tables'] as $table => $info): ?>
            <tr>
                <td><?php echo _DB_PREFIX_ . htmlspecialchars($table); ?></td>
                <td class="<?php echo $info['exists'] ? 'ok' : 'error'; ?>">
                    <?php echo $info['exists'] ? 'OUI' : 'NON'; ?>
                </td>
                <td><?php echo htmlspecialchars($info['rows']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="section">
        <h2>Configuration</h2>
        <table>
            <?php foreach ($diag['config'] as $key => $value): ?>
            <tr>
                <th><?php echo htmlspecialchars($key); ?></th>
                <td><?php echo htmlspecialchars($value); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="section">
        <h2>Répertoire exports</h2>
        <table>
            <tr><th>Chemin</th><td><?php echo htmlspecialchars($diag['exports_dir']['path']); ?></td></tr>
            <tr>
                <th>Existe</th>
                <td class="<?php echo $diag['exports_dir']['exists'] ? 'ok' : 'error'; ?>">
                    <?php echo $diag['exports_dir']['exists'] ? 'OUI' : 'NON'; ?>
                </td>
            </tr>
            <tr>
                <th>Accessible en écriture</th>
                <td class="<?php echo $diag['exports_dir']['writable'] ? 'ok' : 'error'; ?>">
                    <?php echo $diag['exports_dir']['writable'] ? 'OUI' : 'NON'; ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Onglet Admin</h2>
        <table>
            <tr>
                <th>Installé</th>
                <td class="<?php echo $diag['admin_tab']['exists'] ? 'ok' : 'error'; ?>">
                    <?php echo $diag['admin_tab']['exists'] ? 'OUI (ID: ' . $diag['admin_tab']['id'] . ')' : 'NON'; ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Extensions PHP</h2>
        <table>
            <?php foreach ($diag['extensions'] as $ext => $loaded): ?>
            <tr>
                <th><?php echo htmlspecialchars($ext); ?></th>
                <td class="<?php echo $loaded ? 'ok' : 'error'; ?>">
                    <?php echo $loaded ? 'OK' : 'MANQUANTE'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="section">
        <h2>Fichiers du module</h2>
        <table>
            <?php foreach ($diag['files'] as $file => $exists): ?>
            <tr>
                <td><?php echo htmlspecialchars($file); ?></td>
                <td class="<?php echo $exists ? 'ok' : 'error'; ?>">
                    <?php echo $exists ? 'OK' : 'MANQUANT'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="section">
        <h2>Jobs récents (5 derniers)</h2>
        <?php if (empty($diag['recent_jobs'])): ?>
            <p>Aucun job trouvé.</p>
        <?php else: ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Type</th>
                <th>Niveau</th>
                <th>Mode</th>
                <th>Status</th>
                <th>Progression</th>
                <th>Date</th>
            </tr>
            <?php foreach ($diag['recent_jobs'] as $job): ?>
            <tr>
                <td><?php echo (int) $job['id_export_job']; ?></td>
                <td><?php echo htmlspecialchars($job['export_type']); ?></td>
                <td><?php echo htmlspecialchars($job['export_level']); ?></td>
                <td><?php echo htmlspecialchars($job['export_mode']); ?></td>
                <td><span class="status-<?php echo htmlspecialchars($job['status']); ?>">
                    <?php echo htmlspecialchars($job['status']); ?>
                </span></td>
                <td><?php echo (int) $job['processed_records']; ?> / <?php echo (int) $job['total_records']; ?></td>
                <td><?php echo htmlspecialchars($job['date_add']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>

</body>
</html>
