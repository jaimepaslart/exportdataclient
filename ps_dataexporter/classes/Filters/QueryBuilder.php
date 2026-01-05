<?php
/**
 * QueryBuilder - Génère les requêtes SQL optimisées pour l'export
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PdeQueryBuilder
{
    /** @var string */
    private $prefix;

    /** @var array */
    private $filters;

    /** @var int|null */
    private $idShop;

    /** @var int|null */
    private $idLang;

    public function __construct(array $filters = array())
    {
        $this->prefix = _DB_PREFIX_;
        $this->filters = $filters;
        $this->idShop = isset($filters['id_shop']) ? (int) $filters['id_shop'] : null;
        $this->idLang = isset($filters['id_lang']) ? (int) $filters['id_lang'] : (int) Configuration::get('PS_LANG_DEFAULT');
    }

    /**
     * Construit la requête pour les commandes
     */
    public function buildOrdersQuery($lastId = 0, $limit = 1000)
    {
        $sql = new DbQuery();
        $sql->select('o.*');
        $sql->from('orders', 'o');

        // Curseur pour pagination
        if ($lastId > 0) {
            $sql->where('o.id_order > ' . (int) $lastId);
        }

        // Appliquer les filtres
        $this->applyOrderFilters($sql);

        $sql->orderBy('o.id_order ASC');
        $sql->limit((int) $limit);

        return $sql;
    }

    /**
     * Construit la requête pour les clients
     */
    public function buildCustomersQuery($lastId = 0, $limit = 1000)
    {
        $sql = new DbQuery();
        $sql->select('c.*');
        $sql->from('customer', 'c');

        // Curseur pour pagination
        if ($lastId > 0) {
            $sql->where('c.id_customer > ' . (int) $lastId);
        }

        // Appliquer les filtres
        $this->applyCustomerFilters($sql);

        $sql->orderBy('c.id_customer ASC');
        $sql->limit((int) $limit);

        return $sql;
    }

    /**
     * Construit la requête pour une table enfant liée par FK
     */
    public function buildChildQuery($tableName, $fkColumn, array $parentIds, $columns = '*')
    {
        if (empty($parentIds)) {
            return null;
        }

        $sql = new DbQuery();
        $sql->select($columns);
        $sql->from($tableName);
        $sql->where($fkColumn . ' IN (' . implode(',', array_map('intval', $parentIds)) . ')');
        $sql->orderBy($fkColumn . ' ASC');

        return $sql;
    }

    /**
     * Construit la requête COUNT pour estimation
     */
    public function buildCountQuery($entityType)
    {
        $sql = new DbQuery();
        $sql->select('COUNT(*)');

        if ($entityType === 'orders') {
            $sql->from('orders', 'o');
            $this->applyOrderFilters($sql);
        } elseif ($entityType === 'customers') {
            $sql->from('customer', 'c');
            $this->applyCustomerFilters($sql);
        }

        return $sql;
    }

    /**
     * Applique les filtres sur les commandes
     */
    private function applyOrderFilters(DbQuery $sql)
    {
        $f = $this->filters;

        // Filtre boutique
        if ($this->idShop) {
            $sql->where('o.id_shop = ' . (int) $this->idShop);
        } elseif (!empty($f['id_shop_multi'])) {
            $sql->where('o.id_shop IN (' . implode(',', array_map('intval', $f['id_shop_multi'])) . ')');
        }

        // Filtres dates
        if (!empty($f['date_add_from'])) {
            $sql->where('o.date_add >= \'' . pSQL($f['date_add_from']) . ' 00:00:00\'');
        }
        if (!empty($f['date_add_to'])) {
            $sql->where('o.date_add <= \'' . pSQL($f['date_add_to']) . ' 23:59:59\'');
        }
        if (!empty($f['date_upd_from'])) {
            $sql->where('o.date_upd >= \'' . pSQL($f['date_upd_from']) . ' 00:00:00\'');
        }
        if (!empty($f['date_upd_to'])) {
            $sql->where('o.date_upd <= \'' . pSQL($f['date_upd_to']) . ' 23:59:59\'');
        }

        // Filtres ID
        if (!empty($f['id_min'])) {
            $sql->where('o.id_order >= ' . (int) $f['id_min']);
        }
        if (!empty($f['id_max'])) {
            $sql->where('o.id_order <= ' . (int) $f['id_max']);
        }

        // Filtres devise/langue
        if (!empty($f['id_currency'])) {
            $sql->where('o.id_currency = ' . (int) $f['id_currency']);
        }
        if (!empty($f['id_lang'])) {
            $sql->where('o.id_lang = ' . (int) $f['id_lang']);
        }

        // Filtres montants
        $this->applyAmountFilters($sql, $f);

        // Filtres statuts
        $this->applyStatusFilters($sql, $f);

        // Filtres paiement/transport
        $this->applyPaymentFilters($sql, $f);

        // Filtres produits (nécessite jointure)
        $this->applyProductFilters($sql, $f);

        // Filtres coupons (nécessite jointure)
        $this->applyCouponFilters($sql, $f);

        // Filtres géographiques (nécessite jointure)
        $this->applyGeoFilters($sql, $f, 'order');
    }

    /**
     * Applique les filtres sur les clients
     */
    private function applyCustomerFilters(DbQuery $sql)
    {
        $f = $this->filters;

        // Filtre boutique
        if ($this->idShop) {
            $sql->where('c.id_shop = ' . (int) $this->idShop);
        }

        // Filtres dates
        if (!empty($f['date_add_from'])) {
            $sql->where('c.date_add >= \'' . pSQL($f['date_add_from']) . ' 00:00:00\'');
        }
        if (!empty($f['date_add_to'])) {
            $sql->where('c.date_add <= \'' . pSQL($f['date_add_to']) . ' 23:59:59\'');
        }

        // Filtres ID
        if (!empty($f['id_min'])) {
            $sql->where('c.id_customer >= ' . (int) $f['id_min']);
        }
        if (!empty($f['id_max'])) {
            $sql->where('c.id_customer <= ' . (int) $f['id_max']);
        }

        // Filtre actif
        if (isset($f['customer_active'])) {
            $sql->where('c.active = ' . ($f['customer_active'] ? 1 : 0));
        }

        // Filtre supprimé (toujours exclure les supprimés sauf demande contraire)
        $sql->where('c.deleted = 0');

        // Filtre groupe par défaut
        if (!empty($f['id_default_group'])) {
            $sql->where('c.id_default_group IN (' . implode(',', array_map('intval', $f['id_default_group'])) . ')');
        }

        // Filtre newsletter
        if (isset($f['newsletter'])) {
            $sql->where('c.newsletter = ' . ($f['newsletter'] ? 1 : 0));
        }

        // Filtre optin
        if (isset($f['optin'])) {
            $sql->where('c.optin = ' . ($f['optin'] ? 1 : 0));
        }

        // Filtre langue
        if (!empty($f['customer_id_lang'])) {
            $sql->where('c.id_lang = ' . (int) $f['customer_id_lang']);
        }

        // Filtre email
        if (!empty($f['customer_email'])) {
            $sql->where('c.email = \'' . pSQL($f['customer_email']) . '\'');
        }
        if (!empty($f['customer_email_contains'])) {
            $sql->where('c.email LIKE \'%' . pSQL($f['customer_email_contains']) . '%\'');
        }

        // Filtre prospect (jamais commandé)
        if (!empty($f['is_prospect'])) {
            $sql->where('NOT EXISTS (SELECT 1 FROM `' . $this->prefix . 'orders` o WHERE o.id_customer = c.id_customer)');
        }

        // Filtre appartient au groupe
        if (!empty($f['in_group'])) {
            $sql->innerJoin('customer_group', 'cg', 'cg.id_customer = c.id_customer');
            $sql->where('cg.id_group IN (' . implode(',', array_map('intval', $f['in_group'])) . ')');
            $sql->groupBy('c.id_customer');
        }

        // Filtres B2B
        if (!empty($f['has_company'])) {
            $sql->where('c.company IS NOT NULL AND c.company != \'\'');
        }
        if (!empty($f['has_siret'])) {
            $sql->where('c.siret IS NOT NULL AND c.siret != \'\'');
        }

        // Filtres valeur client (nécessite sous-requête)
        $this->applyCustomerValueFilters($sql, $f);
    }

    /**
     * Applique les filtres de montants
     */
    private function applyAmountFilters(DbQuery $sql, array $f)
    {
        if (!empty($f['total_paid_tax_incl_min'])) {
            $sql->where('o.total_paid_tax_incl >= ' . (float) $f['total_paid_tax_incl_min']);
        }
        if (!empty($f['total_paid_tax_incl_max'])) {
            $sql->where('o.total_paid_tax_incl <= ' . (float) $f['total_paid_tax_incl_max']);
        }
        if (!empty($f['total_paid_tax_excl_min'])) {
            $sql->where('o.total_paid_tax_excl >= ' . (float) $f['total_paid_tax_excl_min']);
        }
        if (!empty($f['total_paid_tax_excl_max'])) {
            $sql->where('o.total_paid_tax_excl <= ' . (float) $f['total_paid_tax_excl_max']);
        }
        if (!empty($f['total_products_min'])) {
            $sql->where('o.total_products >= ' . (float) $f['total_products_min']);
        }
        if (!empty($f['total_products_max'])) {
            $sql->where('o.total_products <= ' . (float) $f['total_products_max']);
        }
        if (!empty($f['total_shipping_min'])) {
            $sql->where('o.total_shipping_tax_incl >= ' . (float) $f['total_shipping_min']);
        }
        if (!empty($f['total_shipping_max'])) {
            $sql->where('o.total_shipping_tax_incl <= ' . (float) $f['total_shipping_max']);
        }
        if (!empty($f['total_discounts_min'])) {
            $sql->where('o.total_discounts_tax_incl >= ' . (float) $f['total_discounts_min']);
        }
        if (!empty($f['total_discounts_max'])) {
            $sql->where('o.total_discounts_tax_incl <= ' . (float) $f['total_discounts_max']);
        }
    }

    /**
     * Applique les filtres de statuts
     */
    private function applyStatusFilters(DbQuery $sql, array $f)
    {
        if (!empty($f['current_state'])) {
            $sql->where('o.current_state IN (' . implode(',', array_map('intval', $f['current_state'])) . ')');
        }

        if (!empty($f['has_had_state'])) {
            $states = implode(',', array_map('intval', $f['has_had_state']));
            $sql->where('EXISTS (
                SELECT 1 FROM `' . $this->prefix . 'order_history` oh
                WHERE oh.id_order = o.id_order
                AND oh.id_order_state IN (' . $states . ')
            )');
        }

        if (!empty($f['never_had_state'])) {
            $states = implode(',', array_map('intval', $f['never_had_state']));
            $sql->where('NOT EXISTS (
                SELECT 1 FROM `' . $this->prefix . 'order_history` oh
                WHERE oh.id_order = o.id_order
                AND oh.id_order_state IN (' . $states . ')
            )');
        }

        // Commandes en retard
        if (!empty($f['order_late_days'])) {
            $days = (int) $f['order_late_days'];
            $excludeStates = !empty($f['order_late_exclude_states'])
                ? implode(',', array_map('intval', $f['order_late_exclude_states']))
                : '5'; // État livré par défaut

            $sql->where('o.date_add < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)');
            $sql->where('o.current_state NOT IN (' . $excludeStates . ')');
        }
    }

    /**
     * Applique les filtres de paiement/transport
     */
    private function applyPaymentFilters(DbQuery $sql, array $f)
    {
        if (!empty($f['payment'])) {
            $sql->where('o.payment = \'' . pSQL($f['payment']) . '\'');
        }
        if (!empty($f['payment_contains'])) {
            $sql->where('o.payment LIKE \'%' . pSQL($f['payment_contains']) . '%\'');
        }
        if (!empty($f['module'])) {
            $sql->where('o.module = \'' . pSQL($f['module']) . '\'');
        }

        if (isset($f['has_payment'])) {
            if ($f['has_payment']) {
                $sql->where('EXISTS (
                    SELECT 1 FROM `' . $this->prefix . 'order_payment` op
                    WHERE op.order_reference = o.reference
                )');
            } else {
                $sql->where('NOT EXISTS (
                    SELECT 1 FROM `' . $this->prefix . 'order_payment` op
                    WHERE op.order_reference = o.reference
                )');
            }
        }

        if (!empty($f['id_carrier'])) {
            $sql->where('o.id_carrier IN (' . implode(',', array_map('intval', $f['id_carrier'])) . ')');
        }

        if (isset($f['free_shipping'])) {
            if ($f['free_shipping']) {
                $sql->where('o.total_shipping_tax_incl = 0');
            } else {
                $sql->where('o.total_shipping_tax_incl > 0');
            }
        }

        if (isset($f['has_tracking'])) {
            if ($f['has_tracking']) {
                $sql->where('o.shipping_number IS NOT NULL AND o.shipping_number != \'\'');
            } else {
                $sql->where('(o.shipping_number IS NULL OR o.shipping_number = \'\')');
            }
        }

        if (isset($f['has_invoice'])) {
            if ($f['has_invoice']) {
                $sql->where('o.invoice_number > 0');
            } else {
                $sql->where('(o.invoice_number IS NULL OR o.invoice_number = 0)');
            }
        }

        if (isset($f['has_slip'])) {
            $exists = $f['has_slip'] ? 'EXISTS' : 'NOT EXISTS';
            $sql->where($exists . ' (
                SELECT 1 FROM `' . $this->prefix . 'order_slip` os
                WHERE os.id_order = o.id_order
            )');
        }

        if (isset($f['has_return'])) {
            $exists = $f['has_return'] ? 'EXISTS' : 'NOT EXISTS';
            $sql->where($exists . ' (
                SELECT 1 FROM `' . $this->prefix . 'order_return` orr
                WHERE orr.id_order = o.id_order
            )');
        }

        if (isset($f['different_addresses']) && $f['different_addresses']) {
            $sql->where('o.id_address_delivery != o.id_address_invoice');
        }
    }

    /**
     * Applique les filtres produits
     */
    private function applyProductFilters(DbQuery $sql, array $f)
    {
        if (!empty($f['has_product'])) {
            $products = implode(',', array_map('intval', $f['has_product']));
            $sql->where('EXISTS (
                SELECT 1 FROM `' . $this->prefix . 'order_detail` od
                WHERE od.id_order = o.id_order
                AND od.product_id IN (' . $products . ')
            )');
        }

        if (!empty($f['has_product_attribute'])) {
            $attrs = implode(',', array_map('intval', $f['has_product_attribute']));
            $sql->where('EXISTS (
                SELECT 1 FROM `' . $this->prefix . 'order_detail` od
                WHERE od.id_order = o.id_order
                AND od.product_attribute_id IN (' . $attrs . ')
            )');
        }

        if (!empty($f['product_reference'])) {
            $sql->where('EXISTS (
                SELECT 1 FROM `' . $this->prefix . 'order_detail` od
                WHERE od.id_order = o.id_order
                AND od.product_reference = \'' . pSQL($f['product_reference']) . '\'
            )');
        }

        if (!empty($f['product_reference_contains'])) {
            $sql->where('EXISTS (
                SELECT 1 FROM `' . $this->prefix . 'order_detail` od
                WHERE od.id_order = o.id_order
                AND od.product_reference LIKE \'%' . pSQL($f['product_reference_contains']) . '%\'
            )');
        }

        if (!empty($f['product_ean'])) {
            $sql->where('EXISTS (
                SELECT 1 FROM `' . $this->prefix . 'order_detail` od
                WHERE od.id_order = o.id_order
                AND od.product_ean13 = \'' . pSQL($f['product_ean']) . '\'
            )');
        }

        if (isset($f['has_product_discount']) && $f['has_product_discount']) {
            $sql->where('EXISTS (
                SELECT 1 FROM `' . $this->prefix . 'order_detail` od
                WHERE od.id_order = o.id_order
                AND (od.reduction_percent > 0 OR od.reduction_amount > 0)
            )');
        }
    }

    /**
     * Applique les filtres coupons
     */
    private function applyCouponFilters(DbQuery $sql, array $f)
    {
        if (isset($f['has_coupon'])) {
            $exists = $f['has_coupon'] ? 'EXISTS' : 'NOT EXISTS';
            $sql->where($exists . ' (
                SELECT 1 FROM `' . $this->prefix . 'order_cart_rule` ocr
                WHERE ocr.id_order = o.id_order
            )');
        }

        if (!empty($f['coupon_code'])) {
            $sql->where('EXISTS (
                SELECT 1 FROM `' . $this->prefix . 'order_cart_rule` ocr
                INNER JOIN `' . $this->prefix . 'cart_rule` cr ON cr.id_cart_rule = ocr.id_cart_rule
                WHERE ocr.id_order = o.id_order
                AND cr.code = \'' . pSQL($f['coupon_code']) . '\'
            )');
        }

        if (!empty($f['coupon_code_contains'])) {
            $sql->where('EXISTS (
                SELECT 1 FROM `' . $this->prefix . 'order_cart_rule` ocr
                INNER JOIN `' . $this->prefix . 'cart_rule` cr ON cr.id_cart_rule = ocr.id_cart_rule
                WHERE ocr.id_order = o.id_order
                AND cr.code LIKE \'%' . pSQL($f['coupon_code_contains']) . '%\'
            )');
        }

        if (!empty($f['coupon_discount_min'])) {
            $sql->where('EXISTS (
                SELECT 1 FROM `' . $this->prefix . 'order_cart_rule` ocr
                WHERE ocr.id_order = o.id_order
                AND ocr.value >= ' . (float) $f['coupon_discount_min'] . '
            )');
        }
    }

    /**
     * Applique les filtres géographiques
     */
    private function applyGeoFilters(DbQuery $sql, array $f, $context = 'order')
    {
        $hasGeoFilter = !empty($f['id_country']) || !empty($f['country_iso'])
            || !empty($f['id_state']) || !empty($f['state_iso'])
            || !empty($f['id_zone']) || !empty($f['postcode'])
            || !empty($f['postcode_prefix']) || !empty($f['dept_code'])
            || !empty($f['region_code']);

        if (!$hasGeoFilter) {
            return;
        }

        // Déterminer l'adresse à utiliser
        $addressType = isset($f['address_type']) ? $f['address_type'] : 'delivery';

        if ($context === 'order') {
            if ($addressType === 'delivery') {
                $sql->innerJoin('address', 'addr', 'addr.id_address = o.id_address_delivery');
            } elseif ($addressType === 'invoice') {
                $sql->innerJoin('address', 'addr', 'addr.id_address = o.id_address_invoice');
            } else {
                // both - une des deux adresses doit correspondre
                $sql->where('(
                    EXISTS (SELECT 1 FROM `' . $this->prefix . 'address` a WHERE a.id_address = o.id_address_delivery ' . $this->buildGeoConditions($f, 'a') . ')
                    OR EXISTS (SELECT 1 FROM `' . $this->prefix . 'address` a WHERE a.id_address = o.id_address_invoice ' . $this->buildGeoConditions($f, 'a') . ')
                )');
                return;
            }
        } else {
            $sql->innerJoin('address', 'addr', 'addr.id_customer = c.id_customer');
        }

        // Appliquer les conditions géo
        if (!empty($f['id_country'])) {
            $sql->where('addr.id_country IN (' . implode(',', array_map('intval', $f['id_country'])) . ')');
        }

        if (!empty($f['country_iso'])) {
            $sql->innerJoin('country', 'co', 'co.id_country = addr.id_country');
            $isos = array_map(function ($iso) {
                return '\'' . pSQL($iso) . '\'';
            }, $f['country_iso']);
            $sql->where('co.iso_code IN (' . implode(',', $isos) . ')');
        }

        if (!empty($f['id_state'])) {
            $sql->where('addr.id_state IN (' . implode(',', array_map('intval', $f['id_state'])) . ')');
        }

        if (!empty($f['id_zone'])) {
            $sql->innerJoin('country', 'coz', 'coz.id_country = addr.id_country');
            $sql->where('coz.id_zone IN (' . implode(',', array_map('intval', $f['id_zone'])) . ')');
        }

        if (!empty($f['postcode'])) {
            $sql->where('addr.postcode = \'' . pSQL($f['postcode']) . '\'');
        }

        if (!empty($f['postcode_prefix'])) {
            $sql->where('addr.postcode LIKE \'' . pSQL($f['postcode_prefix']) . '%\'');
        }

        if (!empty($f['postcode_range_from']) && !empty($f['postcode_range_to'])) {
            $sql->where('addr.postcode >= \'' . pSQL($f['postcode_range_from']) . '\'');
            $sql->where('addr.postcode <= \'' . pSQL($f['postcode_range_to']) . '\'');
        }

        // Filtres département/région FR
        if (!empty($f['dept_code'])) {
            $depts = array_map(function ($d) {
                return '\'' . pSQL($d) . '\'';
            }, $f['dept_code']);
            $sql->innerJoin('pde_geo_map', 'geo', 'geo.dept_code = LEFT(addr.postcode, IF(LEFT(addr.postcode, 2) IN (\'97\', \'98\'), 3, 2))');
            $sql->where('geo.dept_code IN (' . implode(',', $depts) . ')');
        }

        if (!empty($f['region_code'])) {
            $regions = array_map(function ($r) {
                return '\'' . pSQL($r) . '\'';
            }, $f['region_code']);
            if (empty($f['dept_code'])) {
                $sql->innerJoin('pde_geo_map', 'geo', 'geo.dept_code = LEFT(addr.postcode, IF(LEFT(addr.postcode, 2) IN (\'97\', \'98\'), 3, 2))');
            }
            $sql->where('geo.region_code IN (' . implode(',', $regions) . ')');
        }
    }

    /**
     * Construit les conditions géo pour sous-requête
     */
    private function buildGeoConditions(array $f, $alias)
    {
        $conditions = array();

        if (!empty($f['id_country'])) {
            $conditions[] = $alias . '.id_country IN (' . implode(',', array_map('intval', $f['id_country'])) . ')';
        }
        if (!empty($f['postcode'])) {
            $conditions[] = $alias . '.postcode = \'' . pSQL($f['postcode']) . '\'';
        }
        if (!empty($f['postcode_prefix'])) {
            $conditions[] = $alias . '.postcode LIKE \'' . pSQL($f['postcode_prefix']) . '%\'';
        }

        return !empty($conditions) ? 'AND ' . implode(' AND ', $conditions) : '';
    }

    /**
     * Applique les filtres de valeur client
     */
    private function applyCustomerValueFilters(DbQuery $sql, array $f)
    {
        // Nombre de commandes
        if (!empty($f['order_count_min']) || !empty($f['order_count_max'])) {
            $having = array();
            if (!empty($f['order_count_min'])) {
                $having[] = 'order_count >= ' . (int) $f['order_count_min'];
            }
            if (!empty($f['order_count_max'])) {
                $having[] = 'order_count <= ' . (int) $f['order_count_max'];
            }

            $sql->select('(SELECT COUNT(*) FROM `' . $this->prefix . 'orders` o WHERE o.id_customer = c.id_customer AND o.valid = 1) AS order_count');
            $sql->having(implode(' AND ', $having));
        }

        // LTV (Lifetime Value)
        if (!empty($f['ltv_min']) || !empty($f['ltv_max'])) {
            $having = array();
            if (!empty($f['ltv_min'])) {
                $having[] = 'ltv >= ' . (float) $f['ltv_min'];
            }
            if (!empty($f['ltv_max'])) {
                $having[] = 'ltv <= ' . (float) $f['ltv_max'];
            }

            $sql->select('(SELECT COALESCE(SUM(o.total_paid_tax_incl), 0) FROM `' . $this->prefix . 'orders` o WHERE o.id_customer = c.id_customer AND o.valid = 1) AS ltv');
            $sql->having(implode(' AND ', $having));
        }

        // Panier moyen
        if (!empty($f['avg_basket_min']) || !empty($f['avg_basket_max'])) {
            $having = array();
            if (!empty($f['avg_basket_min'])) {
                $having[] = 'avg_basket >= ' . (float) $f['avg_basket_min'];
            }
            if (!empty($f['avg_basket_max'])) {
                $having[] = 'avg_basket <= ' . (float) $f['avg_basket_max'];
            }

            $sql->select('(SELECT COALESCE(AVG(o.total_paid_tax_incl), 0) FROM `' . $this->prefix . 'orders` o WHERE o.id_customer = c.id_customer AND o.valid = 1) AS avg_basket');
            $sql->having(implode(' AND ', $having));
        }
    }

    /**
     * Récupère les filtres actuels
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Construit la requête enrichie pour les commandes (avec infos client et adresses)
     */
    public function buildEnrichedOrdersQuery($lastId = 0, $limit = 1000)
    {
        $f = $this->filters;

        $sql = 'SELECT
            o.id_order,
            o.reference,
            o.id_customer,
            o.current_state,
            o.payment,
            o.total_paid_tax_incl,
            o.total_paid_tax_excl,
            o.total_products,
            o.total_shipping_tax_incl,
            o.date_add,
            o.valid,
            osl.name AS order_status_name,
            c.email AS customer_email,
            c.firstname AS customer_firstname,
            c.lastname AS customer_lastname,
            c.company AS customer_company,
            c.siret AS customer_siret,
            ad.company AS delivery_company,
            ad.firstname AS delivery_firstname,
            ad.lastname AS delivery_lastname,
            ad.address1 AS delivery_address1,
            ad.address2 AS delivery_address2,
            ad.postcode AS delivery_postcode,
            ad.city AS delivery_city,
            cld.name AS delivery_country,
            ad.phone AS delivery_phone,
            ad.phone_mobile AS delivery_phone_mobile,
            ai.company AS invoice_company,
            ai.firstname AS invoice_firstname,
            ai.lastname AS invoice_lastname,
            ai.address1 AS invoice_address1,
            ai.address2 AS invoice_address2,
            ai.postcode AS invoice_postcode,
            ai.city AS invoice_city,
            cli.name AS invoice_country,
            ai.phone AS invoice_phone
        FROM `' . $this->prefix . 'orders` o
        LEFT JOIN `' . $this->prefix . 'customer` c ON c.id_customer = o.id_customer
        LEFT JOIN `' . $this->prefix . 'address` ad ON ad.id_address = o.id_address_delivery
        LEFT JOIN `' . $this->prefix . 'address` ai ON ai.id_address = o.id_address_invoice
        LEFT JOIN `' . $this->prefix . 'country_lang` cld ON cld.id_country = ad.id_country AND cld.id_lang = ' . (int) $this->idLang . '
        LEFT JOIN `' . $this->prefix . 'country_lang` cli ON cli.id_country = ai.id_country AND cli.id_lang = ' . (int) $this->idLang . '
        LEFT JOIN `' . $this->prefix . 'order_state_lang` osl ON osl.id_order_state = o.current_state AND osl.id_lang = ' . (int) $this->idLang;

        $where = array();

        if ($lastId > 0) {
            $where[] = 'o.id_order > ' . (int) $lastId;
        }

        if ($this->idShop) {
            $where[] = 'o.id_shop = ' . (int) $this->idShop;
        }

        // Appliquer les filtres de base
        if (!empty($f['date_add_from'])) {
            $where[] = 'o.date_add >= \'' . pSQL($f['date_add_from']) . ' 00:00:00\'';
        }
        if (!empty($f['date_add_to'])) {
            $where[] = 'o.date_add <= \'' . pSQL($f['date_add_to']) . ' 23:59:59\'';
        }
        if (!empty($f['current_state'])) {
            $where[] = 'o.current_state IN (' . implode(',', array_map('intval', $f['current_state'])) . ')';
        }

        // Filtres géographiques
        if (!empty($f['id_country'])) {
            $where[] = 'ad.id_country IN (' . implode(',', array_map('intval', $f['id_country'])) . ')';
        }
        // Filtre par département (préfixe code postal)
        if (!empty($f['dept_code'])) {
            $deptConditions = array();
            foreach ($f['dept_code'] as $dept) {
                $dept = pSQL($dept);
                // Gérer les DOM-TOM (97x, 98x) avec 3 caractères
                if (strlen($dept) === 3) {
                    $deptConditions[] = 'ad.postcode LIKE \'' . $dept . '%\'';
                } else {
                    $deptConditions[] = 'LEFT(ad.postcode, 2) = \'' . $dept . '\'';
                }
            }
            $where[] = '(' . implode(' OR ', $deptConditions) . ')';
        }

        // Filtres paiement/transport
        if (!empty($f['payment'])) {
            $where[] = 'o.payment = \'' . pSQL($f['payment']) . '\'';
        }
        if (!empty($f['id_carrier'])) {
            $where[] = 'o.id_carrier IN (' . implode(',', array_map('intval', $f['id_carrier'])) . ')';
        }

        // Filtres montants
        if (!empty($f['total_paid_tax_incl_min'])) {
            $where[] = 'o.total_paid_tax_incl >= ' . (float) $f['total_paid_tax_incl_min'];
        }
        if (!empty($f['total_paid_tax_incl_max'])) {
            $where[] = 'o.total_paid_tax_incl <= ' . (float) $f['total_paid_tax_incl_max'];
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY o.id_order ASC LIMIT ' . (int) $limit;

        return $sql;
    }

    /**
     * Construit la requête aplatie (commandes + détails + clients + adresses)
     */
    public function buildFlatExportQuery($lastId = 0, $limit = 1000)
    {
        $f = $this->filters;

        $sql = 'SELECT
            o.id_order,
            o.reference AS order_reference,
            o.date_add AS order_date,
            osl.name AS order_status,
            o.payment AS payment_method,
            o.total_paid_tax_incl,
            o.total_paid_tax_excl,
            o.total_products,
            o.total_shipping_tax_incl AS total_shipping,
            c.id_customer AS customer_id,
            c.email AS customer_email,
            c.firstname AS customer_firstname,
            c.lastname AS customer_lastname,
            c.company AS customer_company,
            c.newsletter AS customer_newsletter,
            ad.firstname AS delivery_firstname,
            ad.lastname AS delivery_lastname,
            ad.company AS delivery_company,
            ad.address1 AS delivery_address1,
            ad.address2 AS delivery_address2,
            ad.postcode AS delivery_postcode,
            ad.city AS delivery_city,
            cld.name AS delivery_country,
            ad.phone AS delivery_phone,
            ai.firstname AS invoice_firstname,
            ai.lastname AS invoice_lastname,
            ai.company AS invoice_company,
            ai.address1 AS invoice_address1,
            ai.address2 AS invoice_address2,
            ai.postcode AS invoice_postcode,
            ai.city AS invoice_city,
            cli.name AS invoice_country,
            ai.phone AS invoice_phone,
            od.product_id,
            od.product_name,
            od.product_reference,
            od.product_quantity,
            od.total_price_tax_incl AS product_price_tax_incl,
            od.total_price_tax_excl AS product_price_tax_excl
        FROM `' . $this->prefix . 'orders` o
        LEFT JOIN `' . $this->prefix . 'order_detail` od ON od.id_order = o.id_order
        LEFT JOIN `' . $this->prefix . 'customer` c ON c.id_customer = o.id_customer
        LEFT JOIN `' . $this->prefix . 'address` ad ON ad.id_address = o.id_address_delivery
        LEFT JOIN `' . $this->prefix . 'address` ai ON ai.id_address = o.id_address_invoice
        LEFT JOIN `' . $this->prefix . 'country_lang` cld ON cld.id_country = ad.id_country AND cld.id_lang = ' . (int) $this->idLang . '
        LEFT JOIN `' . $this->prefix . 'country_lang` cli ON cli.id_country = ai.id_country AND cli.id_lang = ' . (int) $this->idLang . '
        LEFT JOIN `' . $this->prefix . 'order_state_lang` osl ON osl.id_order_state = o.current_state AND osl.id_lang = ' . (int) $this->idLang;

        $where = array();

        if ($lastId > 0) {
            $where[] = 'o.id_order > ' . (int) $lastId;
        }

        if ($this->idShop) {
            $where[] = 'o.id_shop = ' . (int) $this->idShop;
        }

        // Appliquer les filtres de base
        if (!empty($f['date_add_from'])) {
            $where[] = 'o.date_add >= \'' . pSQL($f['date_add_from']) . ' 00:00:00\'';
        }
        if (!empty($f['date_add_to'])) {
            $where[] = 'o.date_add <= \'' . pSQL($f['date_add_to']) . ' 23:59:59\'';
        }
        if (!empty($f['current_state'])) {
            $where[] = 'o.current_state IN (' . implode(',', array_map('intval', $f['current_state'])) . ')';
        }

        // Filtres géographiques
        if (!empty($f['id_country'])) {
            $where[] = 'ad.id_country IN (' . implode(',', array_map('intval', $f['id_country'])) . ')';
        }
        // Filtre par département (préfixe code postal)
        if (!empty($f['dept_code'])) {
            $deptConditions = array();
            foreach ($f['dept_code'] as $dept) {
                $dept = pSQL($dept);
                // Gérer les DOM-TOM (97x, 98x) avec 3 caractères
                if (strlen($dept) === 3) {
                    $deptConditions[] = 'ad.postcode LIKE \'' . $dept . '%\'';
                } else {
                    $deptConditions[] = 'LEFT(ad.postcode, 2) = \'' . $dept . '\'';
                }
            }
            $where[] = '(' . implode(' OR ', $deptConditions) . ')';
        }

        // Filtres paiement/transport
        if (!empty($f['payment'])) {
            $where[] = 'o.payment = \'' . pSQL($f['payment']) . '\'';
        }
        if (!empty($f['id_carrier'])) {
            $where[] = 'o.id_carrier IN (' . implode(',', array_map('intval', $f['id_carrier'])) . ')';
        }

        // Filtres montants
        if (!empty($f['total_paid_tax_incl_min'])) {
            $where[] = 'o.total_paid_tax_incl >= ' . (float) $f['total_paid_tax_incl_min'];
        }
        if (!empty($f['total_paid_tax_incl_max'])) {
            $where[] = 'o.total_paid_tax_incl <= ' . (float) $f['total_paid_tax_incl_max'];
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY o.id_order ASC, od.id_order_detail ASC LIMIT ' . (int) $limit;

        return $sql;
    }

    /**
     * Compte les lignes pour le mode aplati
     */
    public function countFlatExport()
    {
        $sql = 'SELECT COUNT(*)
        FROM `' . $this->prefix . 'orders` o
        LEFT JOIN `' . $this->prefix . 'order_detail` od ON od.id_order = o.id_order';

        $where = array();

        if ($this->idShop) {
            $where[] = 'o.id_shop = ' . (int) $this->idShop;
        }

        $f = $this->filters;
        if (!empty($f['date_add_from'])) {
            $where[] = 'o.date_add >= \'' . pSQL($f['date_add_from']) . ' 00:00:00\'';
        }
        if (!empty($f['date_add_to'])) {
            $where[] = 'o.date_add <= \'' . pSQL($f['date_add_to']) . ' 23:59:59\'';
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        return (int) Db::getInstance()->getValue($sql);
    }

    /**
     * Compte les commandes pour estimation
     */
    public function countOrders()
    {
        $sql = $this->buildCountQuery('orders');
        return (int) Db::getInstance()->getValue($sql);
    }

    /**
     * Compte les clients pour estimation
     */
    public function countCustomers()
    {
        $sql = $this->buildCountQuery('customers');
        return (int) Db::getInstance()->getValue($sql);
    }
}
