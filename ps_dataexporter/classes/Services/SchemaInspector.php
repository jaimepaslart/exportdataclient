<?php
/**
 * SchemaInspector - Analyse du schéma DB et détection des colonnes custom
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PdeSchemaInspector
{
    /** @var string */
    private $dbName;

    /** @var string */
    private $prefix;

    /** @var array Cache des colonnes par table */
    private $columnsCache = array();

    /** @var array Tables natives PrestaShop (colonnes standard) */
    private static $nativeColumns = array(
        'customer' => array(
            'id_customer', 'id_shop_group', 'id_shop', 'id_gender', 'id_default_group',
            'id_lang', 'id_risk', 'company', 'siret', 'ape', 'firstname', 'lastname',
            'email', 'passwd', 'last_passwd_gen', 'birthday', 'newsletter', 'ip_registration_newsletter',
            'newsletter_date_add', 'optin', 'website', 'outstanding_allow_amount', 'show_public_prices',
            'max_payment_days', 'secure_key', 'note', 'active', 'is_guest', 'deleted',
            'date_add', 'date_upd', 'reset_password_token', 'reset_password_validity'
        ),
        'address' => array(
            'id_address', 'id_country', 'id_state', 'id_customer', 'id_manufacturer',
            'id_supplier', 'id_warehouse', 'alias', 'company', 'lastname', 'firstname',
            'address1', 'address2', 'postcode', 'city', 'other', 'phone', 'phone_mobile',
            'vat_number', 'dni', 'date_add', 'date_upd', 'active', 'deleted'
        ),
        'orders' => array(
            'id_order', 'reference', 'id_shop_group', 'id_shop', 'id_carrier', 'id_lang',
            'id_customer', 'id_cart', 'id_currency', 'id_address_delivery', 'id_address_invoice',
            'current_state', 'secure_key', 'payment', 'conversion_rate', 'module',
            'recyclable', 'gift', 'gift_message', 'mobile_theme', 'shipping_number',
            'total_discounts', 'total_discounts_tax_incl', 'total_discounts_tax_excl',
            'total_paid', 'total_paid_tax_incl', 'total_paid_tax_excl', 'total_paid_real',
            'total_products', 'total_products_wt', 'total_shipping', 'total_shipping_tax_incl',
            'total_shipping_tax_excl', 'carrier_tax_rate', 'total_wrapping', 'total_wrapping_tax_incl',
            'total_wrapping_tax_excl', 'round_mode', 'round_type', 'invoice_number', 'delivery_number',
            'invoice_date', 'delivery_date', 'valid', 'date_add', 'date_upd'
        ),
        'order_detail' => array(
            'id_order_detail', 'id_order', 'id_order_invoice', 'id_warehouse', 'id_shop',
            'product_id', 'product_attribute_id', 'id_customization', 'product_name',
            'product_quantity', 'product_quantity_in_stock', 'product_quantity_refunded',
            'product_quantity_return', 'product_quantity_reinjected', 'product_quantity_discount',
            'product_price', 'reduction_percent', 'reduction_amount', 'reduction_amount_tax_incl',
            'reduction_amount_tax_excl', 'group_reduction', 'product_quantity_discount',
            'product_ean13', 'product_isbn', 'product_upc', 'product_mpn', 'product_reference',
            'product_supplier_reference', 'product_weight', 'id_tax_rules_group', 'tax_computation_method',
            'tax_name', 'tax_rate', 'ecotax', 'ecotax_tax_rate', 'discount_quantity_applied',
            'download_hash', 'download_nb', 'download_deadline', 'total_price_tax_incl',
            'total_price_tax_excl', 'unit_price_tax_incl', 'unit_price_tax_excl',
            'total_shipping_price_tax_incl', 'total_shipping_price_tax_excl', 'purchase_supplier_price',
            'original_product_price', 'original_wholesale_price', 'total_refunded_tax_excl',
            'total_refunded_tax_incl'
        ),
    );

    public function __construct()
    {
        $this->dbName = _DB_NAME_;
        $this->prefix = _DB_PREFIX_;
    }

    /**
     * Récupère toutes les colonnes d'une table
     */
    public function getTableColumns($tableName)
    {
        $fullTableName = $this->prefix . $tableName;

        if (isset($this->columnsCache[$fullTableName])) {
            return $this->columnsCache[$fullTableName];
        }

        $sql = 'SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = \'' . pSQL($this->dbName) . '\'
                AND TABLE_NAME = \'' . pSQL($fullTableName) . '\'
                ORDER BY ORDINAL_POSITION';

        $columns = Db::getInstance()->executeS($sql);
        $this->columnsCache[$fullTableName] = $columns ? $columns : array();

        return $this->columnsCache[$fullTableName];
    }

    /**
     * Récupère uniquement les noms de colonnes
     */
    public function getColumnNames($tableName)
    {
        $columns = $this->getTableColumns($tableName);
        return array_column($columns, 'COLUMN_NAME');
    }

    /**
     * Détecte les colonnes custom (non natives) d'une table
     */
    public function getCustomColumns($tableName)
    {
        $allColumns = $this->getColumnNames($tableName);

        if (!isset(self::$nativeColumns[$tableName])) {
            // Table non référencée = toutes colonnes considérées custom
            return $allColumns;
        }

        $nativeColumns = self::$nativeColumns[$tableName];
        $customColumns = array();

        foreach ($allColumns as $column) {
            if (!in_array($column, $nativeColumns)) {
                $customColumns[] = $column;
            }
        }

        return $customColumns;
    }

    /**
     * Vérifie si une table existe
     */
    public function tableExists($tableName)
    {
        $fullTableName = $this->prefix . $tableName;

        $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = \'' . pSQL($this->dbName) . '\'
                AND TABLE_NAME = \'' . pSQL($fullTableName) . '\'';

        return (int) Db::getInstance()->getValue($sql) > 0;
    }

    /**
     * Récupère les tables liées à une entité (via FK implicites)
     */
    public function getRelatedTables($entityType)
    {
        $relatedTables = array();

        switch ($entityType) {
            case 'customer':
                $relatedTables = array(
                    'customer_group' => 'id_customer',
                    'address' => 'id_customer',
                    'cart' => 'id_customer',
                    'customer_message' => 'id_customer',
                    'customer_thread' => 'id_customer',
                    'connections' => 'id_customer',
                    'guest' => 'id_customer',
                );
                break;

            case 'order':
                $relatedTables = array(
                    'order_detail' => 'id_order',
                    'order_history' => 'id_order',
                    'order_payment' => 'id_order', // via order_reference
                    'order_carrier' => 'id_order',
                    'order_invoice' => 'id_order',
                    'order_slip' => 'id_order',
                    'order_return' => 'id_order',
                    'order_cart_rule' => 'id_order',
                    'order_message' => 'id_order',
                    'message' => 'id_order',
                );
                break;
        }

        // Filtrer les tables qui existent réellement
        $existingTables = array();
        foreach ($relatedTables as $table => $fkColumn) {
            if ($this->tableExists($table)) {
                $existingTables[$table] = $fkColumn;
            }
        }

        return $existingTables;
    }

    /**
     * Détecte les tables custom contenant une colonne FK spécifique
     */
    public function detectCustomTables($fkColumnName)
    {
        $sql = 'SELECT DISTINCT TABLE_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = \'' . pSQL($this->dbName) . '\'
                AND TABLE_NAME LIKE \'' . pSQL($this->prefix) . '%\'
                AND COLUMN_NAME = \'' . pSQL($fkColumnName) . '\'';

        $results = Db::getInstance()->executeS($sql);
        $customTables = array();

        // Tables natives à exclure
        $nativeTables = array(
            $this->prefix . 'customer',
            $this->prefix . 'orders',
            $this->prefix . 'order_detail',
            $this->prefix . 'address',
            $this->prefix . 'cart',
            $this->prefix . 'cart_product',
        );

        foreach ($results as $row) {
            $tableName = $row['TABLE_NAME'];
            if (!in_array($tableName, $nativeTables)) {
                // Retourner le nom sans préfixe
                $customTables[] = str_replace($this->prefix, '', $tableName);
            }
        }

        return $customTables;
    }

    /**
     * Génère un snapshot du schéma pour un job
     */
    public function generateSchemaSnapshot(array $tables)
    {
        $snapshot = array();

        foreach ($tables as $table) {
            $columns = $this->getColumnNames($table);
            if (!empty($columns)) {
                $snapshot[$table] = $columns;
            }
        }

        return $snapshot;
    }

    /**
     * Récupère la clé primaire d'une table
     */
    public function getPrimaryKey($tableName)
    {
        $columns = $this->getTableColumns($tableName);

        foreach ($columns as $column) {
            if ($column['COLUMN_KEY'] === 'PRI') {
                return $column['COLUMN_NAME'];
            }
        }

        return null;
    }

    /**
     * Récupère les index d'une table
     */
    public function getTableIndexes($tableName)
    {
        $fullTableName = $this->prefix . $tableName;

        $sql = 'SHOW INDEX FROM `' . pSQL($fullTableName) . '`';

        try {
            return Db::getInstance()->executeS($sql);
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * Vérifie si un index existe sur une colonne
     */
    public function indexExists($tableName, $columnName)
    {
        $indexes = $this->getTableIndexes($tableName);

        foreach ($indexes as $index) {
            if ($index['Column_name'] === $columnName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compte le nombre d'enregistrements d'une table (approximatif rapide)
     */
    public function getApproximateCount($tableName)
    {
        $fullTableName = $this->prefix . $tableName;

        // Utilise SHOW TABLE STATUS pour un count rapide (approximatif)
        $sql = 'SHOW TABLE STATUS LIKE \'' . pSQL($fullTableName) . '\'';
        $result = Db::getInstance()->getRow($sql);

        if ($result && isset($result['Rows'])) {
            return (int) $result['Rows'];
        }

        return 0;
    }

    /**
     * Compte exact (plus lent)
     */
    public function getExactCount($tableName, $where = null)
    {
        $fullTableName = $this->prefix . $tableName;

        $sql = 'SELECT COUNT(*) FROM `' . pSQL($fullTableName) . '`';
        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        return (int) Db::getInstance()->getValue($sql);
    }
}
