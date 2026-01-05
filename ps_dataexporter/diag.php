<?php
/**
 * PS Data Exporter - Script de diagnostic
 *
 * Usage: Accéder via le navigateur ou exécuter en CLI
 * CLI: php modules/ps_dataexporter/diag.php
 *
 * IMPORTANT: Supprimer ce fichier après utilisation en production !
 */

// Vérification sécurité minimale
if (php_sapi_name() !== 'cli') {
    // Si accès web, vérifier qu'on est admin PrestaShop
    $configPath = dirname(dirname(dirname(__FILE__))) . '/config/config.inc.php';
    if (file_exists($configPath)) {
        require_once $configPath;
        if (!defined('_PS_VERSION_')) {
            die('PrestaShop non chargé');
        }
        // Vérifier si employé connecté
        $context = Context::getContext();
        if (!$context->employee || !$context->employee->id) {
            die('Accès refusé - Connectez-vous au back-office');
        }
    } else {
        die('Configuration PrestaShop non trouvée');
    }
}

header('Content-Type: text/plain; charset=utf-8');

echo "=======================================================\n";
echo "   PS Data Exporter - Diagnostic v1.0\n";
echo "=======================================================\n\n";

$errors = array();
$warnings = array();
$success = array();

// 1. Vérification PHP
echo "[1] Version PHP\n";
$phpVersion = PHP_VERSION;
if (version_compare($phpVersion, '7.2.0', '>=') && version_compare($phpVersion, '8.0.0', '<')) {
    $success[] = "PHP $phpVersion OK (compatible)";
    echo "    ✓ PHP $phpVersion\n";
} elseif (version_compare($phpVersion, '7.2.0', '<')) {
    $errors[] = "PHP $phpVersion trop ancien (minimum 7.2)";
    echo "    ✗ PHP $phpVersion (minimum 7.2 requis)\n";
} else {
    $warnings[] = "PHP $phpVersion peut ne pas être compatible (conçu pour 7.2-7.4)";
    echo "    ⚠ PHP $phpVersion (conçu pour 7.2-7.4)\n";
}

// 2. Extensions PHP
echo "\n[2] Extensions PHP requises\n";
$requiredExtensions = array('pdo_mysql', 'json', 'mbstring', 'zip');
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        $success[] = "Extension $ext chargée";
        echo "    ✓ $ext\n";
    } else {
        $errors[] = "Extension $ext manquante";
        echo "    ✗ $ext (MANQUANT)\n";
    }
}

// 3. Vérification PrestaShop
echo "\n[3] PrestaShop\n";
if (defined('_PS_VERSION_')) {
    $psVersion = _PS_VERSION_;
    if (version_compare($psVersion, '1.7.6.0', '>=') && version_compare($psVersion, '1.7.7.0', '<')) {
        $success[] = "PrestaShop $psVersion OK";
        echo "    ✓ PrestaShop $psVersion\n";
    } elseif (version_compare($psVersion, '1.7.6.0', '>=')) {
        $warnings[] = "PrestaShop $psVersion (testé sur 1.7.6.x)";
        echo "    ⚠ PrestaShop $psVersion (conçu pour 1.7.6.x)\n";
    } else {
        $errors[] = "PrestaShop $psVersion trop ancien";
        echo "    ✗ PrestaShop $psVersion (minimum 1.7.6.0)\n";
    }
} else {
    echo "    - Non chargé (normal en CLI sans bootstrap)\n";
}

// 4. Structure du module
echo "\n[4] Structure du module\n";
$moduleDir = dirname(__FILE__);
$requiredFiles = array(
    'ps_dataexporter.php' => 'Fichier principal',
    'config.xml' => 'Configuration',
    'classes/ExportJob.php' => 'Modèle job',
    'classes/Services/SchemaInspector.php' => 'Inspecteur schéma',
    'classes/Services/ExportPlanBuilder.php' => 'Constructeur plan',
    'classes/Services/BatchExporter.php' => 'Exporteur par lots',
    'classes/Filters/FilterBuilder.php' => 'Constructeur filtres',
    'classes/Filters/QueryBuilder.php' => 'Constructeur requêtes',
    'classes/Writers/CsvWriter.php' => 'Écrivain CSV',
    'controllers/admin/AdminPsDataExporterController.php' => 'Contrôleur admin',
);

foreach ($requiredFiles as $file => $desc) {
    $path = $moduleDir . '/' . $file;
    if (file_exists($path)) {
        $success[] = "$file présent";
        echo "    ✓ $file\n";
    } else {
        $errors[] = "$file manquant ($desc)";
        echo "    ✗ $file (MANQUANT - $desc)\n";
    }
}

// 5. Répertoires et permissions
echo "\n[5] Répertoires et permissions\n";
$requiredDirs = array(
    'exports' => 'Répertoire exports',
    'views/templates/admin' => 'Templates admin',
    'views/css' => 'CSS',
    'views/js' => 'JavaScript',
);

foreach ($requiredDirs as $dir => $desc) {
    $path = $moduleDir . '/' . $dir;
    if (is_dir($path)) {
        if (is_writable($path)) {
            $success[] = "$dir accessible en écriture";
            echo "    ✓ $dir (writable)\n";
        } else {
            $warnings[] = "$dir non accessible en écriture";
            echo "    ⚠ $dir (non writable - chmod 755 requis)\n";
        }
    } else {
        $errors[] = "$dir manquant";
        echo "    ✗ $dir (MANQUANT)\n";
    }
}

// 6. Fichiers de sécurité
echo "\n[6] Fichiers de sécurité (index.php)\n";
$securityDirs = array('classes', 'controllers', 'views', 'sql', 'exports');
$missingIndex = 0;
foreach ($securityDirs as $dir) {
    $indexPath = $moduleDir . '/' . $dir . '/index.php';
    if (!file_exists($indexPath)) {
        $missingIndex++;
    }
}
if ($missingIndex === 0) {
    $success[] = "Tous les index.php présents";
    echo "    ✓ Tous les fichiers index.php présents\n";
} else {
    $warnings[] = "$missingIndex fichiers index.php manquants";
    echo "    ⚠ $missingIndex fichiers index.php manquants\n";
}

// 7. Protection exports
echo "\n[7] Protection répertoire exports\n";
$htaccessPath = $moduleDir . '/exports/.htaccess';
if (file_exists($htaccessPath)) {
    $content = file_get_contents($htaccessPath);
    if (strpos($content, 'Deny') !== false || strpos($content, 'denied') !== false) {
        $success[] = "Protection .htaccess active";
        echo "    ✓ .htaccess avec protection Deny présent\n";
    } else {
        $warnings[] = ".htaccess sans règle Deny";
        echo "    ⚠ .htaccess présent mais sans règle Deny\n";
    }
} else {
    $errors[] = ".htaccess manquant dans exports/";
    echo "    ✗ .htaccess MANQUANT (sécurité critique!)\n";
}

// 8. Tables de base de données
echo "\n[8] Tables base de données\n";
if (defined('_DB_PREFIX_')) {
    $tables = array(
        'pde_export_job' => 'Jobs d\'export',
        'pde_export_file' => 'Fichiers générés',
        'pde_geo_map' => 'Mapping géographique',
        'pde_export_log' => 'Logs',
    );

    foreach ($tables as $table => $desc) {
        $sql = "SHOW TABLES LIKE '" . _DB_PREFIX_ . "$table'";
        $result = Db::getInstance()->executeS($sql);
        if (!empty($result)) {
            $count = Db::getInstance()->getValue("SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "$table`");
            $success[] = "Table $table OK ($count lignes)";
            echo "    ✓ $table ($count lignes)\n";
        } else {
            $errors[] = "Table $table manquante";
            echo "    ✗ $table MANQUANTE - Réinstaller le module\n";
        }
    }

    // Vérifier le mapping géographique
    $geoCount = Db::getInstance()->getValue("SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "pde_geo_map` WHERE country_iso = 'FR'");
    if ($geoCount >= 95) {
        $success[] = "Mapping FR complet ($geoCount départements)";
        echo "    ✓ Mapping FR: $geoCount départements\n";
    } elseif ($geoCount > 0) {
        $warnings[] = "Mapping FR incomplet ($geoCount/101)";
        echo "    ⚠ Mapping FR incomplet: $geoCount/101 départements\n";
    } else {
        $errors[] = "Mapping FR vide";
        echo "    ✗ Mapping FR vide - Réinstaller le module\n";
    }
} else {
    echo "    - Non vérifiable (DB non connectée)\n";
}

// 9. Configuration
echo "\n[9] Configuration du module\n";
if (defined('_PS_VERSION_')) {
    $configKeys = array(
        'PDE_BATCH_SIZE' => 'Taille des lots',
        'PDE_CSV_DELIMITER' => 'Délimiteur CSV',
        'PDE_DOWNLOAD_TTL' => 'TTL téléchargement (heures)',
        'PDE_CRON_TOKEN' => 'Token CRON',
    );

    foreach ($configKeys as $key => $desc) {
        $value = Configuration::get($key);
        if ($value !== false && $value !== null) {
            if ($key === 'PDE_CRON_TOKEN') {
                $display = strlen($value) > 0 ? '***' . substr($value, -4) : '(vide)';
            } else {
                $display = $value;
            }
            $success[] = "$key configuré";
            echo "    ✓ $key = $display\n";
        } else {
            $warnings[] = "$key non configuré";
            echo "    ⚠ $key non configuré (défaut sera utilisé)\n";
        }
    }
} else {
    echo "    - Non vérifiable (PrestaShop non chargé)\n";
}

// 10. Syntaxe PHP des fichiers
echo "\n[10] Syntaxe PHP\n";
$phpFiles = array(
    'ps_dataexporter.php',
    'classes/ExportJob.php',
    'classes/Services/SchemaInspector.php',
    'classes/Services/ExportPlanBuilder.php',
    'classes/Services/BatchExporter.php',
    'classes/Filters/FilterBuilder.php',
    'classes/Filters/QueryBuilder.php',
    'classes/Writers/CsvWriter.php',
    'controllers/admin/AdminPsDataExporterController.php',
);

$syntaxOk = true;
foreach ($phpFiles as $file) {
    $path = $moduleDir . '/' . $file;
    if (file_exists($path)) {
        $output = array();
        $returnVar = 0;
        exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $returnVar);
        if ($returnVar !== 0) {
            $syntaxOk = false;
            $errors[] = "Erreur syntaxe dans $file";
            echo "    ✗ $file: " . implode(' ', $output) . "\n";
        }
    }
}
if ($syntaxOk) {
    $success[] = "Syntaxe PHP OK";
    echo "    ✓ Tous les fichiers PHP valides\n";
}

// Résumé
echo "\n=======================================================\n";
echo "   RÉSUMÉ\n";
echo "=======================================================\n\n";

echo "Succès : " . count($success) . "\n";
echo "Avertissements : " . count($warnings) . "\n";
echo "Erreurs : " . count($errors) . "\n\n";

if (!empty($errors)) {
    echo "ERREURS À CORRIGER:\n";
    foreach ($errors as $error) {
        echo "  ✗ $error\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "AVERTISSEMENTS:\n";
    foreach ($warnings as $warning) {
        echo "  ⚠ $warning\n";
    }
    echo "\n";
}

if (empty($errors)) {
    echo "✓ Le module est prêt à être installé/utilisé.\n";
} else {
    echo "✗ Corrigez les erreurs avant d'installer le module.\n";
}

echo "\n=======================================================\n";
echo "   Fin du diagnostic\n";
echo "=======================================================\n";
