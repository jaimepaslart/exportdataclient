<?php
/**
 * PS Data Exporter - Script de purge complète
 *
 * Ce script supprime TOUTES les traces d'export :
 * - Fichiers CSV et ZIP
 * - Entrées en base de données (jobs, fichiers, logs)
 * - Conserve uniquement la configuration et le mapping géographique
 *
 * Usage CLI: php modules/ps_dataexporter/purge.php [--confirm]
 * Usage Web: Accessible uniquement aux super-admins PrestaShop
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
        // Vérifier si super admin
        if (!$context->employee->isSuperAdmin()) {
            die('Accès refusé - Super admin requis');
        }
    } else {
        die('Configuration PrestaShop non trouvée');
    }
    $confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';
} else {
    // CLI mode
    if (!file_exists(dirname(dirname(dirname(__FILE__))) . '/config/config.inc.php')) {
        die("Erreur: Exécutez depuis le répertoire PrestaShop\n");
    }
    require_once dirname(dirname(dirname(__FILE__))) . '/config/config.inc.php';
    $confirmed = in_array('--confirm', $argv);
}

header('Content-Type: text/plain; charset=utf-8');

echo "=======================================================\n";
echo "   PS Data Exporter - PURGE COMPLETE\n";
echo "=======================================================\n\n";

if (!$confirmed) {
    echo "ATTENTION: Cette opération va supprimer :\n";
    echo "  - Tous les fichiers d'export (CSV, ZIP)\n";
    echo "  - Tous les jobs d'export\n";
    echo "  - Tous les logs d'export\n\n";

    $jobCount = Db::getInstance()->getValue("SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "pde_export_job`");
    $fileCount = Db::getInstance()->getValue("SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "pde_export_file`");
    $logCount = Db::getInstance()->getValue("SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "pde_export_log`");

    echo "Données actuelles :\n";
    echo "  - $jobCount job(s) d'export\n";
    echo "  - $fileCount fichier(s)\n";
    echo "  - $logCount log(s)\n\n";

    if (php_sapi_name() === 'cli') {
        echo "Pour confirmer la purge, exécutez:\n";
        echo "  php " . $argv[0] . " --confirm\n\n";
    } else {
        echo "Pour confirmer la purge, ajoutez ?confirm=yes à l'URL\n\n";
    }

    echo "La configuration et le mapping géographique seront conservés.\n";
    exit;
}

echo "Purge en cours...\n\n";

$errors = array();
$success = array();

// 1. Supprimer les fichiers physiques
echo "[1] Suppression des fichiers physiques...\n";
$exportDir = dirname(__FILE__) . '/exports/';

if (is_dir($exportDir)) {
    $deletedFiles = 0;
    $deletedDirs = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($exportDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();
        $filename = $item->getFilename();

        // Conserver index.php et .htaccess
        if ($filename === 'index.php' || $filename === '.htaccess') {
            continue;
        }

        if ($item->isDir()) {
            if (@rmdir($path)) {
                $deletedDirs++;
            }
        } else {
            if (@unlink($path)) {
                $deletedFiles++;
            } else {
                $errors[] = "Impossible de supprimer: $path";
            }
        }
    }

    $success[] = "$deletedFiles fichier(s) et $deletedDirs répertoire(s) supprimés";
    echo "    ✓ $deletedFiles fichier(s) supprimé(s)\n";
    echo "    ✓ $deletedDirs répertoire(s) supprimé(s)\n";
} else {
    echo "    - Répertoire exports non trouvé\n";
}

// 2. Supprimer les logs
echo "\n[2] Suppression des logs...\n";
$logCount = Db::getInstance()->getValue("SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "pde_export_log`");
Db::getInstance()->execute("TRUNCATE TABLE `" . _DB_PREFIX_ . "pde_export_log`");
$success[] = "$logCount log(s) supprimé(s)";
echo "    ✓ $logCount log(s) supprimé(s)\n";

// 3. Supprimer les fichiers en base
echo "\n[3] Suppression des entrées fichiers...\n";
$fileCount = Db::getInstance()->getValue("SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "pde_export_file`");
Db::getInstance()->execute("TRUNCATE TABLE `" . _DB_PREFIX_ . "pde_export_file`");
$success[] = "$fileCount entrée(s) fichier supprimée(s)";
echo "    ✓ $fileCount entrée(s) supprimée(s)\n";

// 4. Supprimer les jobs
echo "\n[4] Suppression des jobs...\n";
$jobCount = Db::getInstance()->getValue("SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "pde_export_job`");
Db::getInstance()->execute("TRUNCATE TABLE `" . _DB_PREFIX_ . "pde_export_job`");
$success[] = "$jobCount job(s) supprimé(s)";
echo "    ✓ $jobCount job(s) supprimé(s)\n";

// 5. Réinitialiser les auto-increment
echo "\n[5] Réinitialisation des compteurs...\n";
Db::getInstance()->execute("ALTER TABLE `" . _DB_PREFIX_ . "pde_export_job` AUTO_INCREMENT = 1");
Db::getInstance()->execute("ALTER TABLE `" . _DB_PREFIX_ . "pde_export_file` AUTO_INCREMENT = 1");
Db::getInstance()->execute("ALTER TABLE `" . _DB_PREFIX_ . "pde_export_log` AUTO_INCREMENT = 1");
$success[] = "Compteurs réinitialisés";
echo "    ✓ Compteurs réinitialisés\n";

// Résumé
echo "\n=======================================================\n";
echo "   PURGE TERMINÉE\n";
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

echo "\n✓ Toutes les traces d'export ont été supprimées.\n";
echo "  Le mapping géographique et la configuration sont conservés.\n\n";

// Log de la purge (pour audit)
$employeeName = php_sapi_name() === 'cli' ? 'CLI' : Context::getContext()->employee->email;
Configuration::updateValue('PDE_LAST_PURGE', date('Y-m-d H:i:s') . ' par ' . $employeeName);

echo "Note: Cette action a été enregistrée.\n";
