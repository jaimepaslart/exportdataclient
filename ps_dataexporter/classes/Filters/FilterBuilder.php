<?php
/**
 * FilterBuilder - Parse et valide les filtres d'export
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PdeFilterBuilder
{
    /** @var array Erreurs de validation */
    private $errors = array();

    /** @var array Filtres validés */
    private $filters = array();

    /**
     * Définition des filtres disponibles avec leur validation
     */
    private static $filterDefinitions = array(
        // Filtres communs
        'date_add_from' => array('type' => 'date', 'label' => 'Date création depuis'),
        'date_add_to' => array('type' => 'date', 'label' => 'Date création jusqu\'à'),
        'date_upd_from' => array('type' => 'date', 'label' => 'Date MAJ depuis'),
        'date_upd_to' => array('type' => 'date', 'label' => 'Date MAJ jusqu\'à'),
        'id_shop' => array('type' => 'int', 'label' => 'Boutique'),
        'id_shop_multi' => array('type' => 'int_array', 'label' => 'Boutiques'),
        'id_lang' => array('type' => 'int', 'label' => 'Langue'),
        'id_currency' => array('type' => 'int', 'label' => 'Devise'),
        'id_min' => array('type' => 'int', 'label' => 'ID minimum'),
        'id_max' => array('type' => 'int', 'label' => 'ID maximum'),
        'incremental' => array('type' => 'bool', 'label' => 'Export incrémental'),

        // Filtres montants commandes
        'total_paid_tax_incl_min' => array('type' => 'float', 'label' => 'Total TTC min'),
        'total_paid_tax_incl_max' => array('type' => 'float', 'label' => 'Total TTC max'),
        'total_paid_tax_excl_min' => array('type' => 'float', 'label' => 'Total HT min'),
        'total_paid_tax_excl_max' => array('type' => 'float', 'label' => 'Total HT max'),
        'total_products_min' => array('type' => 'float', 'label' => 'Total produits min'),
        'total_products_max' => array('type' => 'float', 'label' => 'Total produits max'),
        'total_shipping_min' => array('type' => 'float', 'label' => 'Frais port min'),
        'total_shipping_max' => array('type' => 'float', 'label' => 'Frais port max'),
        'total_discounts_min' => array('type' => 'float', 'label' => 'Réductions min'),
        'total_discounts_max' => array('type' => 'float', 'label' => 'Réductions max'),

        // Filtres statuts commandes
        'current_state' => array('type' => 'int_array', 'label' => 'Statut actuel'),
        'has_had_state' => array('type' => 'int_array', 'label' => 'A eu le statut'),
        'never_had_state' => array('type' => 'int_array', 'label' => 'N\'a jamais eu le statut'),
        'order_late_days' => array('type' => 'int', 'label' => 'Commandes en retard (jours)'),
        'order_late_exclude_states' => array('type' => 'int_array', 'label' => 'États finaux à exclure'),

        // Filtres paiement/transport
        'payment' => array('type' => 'string', 'label' => 'Méthode paiement'),
        'payment_contains' => array('type' => 'string', 'label' => 'Paiement contient'),
        'module' => array('type' => 'string', 'label' => 'Module paiement'),
        'has_payment' => array('type' => 'bool', 'label' => 'Paiement enregistré'),
        'id_carrier' => array('type' => 'int_array', 'label' => 'Transporteur'),
        'free_shipping' => array('type' => 'bool', 'label' => 'Livraison gratuite'),
        'has_tracking' => array('type' => 'bool', 'label' => 'Tracking présent'),
        'has_invoice' => array('type' => 'bool', 'label' => 'Facture générée'),
        'has_slip' => array('type' => 'bool', 'label' => 'Avoir présent'),
        'has_return' => array('type' => 'bool', 'label' => 'Retour présent'),
        'different_addresses' => array('type' => 'bool', 'label' => 'Adresses différentes'),

        // Filtres produits
        'has_product' => array('type' => 'int_array', 'label' => 'Contient produit'),
        'has_product_attribute' => array('type' => 'int_array', 'label' => 'Contient déclinaison'),
        'product_reference' => array('type' => 'string', 'label' => 'Référence produit'),
        'product_reference_contains' => array('type' => 'string', 'label' => 'Référence contient'),
        'product_ean' => array('type' => 'string', 'label' => 'EAN produit'),
        'id_manufacturer' => array('type' => 'int_array', 'label' => 'Fabricant'),
        'id_category' => array('type' => 'int_array', 'label' => 'Catégorie'),
        'has_product_discount' => array('type' => 'bool', 'label' => 'Produit en promo'),

        // Filtres coupons
        'has_coupon' => array('type' => 'bool', 'label' => 'A utilisé un coupon'),
        'coupon_code' => array('type' => 'string', 'label' => 'Code coupon exact'),
        'coupon_code_contains' => array('type' => 'string', 'label' => 'Code coupon contient'),
        'coupon_discount_min' => array('type' => 'float', 'label' => 'Réduction coupon min'),
        'coupon_discount_max' => array('type' => 'float', 'label' => 'Réduction coupon max'),
        'coupon_count_min' => array('type' => 'int', 'label' => 'Nb coupons min'),

        // Filtres géographiques
        'address_type' => array('type' => 'string', 'label' => 'Type adresse', 'values' => array('delivery', 'invoice', 'both')),
        'id_country' => array('type' => 'int_array', 'label' => 'Pays'),
        'country_iso' => array('type' => 'string_array', 'label' => 'Code ISO pays'),
        'id_state' => array('type' => 'int_array', 'label' => 'État/Province'),
        'state_iso' => array('type' => 'string_array', 'label' => 'Code ISO état'),
        'id_zone' => array('type' => 'int_array', 'label' => 'Zone'),
        'postcode' => array('type' => 'string', 'label' => 'Code postal exact'),
        'postcode_prefix' => array('type' => 'string', 'label' => 'Préfixe code postal'),
        'postcode_range_from' => array('type' => 'string', 'label' => 'Code postal depuis'),
        'postcode_range_to' => array('type' => 'string', 'label' => 'Code postal jusqu\'à'),
        'dept_code' => array('type' => 'string_array', 'label' => 'Département FR'),
        'region_code' => array('type' => 'string_array', 'label' => 'Région FR'),

        // Filtres clients
        'customer_active' => array('type' => 'bool', 'label' => 'Client actif'),
        'id_default_group' => array('type' => 'int_array', 'label' => 'Groupe par défaut'),
        'in_group' => array('type' => 'int_array', 'label' => 'Appartient au groupe'),
        'newsletter' => array('type' => 'bool', 'label' => 'Inscrit newsletter'),
        'optin' => array('type' => 'bool', 'label' => 'Optin partenaires'),
        'is_prospect' => array('type' => 'bool', 'label' => 'Prospect (jamais commandé)'),
        'customer_email' => array('type' => 'string', 'label' => 'Email exact'),
        'customer_email_contains' => array('type' => 'string', 'label' => 'Email contient'),
        'customer_id_lang' => array('type' => 'int', 'label' => 'Langue client'),
        'last_visit_days_ago' => array('type' => 'int', 'label' => 'Dernière visite > X jours'),
        'has_phone' => array('type' => 'bool', 'label' => 'Téléphone renseigné'),
        'has_postcode' => array('type' => 'bool', 'label' => 'Code postal renseigné'),
        'has_complete_address' => array('type' => 'bool', 'label' => 'Adresse complète'),

        // Filtres B2B
        'has_company' => array('type' => 'bool', 'label' => 'Société renseignée'),
        'has_siret' => array('type' => 'bool', 'label' => 'SIRET renseigné'),
        'has_vat_number' => array('type' => 'bool', 'label' => 'N° TVA renseigné'),
        'order_count_min' => array('type' => 'int', 'label' => 'Nb commandes min'),
        'order_count_max' => array('type' => 'int', 'label' => 'Nb commandes max'),
        'ltv_min' => array('type' => 'float', 'label' => 'LTV min'),
        'ltv_max' => array('type' => 'float', 'label' => 'LTV max'),
        'avg_basket_min' => array('type' => 'float', 'label' => 'Panier moyen min'),
        'avg_basket_max' => array('type' => 'float', 'label' => 'Panier moyen max'),
    );

    /**
     * Parse et valide les filtres depuis les données POST
     */
    public function parseFromRequest(array $data)
    {
        $this->errors = array();
        $this->filters = array();

        foreach (self::$filterDefinitions as $key => $definition) {
            if (isset($data[$key]) && $data[$key] !== '' && $data[$key] !== null) {
                $value = $data[$key];
                $validated = $this->validateFilter($key, $value, $definition);

                if ($validated !== null) {
                    $this->filters[$key] = $validated;
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Valide un filtre individuel
     */
    private function validateFilter($key, $value, array $definition)
    {
        $type = $definition['type'];
        $label = $definition['label'];

        switch ($type) {
            case 'int':
                if (!is_numeric($value) || (int) $value < 0) {
                    $this->errors[] = sprintf('Le filtre "%s" doit être un entier positif.', $label);
                    return null;
                }
                return (int) $value;

            case 'float':
                if (!is_numeric($value)) {
                    $this->errors[] = sprintf('Le filtre "%s" doit être un nombre.', $label);
                    return null;
                }
                return (float) $value;

            case 'bool':
                return (bool) $value;

            case 'string':
                $value = trim($value);
                if (isset($definition['values']) && !in_array($value, $definition['values'])) {
                    $this->errors[] = sprintf('Le filtre "%s" a une valeur invalide.', $label);
                    return null;
                }
                // Protection contre l'injection SQL
                if (!Validate::isCleanHtml($value)) {
                    $this->errors[] = sprintf('Le filtre "%s" contient des caractères invalides.', $label);
                    return null;
                }
                return $value;

            case 'date':
                if (!Validate::isDate($value)) {
                    $this->errors[] = sprintf('Le filtre "%s" doit être une date valide (AAAA-MM-JJ).', $label);
                    return null;
                }
                return $value;

            case 'int_array':
                if (!is_array($value)) {
                    $value = array_map('trim', explode(',', $value));
                }
                $result = array();
                foreach ($value as $v) {
                    if ($v !== '' && is_numeric($v) && (int) $v >= 0) {
                        $result[] = (int) $v;
                    }
                }
                return !empty($result) ? $result : null;

            case 'string_array':
                if (!is_array($value)) {
                    $value = array_map('trim', explode(',', $value));
                }
                $result = array();
                foreach ($value as $v) {
                    $v = trim($v);
                    if ($v !== '' && Validate::isCleanHtml($v)) {
                        $result[] = $v;
                    }
                }
                return !empty($result) ? $result : null;

            default:
                return null;
        }
    }

    /**
     * Récupère les filtres validés
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Récupère les erreurs de validation
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Vérifie si des filtres sont définis
     */
    public function hasFilters()
    {
        return !empty($this->filters);
    }

    /**
     * Récupère la définition des filtres pour l'affichage BO
     */
    public static function getFilterDefinitions()
    {
        return self::$filterDefinitions;
    }

    /**
     * Récupère les filtres groupés par catégorie pour l'affichage BO
     */
    public static function getFilterGroups()
    {
        return array(
            'common' => array(
                'label' => 'Filtres communs',
                'filters' => array(
                    'date_add_from', 'date_add_to', 'date_upd_from', 'date_upd_to',
                    'id_shop', 'id_lang', 'id_currency', 'id_min', 'id_max', 'incremental'
                ),
            ),
            'amounts' => array(
                'label' => 'Montants commandes',
                'filters' => array(
                    'total_paid_tax_incl_min', 'total_paid_tax_incl_max',
                    'total_paid_tax_excl_min', 'total_paid_tax_excl_max',
                    'total_products_min', 'total_products_max',
                    'total_shipping_min', 'total_shipping_max',
                    'total_discounts_min', 'total_discounts_max'
                ),
            ),
            'status' => array(
                'label' => 'Statuts commandes',
                'filters' => array(
                    'current_state', 'has_had_state', 'never_had_state',
                    'order_late_days', 'order_late_exclude_states'
                ),
            ),
            'payment' => array(
                'label' => 'Paiement & Transport',
                'filters' => array(
                    'payment', 'payment_contains', 'module', 'has_payment',
                    'id_carrier', 'free_shipping', 'has_tracking',
                    'has_invoice', 'has_slip', 'has_return', 'different_addresses'
                ),
            ),
            'products' => array(
                'label' => 'Produits',
                'filters' => array(
                    'has_product', 'has_product_attribute',
                    'product_reference', 'product_reference_contains',
                    'product_ean', 'id_manufacturer', 'id_category', 'has_product_discount'
                ),
            ),
            'coupons' => array(
                'label' => 'Coupons',
                'filters' => array(
                    'has_coupon', 'coupon_code', 'coupon_code_contains',
                    'coupon_discount_min', 'coupon_discount_max', 'coupon_count_min'
                ),
            ),
            'geo' => array(
                'label' => 'Géographie',
                'filters' => array(
                    'address_type', 'id_country', 'country_iso', 'id_state', 'state_iso',
                    'id_zone', 'postcode', 'postcode_prefix',
                    'postcode_range_from', 'postcode_range_to',
                    'dept_code', 'region_code'
                ),
            ),
            'customers' => array(
                'label' => 'Profil clients',
                'filters' => array(
                    'customer_active', 'id_default_group', 'in_group',
                    'newsletter', 'optin', 'is_prospect',
                    'customer_email', 'customer_email_contains', 'customer_id_lang',
                    'last_visit_days_ago', 'has_phone', 'has_postcode', 'has_complete_address'
                ),
            ),
            'b2b' => array(
                'label' => 'B2B & Valeur client',
                'filters' => array(
                    'has_company', 'has_siret', 'has_vat_number',
                    'order_count_min', 'order_count_max',
                    'ltv_min', 'ltv_max', 'avg_basket_min', 'avg_basket_max'
                ),
            ),
        );
    }

    /**
     * Sérialise les filtres pour stockage
     */
    public function serialize()
    {
        return json_encode($this->filters, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Désérialise les filtres
     */
    public function unserialize($json)
    {
        $data = json_decode($json, true);
        if (is_array($data)) {
            $this->filters = $data;
            return true;
        }
        return false;
    }

    /**
     * Crée une instance depuis des filtres sérialisés
     */
    public static function fromJson($json)
    {
        $instance = new self();
        $instance->unserialize($json);
        return $instance;
    }
}
