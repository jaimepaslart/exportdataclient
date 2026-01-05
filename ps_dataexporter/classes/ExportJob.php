<?php
/**
 * ExportJob - Modèle de job d'export
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PdeExportJob extends ObjectModel
{
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    const TYPE_CUSTOMERS = 'customers';
    const TYPE_ORDERS = 'orders';
    const TYPE_FULL = 'full';

    const LEVEL_ESSENTIAL = 'essential';
    const LEVEL_COMPLETE = 'complete';
    const LEVEL_ULTRA = 'ultra';

    const MODE_RELATIONAL = 'relational';
    const MODE_FLAT = 'flat';

    /** @var int */
    public $id_export_job;

    /** @var int */
    public $id_employee;

    /** @var int|null */
    public $id_shop;

    /** @var string */
    public $export_type = self::TYPE_ORDERS;

    /** @var string */
    public $export_level = self::LEVEL_ESSENTIAL;

    /** @var string */
    public $export_mode = self::MODE_RELATIONAL;

    /** @var string JSON */
    public $params_json;

    /** @var string JSON */
    public $schema_snapshot;

    /** @var string */
    public $status = self::STATUS_PENDING;

    /** @var int */
    public $total_records = 0;

    /** @var int */
    public $processed_records = 0;

    /** @var string|null */
    public $current_entity;

    /** @var string JSON */
    public $cursors_json;

    /** @var string|null */
    public $error_message;

    /** @var string */
    public $started_at;

    /** @var string */
    public $completed_at;

    /** @var string */
    public $date_add;

    /** @var string */
    public $date_upd;

    public static $definition = array(
        'table' => 'pde_export_job',
        'primary' => 'id_export_job',
        'fields' => array(
            'id_employee' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'id_shop' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'export_type' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32, 'required' => true),
            'export_level' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32),
            'export_mode' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32),
            'params_json' => array('type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'),
            'schema_snapshot' => array('type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'),
            'status' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32),
            'total_records' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'processed_records' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'current_entity' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 64),
            'cursors_json' => array('type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'),
            'error_message' => array('type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'),
            'started_at' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'completed_at' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    );

    /**
     * Récupère les paramètres décodés
     */
    public function getParams()
    {
        if (empty($this->params_json)) {
            return array();
        }
        $params = json_decode($this->params_json, true);
        return is_array($params) ? $params : array();
    }

    /**
     * Définit les paramètres
     */
    public function setParams(array $params)
    {
        $this->params_json = json_encode($params, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Récupère le schema snapshot décodé
     */
    public function getSchemaSnapshot()
    {
        if (empty($this->schema_snapshot)) {
            return array();
        }
        $schema = json_decode($this->schema_snapshot, true);
        return is_array($schema) ? $schema : array();
    }

    /**
     * Définit le schema snapshot
     */
    public function setSchemaSnapshot(array $schema)
    {
        $this->schema_snapshot = json_encode($schema, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Récupère les curseurs décodés
     */
    public function getCursors()
    {
        if (empty($this->cursors_json)) {
            return array();
        }
        $cursors = json_decode($this->cursors_json, true);
        return is_array($cursors) ? $cursors : array();
    }

    /**
     * Définit un curseur pour une entité
     */
    public function setCursor($entity, $lastId)
    {
        $cursors = $this->getCursors();
        $cursors[$entity] = (int) $lastId;
        $this->cursors_json = json_encode($cursors);
    }

    /**
     * Récupère le curseur d'une entité
     */
    public function getCursor($entity)
    {
        $cursors = $this->getCursors();
        return isset($cursors[$entity]) ? (int) $cursors[$entity] : 0;
    }

    /**
     * Démarre le job
     */
    public function start()
    {
        $this->status = self::STATUS_RUNNING;
        $this->started_at = date('Y-m-d H:i:s');
        $this->date_upd = date('Y-m-d H:i:s');
        return $this->update();
    }

    /**
     * Met en pause le job
     */
    public function pause()
    {
        $this->status = self::STATUS_PAUSED;
        $this->date_upd = date('Y-m-d H:i:s');
        return $this->update();
    }

    /**
     * Termine le job avec succès
     */
    public function complete()
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = date('Y-m-d H:i:s');
        $this->date_upd = date('Y-m-d H:i:s');
        return $this->update();
    }

    /**
     * Marque le job comme échoué
     */
    public function fail($errorMessage)
    {
        $this->status = self::STATUS_FAILED;
        $this->error_message = pSQL(Tools::substr($errorMessage, 0, 65535));
        $this->date_upd = date('Y-m-d H:i:s');
        return $this->update();
    }

    /**
     * Met à jour la progression
     */
    public function updateProgress($processedRecords, $currentEntity = null)
    {
        $this->processed_records = (int) $processedRecords;
        if ($currentEntity !== null) {
            $this->current_entity = pSQL($currentEntity);
        }
        $this->date_upd = date('Y-m-d H:i:s');
        return $this->update();
    }

    /**
     * Récupère le pourcentage de progression
     */
    public function getProgressPercent()
    {
        if ($this->total_records <= 0) {
            return 0;
        }
        return min(100, round(($this->processed_records / $this->total_records) * 100, 1));
    }

    /**
     * Récupère les fichiers générés pour ce job
     */
    public function getFiles()
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('pde_export_file');
        $sql->where('id_export_job = ' . (int) $this->id);
        $sql->orderBy('entity_name ASC');

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Récupère les logs pour ce job
     */
    public function getLogs($limit = 100)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('pde_export_log');
        $sql->where('id_export_job = ' . (int) $this->id);
        $sql->orderBy('id_log DESC');
        $sql->limit((int) $limit);

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Ajoute un log
     */
    public function log($level, $message, array $context = array())
    {
        // Ne jamais logger de PII
        $safeContext = array();
        $piiKeys = array('email', 'phone', 'firstname', 'lastname', 'address', 'postcode', 'password');
        foreach ($context as $key => $value) {
            if (!in_array(strtolower($key), $piiKeys)) {
                $safeContext[$key] = $value;
            }
        }

        return Db::getInstance()->insert('pde_export_log', array(
            'id_export_job' => (int) $this->id,
            'level' => pSQL($level),
            'message' => pSQL(Tools::substr($message, 0, 65535)),
            'context_json' => !empty($safeContext) ? pSQL(json_encode($safeContext)) : null,
            'date_add' => date('Y-m-d H:i:s'),
        ));
    }

    /**
     * Récupère les jobs par statut
     */
    public static function getByStatus($status, $limit = 50)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('pde_export_job');
        $sql->where('status = \'' . pSQL($status) . '\'');
        $sql->orderBy('date_add DESC');
        $sql->limit((int) $limit);

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Récupère les jobs récents
     */
    public static function getRecent($limit = 50)
    {
        $sql = new DbQuery();
        $sql->select('j.*, e.firstname, e.lastname');
        $sql->from('pde_export_job', 'j');
        $sql->leftJoin('employee', 'e', 'e.id_employee = j.id_employee');
        $sql->orderBy('j.date_add DESC');
        $sql->limit((int) $limit);

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Nettoyage des anciens jobs et fichiers
     */
    public static function cleanupOldJobs($days = 7)
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Récupérer les fichiers à supprimer
        $sql = new DbQuery();
        $sql->select('f.filepath');
        $sql->from('pde_export_file', 'f');
        $sql->innerJoin('pde_export_job', 'j', 'j.id_export_job = f.id_export_job');
        $sql->where('j.date_add < \'' . pSQL($cutoff) . '\'');

        $files = Db::getInstance()->executeS($sql);
        foreach ($files as $file) {
            if (file_exists($file['filepath'])) {
                @unlink($file['filepath']);
            }
        }

        // Supprimer les logs
        Db::getInstance()->execute(
            'DELETE l FROM `' . _DB_PREFIX_ . 'pde_export_log` l
             INNER JOIN `' . _DB_PREFIX_ . 'pde_export_job` j ON j.id_export_job = l.id_export_job
             WHERE j.date_add < \'' . pSQL($cutoff) . '\''
        );

        // Supprimer les fichiers
        Db::getInstance()->execute(
            'DELETE f FROM `' . _DB_PREFIX_ . 'pde_export_file` f
             INNER JOIN `' . _DB_PREFIX_ . 'pde_export_job` j ON j.id_export_job = f.id_export_job
             WHERE j.date_add < \'' . pSQL($cutoff) . '\''
        );

        // Supprimer les jobs
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'pde_export_job`
             WHERE date_add < \'' . pSQL($cutoff) . '\''
        );

        return true;
    }
}
