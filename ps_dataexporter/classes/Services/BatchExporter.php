<?php
/**
 * BatchExporter - Exécute l'export par lots
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/ExportPlanBuilder.php';
require_once dirname(__FILE__) . '/../Filters/QueryBuilder.php';
require_once dirname(__FILE__) . '/../Writers/CsvWriter.php';

class PdeBatchExporter
{
    /** @var PdeExportJob */
    private $job;

    /** @var array */
    private $plan;

    /** @var array */
    private $filters;

    /** @var PdeQueryBuilder */
    private $queryBuilder;

    /** @var int */
    private $batchSize;

    /** @var string */
    private $exportDir;

    /** @var int */
    private $idLang;

    /** @var int|null */
    private $idShop;

    /** @var array */
    private $writers = array();

    /** @var int */
    private $totalProcessed = 0;

    /** @var float */
    private $startTime;

    /** @var int Max execution time per batch in seconds */
    private $maxExecutionTime = 25;

    public function __construct(PdeExportJob $job)
    {
        $this->job = $job;
        $this->filters = $job->getParams();
        $this->batchSize = (int) Configuration::get('PDE_BATCH_SIZE', null, null, null, 500);
        $this->exportDir = _PS_MODULE_DIR_ . 'ps_dataexporter/exports/' . $job->id . '/';
        $this->idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $this->idShop = $job->id_shop;

        // Créer le répertoire d'export
        if (!is_dir($this->exportDir)) {
            mkdir($this->exportDir, 0755, true);
        }

        // Initialiser le QueryBuilder
        $this->queryBuilder = new PdeQueryBuilder($this->idLang, $this->idShop);

        // Construire le plan d'export
        $planBuilder = new PdeExportPlanBuilder(
            !empty($this->filters['include_custom_columns']),
            !empty($this->filters['include_custom_tables'])
        );
        $this->plan = $planBuilder->buildPlan($job->export_type, $job->export_level);

        // Sauvegarder le schema snapshot si pas encore fait
        if (empty($job->schema_snapshot)) {
            $job->setSchemaSnapshot($this->plan['schema']);
            $job->update();
        }
    }

    /**
     * Exécute un lot d'export
     * @return array Status de l'exécution
     */
    public function runBatch()
    {
        $this->startTime = microtime(true);

        try {
            // Démarrer le job si pending
            if ($this->job->status === PdeExportJob::STATUS_PENDING) {
                $this->job->start();
                $this->job->log('info', 'Export démarré', array(
                    'type' => $this->job->export_type,
                    'level' => $this->job->export_level,
                    'mode' => $this->job->export_mode,
                ));
            }

            // Obtenir l'ordre d'export
            $planBuilder = new PdeExportPlanBuilder();
            $exportOrder = $planBuilder->getExportOrder($this->plan);

            // Trouver l'entité courante à traiter
            $currentEntity = $this->job->current_entity;
            $startIndex = 0;

            if ($currentEntity) {
                $index = array_search($currentEntity, $exportOrder);
                if ($index !== false) {
                    $startIndex = $index;
                }
            } else {
                // Première entité
                $currentEntity = $exportOrder[0];
                $this->job->current_entity = $currentEntity;
            }

            $processed = 0;
            $completed = false;

            // Traiter les entités
            for ($i = $startIndex; $i < count($exportOrder); $i++) {
                $entity = $exportOrder[$i];
                $entityConfig = $this->plan['entities'][$entity];

                // Mettre à jour l'entité courante
                if ($this->job->current_entity !== $entity) {
                    $this->job->current_entity = $entity;
                    $this->job->setCursor($entity, 0);
                }

                // Exporter l'entité
                $result = $this->exportEntity($entity, $entityConfig);
                $processed += $result['processed'];

                // Vérifier le temps
                if ($this->isTimeExceeded()) {
                    $this->job->updateProgress(
                        $this->job->processed_records + $processed,
                        $entity
                    );
                    return array(
                        'status' => 'running',
                        'processed' => $processed,
                        'entity' => $entity,
                        'message' => 'Batch terminé, en attente du prochain',
                    );
                }

                // Entité terminée ?
                if ($result['completed']) {
                    // Passer à l'entité suivante
                    if ($i + 1 < count($exportOrder)) {
                        $this->job->current_entity = $exportOrder[$i + 1];
                        $this->job->setCursor($exportOrder[$i + 1], 0);
                    } else {
                        $completed = true;
                    }
                }
            }

            // Export terminé
            if ($completed) {
                $this->finalizeExport();
                return array(
                    'status' => 'completed',
                    'processed' => $processed,
                    'message' => 'Export terminé avec succès',
                );
            }

            $this->job->updateProgress($this->job->processed_records + $processed);

            return array(
                'status' => 'running',
                'processed' => $processed,
                'message' => 'En cours...',
            );

        } catch (Exception $e) {
            $this->job->fail($e->getMessage());
            $this->job->log('error', 'Erreur export: ' . $e->getMessage());

            return array(
                'status' => 'failed',
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Exporte une entité
     */
    private function exportEntity($tableName, array $config)
    {
        $cursor = $this->job->getCursor($tableName);
        $processed = 0;

        // Obtenir le writer
        $writer = $this->getWriter($tableName);

        // Construire la requête selon le type d'entité
        if (isset($config['root']) && $config['root']) {
            // Entité racine (customers ou orders)
            $query = $this->buildRootQuery($tableName, $config, $cursor);
        } else {
            // Entité enfant
            $query = $this->buildChildQuery($tableName, $config, $cursor);
        }

        // Exécuter et écrire
        $rows = Db::getInstance()->executeS($query);

        if (empty($rows)) {
            // Plus de données pour cette entité
            $writer->close();
            $this->registerFile($tableName, $writer);

            return array('processed' => 0, 'completed' => true);
        }

        // Écrire les lignes
        foreach ($rows as $row) {
            $writer->writeRow($row);
            $processed++;

            // Mettre à jour le curseur
            $pk = isset($config['primary']) ? $config['primary'] : null;
            if ($pk && isset($row[$pk])) {
                $cursor = (int) $row[$pk];
            }
        }

        // Sauvegarder le curseur
        $this->job->setCursor($tableName, $cursor);

        // Vérifier si terminé
        $completed = count($rows) < $this->batchSize;

        if ($completed) {
            $writer->close();
            $this->registerFile($tableName, $writer);
        }

        return array('processed' => $processed, 'completed' => $completed);
    }

    /**
     * Construit la requête pour une entité racine
     */
    private function buildRootQuery($tableName, array $config, $cursor)
    {
        if ($tableName === 'orders') {
            return $this->queryBuilder->buildOrdersQuery($this->filters, $this->batchSize, $cursor);
        }

        if ($tableName === 'customer') {
            return $this->queryBuilder->buildCustomersQuery($this->filters, $this->batchSize, $cursor);
        }

        // Fallback générique
        $pk = $config['primary'];
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from($tableName);
        if ($cursor > 0) {
            $sql->where($pk . ' > ' . (int) $cursor);
        }
        $sql->orderBy($pk . ' ASC');
        $sql->limit($this->batchSize);

        return $sql->build();
    }

    /**
     * Construit la requête pour une entité enfant
     */
    private function buildChildQuery($tableName, array $config, $cursor)
    {
        // Récupérer les IDs parents déjà exportés
        $parentIds = $this->getExportedParentIds($tableName, $config);

        if (empty($parentIds)) {
            return "SELECT * FROM `" . _DB_PREFIX_ . pSQL($tableName) . "` WHERE 0";
        }

        return $this->queryBuilder->buildChildQuery(
            $tableName,
            $config['fk'],
            $parentIds,
            $this->batchSize,
            $cursor,
            isset($config['primary']) ? $config['primary'] : null
        );
    }

    /**
     * Récupère les IDs parents exportés
     */
    private function getExportedParentIds($tableName, array $config)
    {
        $fk = $config['fk'];

        // Déterminer la table parent
        if (isset($config['parent'])) {
            $parentTable = $config['parent'];
        } else {
            // Parent implicite via FK
            if ($fk === 'id_customer') {
                $parentTable = 'customer';
            } elseif ($fk === 'id_order') {
                $parentTable = 'orders';
            } elseif ($fk === 'order_reference') {
                // Cas spécial order_payment
                return $this->getExportedOrderReferences();
            } else {
                // Chercher dans le plan
                foreach ($this->plan['entities'] as $entity => $entityConfig) {
                    if (isset($entityConfig['primary']) && $entityConfig['primary'] === $fk) {
                        $parentTable = $entity;
                        break;
                    }
                }
            }
        }

        if (!isset($parentTable)) {
            return array();
        }

        // Lire le fichier d'export parent pour extraire les IDs
        $parentFile = $this->exportDir . $parentTable . '.csv';
        if (!file_exists($parentFile)) {
            return array();
        }

        $ids = array();
        $handle = fopen($parentFile, 'r');
        if ($handle) {
            $header = fgetcsv($handle, 0, ';');
            $fkIndex = array_search($fk, $header);

            if ($fkIndex === false) {
                // Essayer avec le primary du parent
                $parentConfig = isset($this->plan['entities'][$parentTable])
                    ? $this->plan['entities'][$parentTable]
                    : array();
                $pk = isset($parentConfig['primary']) ? $parentConfig['primary'] : null;
                if ($pk) {
                    $fkIndex = array_search($pk, $header);
                }
            }

            if ($fkIndex !== false) {
                while (($row = fgetcsv($handle, 0, ';')) !== false) {
                    if (isset($row[$fkIndex]) && $row[$fkIndex] !== '') {
                        $ids[] = $row[$fkIndex];
                    }
                }
            }
            fclose($handle);
        }

        return array_unique($ids);
    }

    /**
     * Récupère les références de commandes exportées
     */
    private function getExportedOrderReferences()
    {
        $ordersFile = $this->exportDir . 'orders.csv';
        if (!file_exists($ordersFile)) {
            return array();
        }

        $refs = array();
        $handle = fopen($ordersFile, 'r');
        if ($handle) {
            $header = fgetcsv($handle, 0, ';');
            $refIndex = array_search('reference', $header);

            if ($refIndex !== false) {
                while (($row = fgetcsv($handle, 0, ';')) !== false) {
                    if (isset($row[$refIndex]) && $row[$refIndex] !== '') {
                        $refs[] = $row[$refIndex];
                    }
                }
            }
            fclose($handle);
        }

        return array_unique($refs);
    }

    /**
     * Obtient ou crée un writer pour une entité
     */
    private function getWriter($tableName)
    {
        if (!isset($this->writers[$tableName])) {
            $filepath = $this->exportDir . $tableName . '.csv';
            $append = file_exists($filepath);

            $this->writers[$tableName] = new PdeCsvWriter(
                $filepath,
                Configuration::get('PDE_CSV_DELIMITER', null, null, null, ';'),
                Configuration::get('PDE_CSV_ENCLOSURE', null, null, null, '"'),
                (bool) Configuration::get('PDE_CSV_UTF8_BOM', null, null, null, true)
            );

            // Écrire l'en-tête si nouveau fichier
            if (!$append) {
                $columns = $this->getEntityColumns($tableName);
                $this->writers[$tableName]->writeHeader($columns);
            }
        }

        return $this->writers[$tableName];
    }

    /**
     * Récupère les colonnes d'une entité
     */
    private function getEntityColumns($tableName)
    {
        if (isset($this->plan['schema'][$tableName])) {
            return array_keys($this->plan['schema'][$tableName]);
        }

        // Fallback via SchemaInspector
        require_once dirname(__FILE__) . '/SchemaInspector.php';
        $inspector = new PdeSchemaInspector();
        return $inspector->getColumnNames($tableName);
    }

    /**
     * Enregistre un fichier dans la base
     */
    private function registerFile($tableName, PdeCsvWriter $writer)
    {
        $filepath = $this->exportDir . $tableName . '.csv';

        if (!file_exists($filepath)) {
            return;
        }

        $stats = $writer->getStats();

        // Générer le token de téléchargement
        $token = bin2hex(random_bytes(32));
        $ttl = (int) Configuration::get('PDE_DOWNLOAD_TTL', null, null, null, 24);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$ttl} hours"));

        Db::getInstance()->insert('pde_export_file', array(
            'id_export_job' => (int) $this->job->id,
            'entity_name' => pSQL($tableName),
            'filepath' => pSQL($filepath),
            'filename' => pSQL($tableName . '.csv'),
            'filesize' => (int) filesize($filepath),
            'row_count' => (int) $stats['row_count'],
            'checksum' => pSQL($writer->getChecksum()),
            'download_token' => pSQL($token),
            'download_expires' => pSQL($expiresAt),
            'download_count' => 0,
            'date_add' => date('Y-m-d H:i:s'),
        ));

        $this->job->log('info', "Fichier généré: {$tableName}.csv", array(
            'rows' => $stats['row_count'],
            'size' => filesize($filepath),
        ));
    }

    /**
     * Finalise l'export
     */
    private function finalizeExport()
    {
        // Fermer tous les writers
        foreach ($this->writers as $writer) {
            if ($writer->isOpen()) {
                $writer->close();
            }
        }

        // Créer le ZIP si demandé
        if (Configuration::get('PDE_CREATE_ZIP', null, null, null, true)) {
            $this->createZipArchive();
        }

        // Marquer le job comme terminé
        $this->job->complete();

        $this->job->log('info', 'Export terminé', array(
            'duration' => round(microtime(true) - $this->startTime, 2) . 's',
            'total_records' => $this->job->processed_records,
        ));
    }

    /**
     * Crée l'archive ZIP
     */
    private function createZipArchive()
    {
        $zipPath = $this->exportDir . 'export_' . $this->job->id . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->job->log('warning', 'Impossible de créer le ZIP');
            return;
        }

        // Ajouter tous les CSV
        $files = glob($this->exportDir . '*.csv');
        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }

        $zip->close();

        // Enregistrer le ZIP
        $token = bin2hex(random_bytes(32));
        $ttl = (int) Configuration::get('PDE_DOWNLOAD_TTL', null, null, null, 24);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$ttl} hours"));

        Db::getInstance()->insert('pde_export_file', array(
            'id_export_job' => (int) $this->job->id,
            'entity_name' => 'archive',
            'filepath' => pSQL($zipPath),
            'filename' => pSQL('export_' . $this->job->id . '.zip'),
            'filesize' => (int) filesize($zipPath),
            'row_count' => 0,
            'checksum' => pSQL(md5_file($zipPath)),
            'download_token' => pSQL($token),
            'download_expires' => pSQL($expiresAt),
            'download_count' => 0,
            'date_add' => date('Y-m-d H:i:s'),
        ));
    }

    /**
     * Vérifie si le temps max est dépassé
     */
    private function isTimeExceeded()
    {
        return (microtime(true) - $this->startTime) >= $this->maxExecutionTime;
    }

    /**
     * Estime le nombre total d'enregistrements
     */
    public static function estimateTotal($exportType, $exportLevel, array $filters)
    {
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $idShop = isset($filters['id_shop']) ? (int) $filters['id_shop'] : null;

        $queryBuilder = new PdeQueryBuilder($idLang, $idShop);
        $total = 0;

        if ($exportType === 'orders' || $exportType === 'full') {
            $total += $queryBuilder->countOrders($filters);
        }

        if ($exportType === 'customers' || $exportType === 'full') {
            $total += $queryBuilder->countCustomers($filters);
        }

        // Estimation des tables enfants (approximation)
        $planBuilder = new PdeExportPlanBuilder();
        $plan = $planBuilder->buildPlan($exportType, $exportLevel);

        foreach ($plan['entities'] as $tableName => $config) {
            if (!isset($config['root']) || !$config['root']) {
                // Estimation grossière basée sur un ratio
                $ratio = 2; // En moyenne 2 lignes enfants par parent
                if (strpos($tableName, 'detail') !== false) {
                    $ratio = 3;
                }
                if (strpos($tableName, 'history') !== false) {
                    $ratio = 5;
                }

                if ($config['type'] === 'orders') {
                    $parentCount = ($exportType === 'orders' || $exportType === 'full')
                        ? $queryBuilder->countOrders($filters)
                        : 0;
                } else {
                    $parentCount = ($exportType === 'customers' || $exportType === 'full')
                        ? $queryBuilder->countCustomers($filters)
                        : 0;
                }

                $total += (int) ($parentCount * $ratio);
            }
        }

        return $total;
    }
}
