<?php
/**
 * ExportPlanBuilder - Définit les entités à exporter selon le niveau
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/SchemaInspector.php';

class PdeExportPlanBuilder
{
    /** @var PdeSchemaInspector */
    private $schemaInspector;

    /** @var bool */
    private $includeCustomColumns;

    /** @var bool */
    private $includeCustomTables;

    /**
     * Définition des entités par niveau
     */
    private static $levelEntities = array(
        // Niveau Essentiel : données de base
        'essential' => array(
            'customers' => array(
                'customer' => array('primary' => 'id_customer', 'root' => true),
                'address' => array('primary' => 'id_address', 'fk' => 'id_customer'),
            ),
            'orders' => array(
                'orders' => array('primary' => 'id_order', 'root' => true),
                'order_detail' => array('primary' => 'id_order_detail', 'fk' => 'id_order'),
            ),
        ),
        // Niveau Complet : + paiements, transport, états, factures, avoirs, coupons
        'complete' => array(
            'customers' => array(
                'customer' => array('primary' => 'id_customer', 'root' => true),
                'customer_group' => array('primary' => null, 'fk' => 'id_customer'),
                'address' => array('primary' => 'id_address', 'fk' => 'id_customer'),
            ),
            'orders' => array(
                'orders' => array('primary' => 'id_order', 'root' => true),
                'order_detail' => array('primary' => 'id_order_detail', 'fk' => 'id_order'),
                'order_history' => array('primary' => 'id_order_history', 'fk' => 'id_order'),
                'order_payment' => array('primary' => 'id_order_payment', 'fk' => 'order_reference', 'fk_type' => 'reference'),
                'order_carrier' => array('primary' => 'id_order_carrier', 'fk' => 'id_order'),
                'order_invoice' => array('primary' => 'id_order_invoice', 'fk' => 'id_order'),
                'order_slip' => array('primary' => 'id_order_slip', 'fk' => 'id_order'),
                'order_cart_rule' => array('primary' => 'id_order_cart_rule', 'fk' => 'id_order'),
            ),
        ),
        // Niveau Ultra : + paniers, connections, messages, retours, stocks
        'ultra' => array(
            'customers' => array(
                'customer' => array('primary' => 'id_customer', 'root' => true),
                'customer_group' => array('primary' => null, 'fk' => 'id_customer'),
                'address' => array('primary' => 'id_address', 'fk' => 'id_customer'),
                'cart' => array('primary' => 'id_cart', 'fk' => 'id_customer'),
                'cart_product' => array('primary' => null, 'fk' => 'id_cart', 'parent' => 'cart'),
                'customer_thread' => array('primary' => 'id_customer_thread', 'fk' => 'id_customer'),
                'customer_message' => array('primary' => 'id_customer_message', 'fk' => 'id_customer_thread', 'parent' => 'customer_thread'),
                'connections' => array('primary' => 'id_connections', 'fk' => 'id_customer'),
                'guest' => array('primary' => 'id_guest', 'fk' => 'id_customer'),
            ),
            'orders' => array(
                'orders' => array('primary' => 'id_order', 'root' => true),
                'order_detail' => array('primary' => 'id_order_detail', 'fk' => 'id_order'),
                'order_history' => array('primary' => 'id_order_history', 'fk' => 'id_order'),
                'order_payment' => array('primary' => 'id_order_payment', 'fk' => 'order_reference', 'fk_type' => 'reference'),
                'order_carrier' => array('primary' => 'id_order_carrier', 'fk' => 'id_order'),
                'order_invoice' => array('primary' => 'id_order_invoice', 'fk' => 'id_order'),
                'order_invoice_payment' => array('primary' => null, 'fk' => 'id_order_invoice', 'parent' => 'order_invoice'),
                'order_slip' => array('primary' => 'id_order_slip', 'fk' => 'id_order'),
                'order_slip_detail' => array('primary' => 'id_order_slip_detail', 'fk' => 'id_order_slip', 'parent' => 'order_slip'),
                'order_return' => array('primary' => 'id_order_return', 'fk' => 'id_order'),
                'order_return_detail' => array('primary' => null, 'fk' => 'id_order_return', 'parent' => 'order_return'),
                'order_cart_rule' => array('primary' => 'id_order_cart_rule', 'fk' => 'id_order'),
                'order_message' => array('primary' => 'id_order_message', 'fk' => 'id_order'),
                'message' => array('primary' => 'id_message', 'fk' => 'id_order'),
            ),
        ),
    );

    /**
     * Tables de référence à joindre pour enrichir les données
     */
    private static $referenceTables = array(
        'country' => array('primary' => 'id_country', 'lang' => true),
        'state' => array('primary' => 'id_state'),
        'zone' => array('primary' => 'id_zone'),
        'group' => array('primary' => 'id_group', 'lang' => true),
        'carrier' => array('primary' => 'id_carrier', 'lang' => true),
        'order_state' => array('primary' => 'id_order_state', 'lang' => true),
        'cart_rule' => array('primary' => 'id_cart_rule', 'lang' => true),
        'currency' => array('primary' => 'id_currency'),
        'lang' => array('primary' => 'id_lang'),
    );

    public function __construct($includeCustomColumns = false, $includeCustomTables = false)
    {
        $this->schemaInspector = new PdeSchemaInspector();
        $this->includeCustomColumns = $includeCustomColumns;
        $this->includeCustomTables = $includeCustomTables;
    }

    /**
     * Construit le plan d'export
     */
    public function buildPlan($exportType, $exportLevel)
    {
        $plan = array(
            'entities' => array(),
            'reference_tables' => array(),
            'schema' => array(),
        );

        // Récupérer les entités selon le niveau
        $levelConfig = isset(self::$levelEntities[$exportLevel])
            ? self::$levelEntities[$exportLevel]
            : self::$levelEntities['essential'];

        // Type d'export (customers, orders, ou full)
        $typesToInclude = array();
        if ($exportType === 'customers' || $exportType === 'full') {
            $typesToInclude[] = 'customers';
        }
        if ($exportType === 'orders' || $exportType === 'full') {
            $typesToInclude[] = 'orders';
        }

        // Construire la liste des entités
        foreach ($typesToInclude as $type) {
            if (isset($levelConfig[$type])) {
                foreach ($levelConfig[$type] as $tableName => $config) {
                    // Vérifier que la table existe
                    if ($this->schemaInspector->tableExists($tableName)) {
                        $plan['entities'][$tableName] = $config;
                        $plan['entities'][$tableName]['type'] = $type;
                    }
                }
            }
        }

        // Ajouter les tables custom si option activée
        if ($this->includeCustomTables) {
            $this->addCustomTables($plan, $typesToInclude);
        }

        // Ajouter les tables de référence nécessaires
        $this->addReferenceTables($plan);

        // Générer le snapshot du schéma
        $allTables = array_keys($plan['entities']);
        foreach ($plan['reference_tables'] as $refTable => $refConfig) {
            $allTables[] = $refTable;
            if (isset($refConfig['lang']) && $refConfig['lang']) {
                $allTables[] = $refTable . '_lang';
            }
        }

        $plan['schema'] = $this->schemaInspector->generateSchemaSnapshot($allTables);

        // Ajouter les colonnes custom si option activée
        if ($this->includeCustomColumns) {
            $plan['custom_columns'] = $this->detectCustomColumns($plan['entities']);
        }

        return $plan;
    }

    /**
     * Ajoute les tables custom liées
     */
    private function addCustomTables(array &$plan, array $types)
    {
        // Détecter les tables custom contenant id_customer ou id_order
        $fkColumns = array();
        if (in_array('customers', $types)) {
            $fkColumns[] = 'id_customer';
        }
        if (in_array('orders', $types)) {
            $fkColumns[] = 'id_order';
        }

        foreach ($fkColumns as $fkColumn) {
            $customTables = $this->schemaInspector->detectCustomTables($fkColumn);

            foreach ($customTables as $tableName) {
                // Éviter les doublons
                if (!isset($plan['entities'][$tableName])) {
                    $pk = $this->schemaInspector->getPrimaryKey($tableName);
                    $plan['entities'][$tableName] = array(
                        'primary' => $pk,
                        'fk' => $fkColumn,
                        'custom' => true,
                        'type' => ($fkColumn === 'id_customer') ? 'customers' : 'orders',
                    );
                }
            }
        }
    }

    /**
     * Ajoute les tables de référence
     */
    private function addReferenceTables(array &$plan)
    {
        // Analyser les entités pour déterminer les tables de référence nécessaires
        $neededRefs = array();

        foreach ($plan['entities'] as $tableName => $config) {
            $columns = $this->schemaInspector->getColumnNames($tableName);

            foreach ($columns as $column) {
                // Détecter les FK vers les tables de référence
                if (preg_match('/^id_(.+)$/', $column, $matches)) {
                    $refTable = $matches[1];
                    if (isset(self::$referenceTables[$refTable])) {
                        $neededRefs[$refTable] = self::$referenceTables[$refTable];
                    }
                }
            }
        }

        $plan['reference_tables'] = $neededRefs;
    }

    /**
     * Détecte les colonnes custom pour chaque entité
     */
    private function detectCustomColumns(array $entities)
    {
        $customColumns = array();

        foreach (array_keys($entities) as $tableName) {
            $custom = $this->schemaInspector->getCustomColumns($tableName);
            if (!empty($custom)) {
                $customColumns[$tableName] = $custom;
            }
        }

        return $customColumns;
    }

    /**
     * Estime le nombre total d'enregistrements à exporter
     */
    public function estimateTotalRecords(array $plan, array $filters = array())
    {
        $total = 0;

        foreach ($plan['entities'] as $tableName => $config) {
            if (isset($config['root']) && $config['root']) {
                // Pour les tables racines, utiliser le count avec filtres
                $where = $this->buildWhereClause($tableName, $filters);
                $count = $this->schemaInspector->getExactCount($tableName, $where);
            } else {
                // Pour les tables enfants, estimation approximative
                $count = $this->schemaInspector->getApproximateCount($tableName);
            }

            $total += $count;
        }

        return $total;
    }

    /**
     * Construit une clause WHERE basique pour l'estimation
     */
    private function buildWhereClause($tableName, array $filters)
    {
        $conditions = array();

        // Filtre boutique
        if (!empty($filters['id_shop'])) {
            if ($this->schemaInspector->getColumnNames($tableName) &&
                in_array('id_shop', $this->schemaInspector->getColumnNames($tableName))) {
                $conditions[] = 'id_shop = ' . (int) $filters['id_shop'];
            }
        }

        // Filtre date
        if (!empty($filters['date_from'])) {
            $conditions[] = 'date_add >= \'' . pSQL($filters['date_from']) . '\'';
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'date_add <= \'' . pSQL($filters['date_to']) . '\'';
        }

        return !empty($conditions) ? implode(' AND ', $conditions) : null;
    }

    /**
     * Retourne l'ordre d'export des entités (respect des dépendances)
     */
    public function getExportOrder(array $plan)
    {
        $order = array();
        $processed = array();

        // D'abord les entités racines
        foreach ($plan['entities'] as $tableName => $config) {
            if (isset($config['root']) && $config['root']) {
                $order[] = $tableName;
                $processed[$tableName] = true;
            }
        }

        // Ensuite les entités enfants directes
        foreach ($plan['entities'] as $tableName => $config) {
            if (!isset($processed[$tableName]) && !isset($config['parent'])) {
                $order[] = $tableName;
                $processed[$tableName] = true;
            }
        }

        // Enfin les entités avec parent
        foreach ($plan['entities'] as $tableName => $config) {
            if (!isset($processed[$tableName])) {
                $order[] = $tableName;
            }
        }

        return $order;
    }

    /**
     * Récupère la configuration d'une entité
     */
    public function getEntityConfig($tableName)
    {
        foreach (self::$levelEntities as $level => $types) {
            foreach ($types as $type => $entities) {
                if (isset($entities[$tableName])) {
                    return $entities[$tableName];
                }
            }
        }
        return null;
    }

    /**
     * Récupère les niveaux disponibles
     */
    public static function getLevels()
    {
        return array(
            'essential' => 'Essentiel (Clients + Commandes de base)',
            'complete' => 'Complet (+ Paiements, Transport, États, Factures, Coupons)',
            'ultra' => 'Ultra (+ Paniers, Connexions, Messages, Retours)',
        );
    }

    /**
     * Récupère les types d'export disponibles
     */
    public static function getTypes()
    {
        return array(
            'customers' => 'Clients uniquement',
            'orders' => 'Commandes uniquement',
            'full' => 'Clients + Commandes (complet)',
        );
    }

    /**
     * Récupère les modes d'export disponibles
     */
    public static function getModes()
    {
        return array(
            'relational' => 'Relationnel (1 fichier par entité)',
            'enriched' => 'Enrichi (commandes avec infos clients)',
            'flat' => 'Aplati (1 fichier unique complet)',
        );
    }

    /**
     * Retourne les colonnes enrichies pour les commandes
     */
    public static function getEnrichedOrderColumns()
    {
        return array(
            // Colonnes commande de base
            'id_order', 'reference', 'id_customer', 'current_state',
            'payment', 'total_paid_tax_incl', 'total_paid_tax_excl',
            'total_products', 'total_shipping_tax_incl',
            'date_add', 'valid',
            // Colonnes client jointes
            'customer_email', 'customer_firstname', 'customer_lastname',
            'customer_company', 'customer_siret',
            // Colonnes adresse livraison
            'delivery_company', 'delivery_firstname', 'delivery_lastname',
            'delivery_address1', 'delivery_address2', 'delivery_postcode',
            'delivery_city', 'delivery_country', 'delivery_phone',
            // Colonnes adresse facturation
            'invoice_company', 'invoice_firstname', 'invoice_lastname',
            'invoice_address1', 'invoice_address2', 'invoice_postcode',
            'invoice_city', 'invoice_country', 'invoice_phone',
        );
    }

    /**
     * Retourne les colonnes pour le mode aplati (flat)
     */
    public static function getFlatExportColumns()
    {
        return array(
            // Commande
            'id_order', 'order_reference', 'order_date', 'order_status',
            'payment_method', 'total_paid_tax_incl', 'total_paid_tax_excl',
            'total_products', 'total_shipping',
            // Client
            'customer_id', 'customer_email', 'customer_firstname', 'customer_lastname',
            'customer_company', 'customer_newsletter',
            // Adresse livraison
            'delivery_firstname', 'delivery_lastname', 'delivery_company',
            'delivery_address1', 'delivery_address2', 'delivery_postcode',
            'delivery_city', 'delivery_country', 'delivery_phone',
            // Adresse facturation
            'invoice_firstname', 'invoice_lastname', 'invoice_company',
            'invoice_address1', 'invoice_address2', 'invoice_postcode',
            'invoice_city', 'invoice_country', 'invoice_phone',
            // Détail produit
            'product_id', 'product_name', 'product_reference',
            'product_quantity', 'product_price_tax_incl', 'product_price_tax_excl',
        );
    }
}
