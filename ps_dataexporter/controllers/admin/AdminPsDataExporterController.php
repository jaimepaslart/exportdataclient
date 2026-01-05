<?php
/**
 * AdminPsDataExporterController - Contrôleur Back-Office
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'ps_dataexporter/classes/ExportJob.php';
require_once _PS_MODULE_DIR_ . 'ps_dataexporter/classes/Services/SchemaInspector.php';
require_once _PS_MODULE_DIR_ . 'ps_dataexporter/classes/Services/ExportPlanBuilder.php';
require_once _PS_MODULE_DIR_ . 'ps_dataexporter/classes/Filters/FilterBuilder.php';
require_once _PS_MODULE_DIR_ . 'ps_dataexporter/classes/Filters/QueryBuilder.php';
require_once _PS_MODULE_DIR_ . 'ps_dataexporter/classes/Writers/CsvWriter.php';

class AdminPsDataExporterController extends ModuleAdminController
{
    /** @var Ps_DataExporter */
    public $module;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';

        parent::__construct();

        $this->meta_title = $this->l('Data Exporter');
    }

    /**
     * Initialisation
     */
    public function init()
    {
        parent::init();

        // Ajouter CSS/JS
        $this->addCSS(_PS_MODULE_DIR_ . 'ps_dataexporter/views/css/admin/admin.css');
        $this->addJS(_PS_MODULE_DIR_ . 'ps_dataexporter/views/js/admin/admin.js');
    }

    /**
     * Traitement des actions POST
     */
    public function postProcess()
    {
        parent::postProcess();

        // Action AJAX estimation
        if (Tools::isSubmit('ajax') && Tools::getValue('action') === 'estimate') {
            $this->ajaxEstimate();
        }

        // Action AJAX progression
        if (Tools::isSubmit('ajax') && Tools::getValue('action') === 'progress') {
            $this->ajaxProgress();
        }

        // Action AJAX batch
        if (Tools::isSubmit('ajax') && Tools::getValue('action') === 'runBatch') {
            $this->ajaxRunBatch();
        }

        // Créer un nouvel export
        if (Tools::isSubmit('submitNewExport')) {
            $this->processNewExport();
        }

        // Continuer un export
        if (Tools::isSubmit('continueExport')) {
            $this->processContinueExport();
        }

        // Supprimer un export
        if (Tools::isSubmit('deleteExport')) {
            $this->processDeleteExport();
        }

        // Télécharger un fichier
        if (Tools::getValue('action') === 'download') {
            $this->processDownload();
        }

        // Sauvegarder les paramètres
        if (Tools::isSubmit('submitSettings')) {
            $this->processSaveSettings();
        }
    }

    /**
     * Affichage principal
     */
    public function renderView()
    {
        $tab = Tools::getValue('tab', 'new');

        $this->context->smarty->assign(array(
            'current_tab' => $tab,
            'module_dir' => _PS_MODULE_DIR_ . 'ps_dataexporter/',
            'ajax_url' => $this->context->link->getAdminLink('AdminPsDataExporter'),
            'token' => $this->token,
        ));

        $content = '';

        switch ($tab) {
            case 'new':
                $content = $this->renderNewExportForm();
                break;
            case 'progress':
                $content = $this->renderProgressView();
                break;
            case 'history':
                $content = $this->renderHistoryView();
                break;
            case 'settings':
                $content = $this->renderSettingsForm();
                break;
            default:
                $content = $this->renderNewExportForm();
        }

        return $this->renderTabs() . $content;
    }

    /**
     * Rendu des onglets
     */
    private function renderTabs()
    {
        $tabs = array(
            'new' => array('icon' => 'icon-plus', 'label' => $this->l('Nouvel export')),
            'progress' => array('icon' => 'icon-tasks', 'label' => $this->l('Progression')),
            'history' => array('icon' => 'icon-history', 'label' => $this->l('Historique')),
            'settings' => array('icon' => 'icon-cogs', 'label' => $this->l('Paramètres')),
        );

        $currentTab = Tools::getValue('tab', 'new');

        $this->context->smarty->assign(array(
            'tabs' => $tabs,
            'current_tab' => $currentTab,
            'base_url' => $this->context->link->getAdminLink('AdminPsDataExporter'),
        ));

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'ps_dataexporter/views/templates/admin/tabs.tpl');
    }

    /**
     * Formulaire nouvel export
     */
    private function renderNewExportForm()
    {
        $planBuilder = new PdeExportPlanBuilder();

        // Récupérer les données pour les selects
        $shops = Shop::getShops();
        $languages = Language::getLanguages(false);
        $currencies = Currency::getCurrencies();
        $carriers = Carrier::getCarriers($this->context->language->id, true);
        $orderStates = OrderState::getOrderStates($this->context->language->id);
        $groups = Group::getGroups($this->context->language->id);
        $countries = Country::getCountries($this->context->language->id);

        // Régions FR
        $regions = Db::getInstance()->executeS(
            'SELECT DISTINCT region_code, region_name FROM `' . _DB_PREFIX_ . 'pde_geo_map` ORDER BY region_name'
        );

        // Départements FR
        $departments = Db::getInstance()->executeS(
            'SELECT dept_code, dept_name FROM `' . _DB_PREFIX_ . 'pde_geo_map` ORDER BY dept_code'
        );

        $this->context->smarty->assign(array(
            'export_types' => PdeExportPlanBuilder::getTypes(),
            'export_levels' => PdeExportPlanBuilder::getLevels(),
            'export_modes' => PdeExportPlanBuilder::getModes(),
            'filter_groups' => PdeFilterBuilder::getFilterGroups(),
            'filter_definitions' => PdeFilterBuilder::getFilterDefinitions(),
            'shops' => $shops,
            'languages' => $languages,
            'currencies' => $currencies,
            'carriers' => $carriers,
            'order_states' => $orderStates,
            'customer_groups' => $groups,
            'countries' => $countries,
            'regions' => $regions,
            'departments' => $departments,
            'form_action' => $this->context->link->getAdminLink('AdminPsDataExporter') . '&tab=new',
        ));

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'ps_dataexporter/views/templates/admin/new_export.tpl');
    }

    /**
     * Vue progression
     */
    private function renderProgressView()
    {
        // Jobs en cours ou en pause
        $runningJobs = PdeExportJob::getByStatus(PdeExportJob::STATUS_RUNNING, 10);
        $pausedJobs = PdeExportJob::getByStatus(PdeExportJob::STATUS_PAUSED, 10);

        $jobs = array_merge($runningJobs, $pausedJobs);

        // Enrichir avec les données
        foreach ($jobs as &$job) {
            $jobObj = new PdeExportJob($job['id_export_job']);
            $job['progress'] = $jobObj->getProgressPercent();
            $job['files'] = $jobObj->getFiles();
        }

        $this->context->smarty->assign(array(
            'jobs' => $jobs,
            'ajax_url' => $this->context->link->getAdminLink('AdminPsDataExporter'),
        ));

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'ps_dataexporter/views/templates/admin/progress.tpl');
    }

    /**
     * Vue historique
     */
    private function renderHistoryView()
    {
        $jobs = PdeExportJob::getRecent(50);

        // Enrichir avec les fichiers
        foreach ($jobs as &$job) {
            $jobObj = new PdeExportJob($job['id_export_job']);
            $job['files'] = $jobObj->getFiles();
            $job['progress'] = $jobObj->getProgressPercent();
        }

        $this->context->smarty->assign(array(
            'jobs' => $jobs,
            'download_url' => $this->context->link->getAdminLink('AdminPsDataExporter') . '&action=download',
        ));

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'ps_dataexporter/views/templates/admin/history.tpl');
    }

    /**
     * Formulaire paramètres
     */
    private function renderSettingsForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Paramètres d\'export'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Taille des batchs'),
                        'name' => 'PDE_BATCH_SIZE',
                        'desc' => $this->l('Nombre d\'enregistrements par batch (recommandé: 500-2000)'),
                        'class' => 'fixed-width-sm',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Séparateur CSV'),
                        'name' => 'PDE_CSV_SEPARATOR',
                        'class' => 'fixed-width-xs',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('TTL liens (heures)'),
                        'name' => 'PDE_LINK_TTL_HOURS',
                        'desc' => $this->l('Durée de validité des liens de téléchargement'),
                        'class' => 'fixed-width-sm',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Nettoyage auto (jours)'),
                        'name' => 'PDE_AUTO_CLEANUP_DAYS',
                        'desc' => $this->l('Supprimer les exports plus anciens que X jours (0 = désactivé)'),
                        'class' => 'fixed-width-sm',
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Inclure colonnes custom'),
                        'name' => 'PDE_INCLUDE_CUSTOM_COLUMNS',
                        'desc' => $this->l('Exporter les colonnes ajoutées par des modules'),
                        'values' => array(
                            array('id' => 'on', 'value' => 1),
                            array('id' => 'off', 'value' => 0),
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Inclure tables custom'),
                        'name' => 'PDE_INCLUDE_CUSTOM_TABLES',
                        'desc' => $this->l('Exporter les tables liées ajoutées par des modules'),
                        'values' => array(
                            array('id' => 'on', 'value' => 1),
                            array('id' => 'off', 'value' => 0),
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Anonymiser les données'),
                        'name' => 'PDE_ANONYMIZE_DATA',
                        'desc' => $this->l('Masquer les données personnelles (email, téléphone, etc.)'),
                        'values' => array(
                            array('id' => 'on', 'value' => 1),
                            array('id' => 'off', 'value' => 0),
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Sauvegarder'),
                ),
            ),
        );

        // Section sécurité cron
        $fields_form_cron = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Sécurité & Cron'),
                    'icon' => 'icon-lock',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Token Cron'),
                        'name' => 'PDE_CRON_TOKEN',
                        'desc' => $this->l('Token pour l\'accès cron sécurisé'),
                        'readonly' => true,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('IP autorisées (cron)'),
                        'name' => 'PDE_CRON_IP_WHITELIST',
                        'desc' => $this->l('Une IP par ligne. Laisser vide pour désactiver le cron.'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Sauvegarder'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->module = $this->module;
        $helper->token = $this->token;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminPsDataExporter') . '&tab=settings';
        $helper->submit_action = 'submitSettings';
        $helper->show_toolbar = false;

        $helper->fields_value = array(
            'PDE_BATCH_SIZE' => Configuration::get('PDE_BATCH_SIZE'),
            'PDE_CSV_SEPARATOR' => Configuration::get('PDE_CSV_SEPARATOR'),
            'PDE_LINK_TTL_HOURS' => Configuration::get('PDE_LINK_TTL_HOURS'),
            'PDE_AUTO_CLEANUP_DAYS' => Configuration::get('PDE_AUTO_CLEANUP_DAYS'),
            'PDE_INCLUDE_CUSTOM_COLUMNS' => Configuration::get('PDE_INCLUDE_CUSTOM_COLUMNS'),
            'PDE_INCLUDE_CUSTOM_TABLES' => Configuration::get('PDE_INCLUDE_CUSTOM_TABLES'),
            'PDE_ANONYMIZE_DATA' => Configuration::get('PDE_ANONYMIZE_DATA'),
            'PDE_CRON_TOKEN' => Configuration::get('PDE_CRON_TOKEN'),
            'PDE_CRON_IP_WHITELIST' => Configuration::get('PDE_CRON_IP_WHITELIST'),
        );

        return $helper->generateForm(array($fields_form, $fields_form_cron));
    }

    /**
     * Traitement création nouvel export
     */
    private function processNewExport()
    {
        // Validation CSRF
        if (!$this->isTokenValid()) {
            $this->errors[] = $this->l('Token de sécurité invalide.');
            return;
        }

        // Récupérer les paramètres
        $exportType = Tools::getValue('export_type', 'orders');
        $exportLevel = Tools::getValue('export_level', 'essential');
        $exportMode = Tools::getValue('export_mode', 'relational');

        // Valider les valeurs
        if (!in_array($exportType, array('customers', 'orders', 'full'))) {
            $this->errors[] = $this->l('Type d\'export invalide.');
            return;
        }
        if (!in_array($exportLevel, array('essential', 'complete', 'ultra'))) {
            $this->errors[] = $this->l('Niveau d\'export invalide.');
            return;
        }
        if (!in_array($exportMode, array('relational', 'flat'))) {
            $this->errors[] = $this->l('Mode d\'export invalide.');
            return;
        }

        // Parser les filtres
        $filterBuilder = new PdeFilterBuilder();
        if (!$filterBuilder->parseFromRequest($_POST)) {
            $this->errors = array_merge($this->errors, $filterBuilder->getErrors());
            return;
        }

        // Créer le job
        $job = new PdeExportJob();
        $job->id_employee = (int) $this->context->employee->id;
        $job->id_shop = Shop::isFeatureActive() ? (int) $this->context->shop->id : null;
        $job->export_type = pSQL($exportType);
        $job->export_level = pSQL($exportLevel);
        $job->export_mode = pSQL($exportMode);
        $job->setParams($filterBuilder->getFilters());
        $job->status = PdeExportJob::STATUS_PENDING;
        $job->date_add = date('Y-m-d H:i:s');
        $job->date_upd = date('Y-m-d H:i:s');

        // Construire le plan d'export
        $includeCustomColumns = (bool) Configuration::get('PDE_INCLUDE_CUSTOM_COLUMNS');
        $includeCustomTables = (bool) Configuration::get('PDE_INCLUDE_CUSTOM_TABLES');
        $planBuilder = new PdeExportPlanBuilder($includeCustomColumns, $includeCustomTables);
        $plan = $planBuilder->buildPlan($exportType, $exportLevel);

        // Sauvegarder le schema snapshot
        $job->setSchemaSnapshot($plan['schema']);

        // Estimer le nombre d'enregistrements
        $job->total_records = $planBuilder->estimateTotalRecords($plan, $filterBuilder->getFilters());

        if (!$job->add()) {
            $this->errors[] = $this->l('Erreur lors de la création du job.');
            return;
        }

        $job->log('info', 'Job créé', array(
            'type' => $exportType,
            'level' => $exportLevel,
            'estimated_records' => $job->total_records,
        ));

        $this->confirmations[] = sprintf(
            $this->l('Export #%d créé avec succès. %d enregistrements estimés.'),
            $job->id,
            $job->total_records
        );

        // Rediriger vers la progression
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminPsDataExporter') . '&tab=progress');
    }

    /**
     * Sauvegarde des paramètres
     */
    private function processSaveSettings()
    {
        if (!$this->isTokenValid()) {
            $this->errors[] = $this->l('Token de sécurité invalide.');
            return;
        }

        $keys = array(
            'PDE_BATCH_SIZE',
            'PDE_CSV_SEPARATOR',
            'PDE_LINK_TTL_HOURS',
            'PDE_AUTO_CLEANUP_DAYS',
            'PDE_INCLUDE_CUSTOM_COLUMNS',
            'PDE_INCLUDE_CUSTOM_TABLES',
            'PDE_ANONYMIZE_DATA',
            'PDE_CRON_IP_WHITELIST',
        );

        foreach ($keys as $key) {
            $value = Tools::getValue($key);
            Configuration::updateValue($key, pSQL($value));
        }

        $this->confirmations[] = $this->l('Paramètres sauvegardés.');
    }

    /**
     * AJAX : Estimation count
     */
    private function ajaxEstimate()
    {
        $response = array('success' => false);

        try {
            $filterBuilder = new PdeFilterBuilder();
            $filterBuilder->parseFromRequest($_POST);

            $exportType = Tools::getValue('export_type', 'orders');
            $exportLevel = Tools::getValue('export_level', 'essential');

            $planBuilder = new PdeExportPlanBuilder();
            $plan = $planBuilder->buildPlan($exportType, $exportLevel);

            $count = $planBuilder->estimateTotalRecords($plan, $filterBuilder->getFilters());

            $response = array(
                'success' => true,
                'count' => $count,
                'formatted' => number_format($count, 0, ',', ' '),
            );
        } catch (Exception $e) {
            $response['error'] = $e->getMessage();
        }

        die(json_encode($response));
    }

    /**
     * AJAX : Progression job
     */
    private function ajaxProgress()
    {
        $jobId = (int) Tools::getValue('id_job');
        $job = new PdeExportJob($jobId);

        if (!Validate::isLoadedObject($job)) {
            die(json_encode(array('success' => false, 'error' => 'Job non trouvé')));
        }

        die(json_encode(array(
            'success' => true,
            'status' => $job->status,
            'progress' => $job->getProgressPercent(),
            'processed' => $job->processed_records,
            'total' => $job->total_records,
            'current_entity' => $job->current_entity,
        )));
    }

    /**
     * AJAX : Exécuter un batch
     */
    private function ajaxRunBatch()
    {
        $jobId = (int) Tools::getValue('id_job');
        $job = new PdeExportJob($jobId);

        if (!Validate::isLoadedObject($job)) {
            die(json_encode(array('success' => false, 'error' => 'Job non trouvé')));
        }

        try {
            // Charger l'exporter approprié
            require_once _PS_MODULE_DIR_ . 'ps_dataexporter/classes/Exporters/BatchExporter.php';

            $exporter = new PdeBatchExporter($job);
            $result = $exporter->runBatch();

            die(json_encode(array(
                'success' => true,
                'status' => $job->status,
                'progress' => $job->getProgressPercent(),
                'processed' => $job->processed_records,
                'total' => $job->total_records,
                'current_entity' => $job->current_entity,
                'completed' => ($job->status === PdeExportJob::STATUS_COMPLETED),
            )));
        } catch (Exception $e) {
            $job->log('error', $e->getMessage());
            $job->fail($e->getMessage());

            die(json_encode(array(
                'success' => false,
                'error' => $e->getMessage(),
            )));
        }
    }

    /**
     * Téléchargement sécurisé
     */
    private function processDownload()
    {
        $token = Tools::getValue('file_token');
        $fileId = (int) Tools::getValue('id_file');

        if (empty($token) || $fileId <= 0) {
            die($this->l('Paramètres invalides.'));
        }

        // Récupérer le fichier
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('pde_export_file');
        $sql->where('id_export_file = ' . $fileId);
        $sql->where('download_token = \'' . pSQL($token) . '\'');
        $sql->where('expires_at > NOW()');

        $file = Db::getInstance()->getRow($sql);

        if (!$file) {
            die($this->l('Fichier non trouvé ou lien expiré.'));
        }

        // Vérifier que le fichier existe
        if (!file_exists($file['filepath'])) {
            die($this->l('Fichier introuvable sur le serveur.'));
        }

        // Incrémenter le compteur de téléchargements
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'pde_export_file`
             SET download_count = download_count + 1
             WHERE id_export_file = ' . $fileId
        );

        // Envoyer le fichier
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . $file['filesize']);

        readfile($file['filepath']);
        exit;
    }

    /**
     * Suppression d'un export
     */
    private function processDeleteExport()
    {
        if (!$this->isTokenValid()) {
            $this->errors[] = $this->l('Token de sécurité invalide.');
            return;
        }

        $jobId = (int) Tools::getValue('id_job');
        $job = new PdeExportJob($jobId);

        if (!Validate::isLoadedObject($job)) {
            $this->errors[] = $this->l('Job non trouvé.');
            return;
        }

        // Supprimer les fichiers physiques
        $files = $job->getFiles();
        foreach ($files as $file) {
            if (file_exists($file['filepath'])) {
                @unlink($file['filepath']);
            }
        }

        // Supprimer en base
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'pde_export_log` WHERE id_export_job = ' . $jobId
        );
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'pde_export_file` WHERE id_export_job = ' . $jobId
        );
        $job->delete();

        $this->confirmations[] = $this->l('Export supprimé.');
    }

    /**
     * Continuer un export en pause
     */
    private function processContinueExport()
    {
        $jobId = (int) Tools::getValue('id_job');

        // Rediriger vers la page de progression avec auto-start
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminPsDataExporter') . '&tab=progress&start_job=' . $jobId
        );
    }

    /**
     * Vérifie le token CSRF
     */
    private function isTokenValid()
    {
        return Tools::getValue('token') === $this->token;
    }
}
