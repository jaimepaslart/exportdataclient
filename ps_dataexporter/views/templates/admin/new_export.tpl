{*
 * New export form
 *}
<div class="panel pde-new-export">
    <div class="panel-heading">
        <i class="icon-download"></i> {l s='Nouvel export de donnees' mod='ps_dataexporter'}
    </div>
    <div class="panel-body">
        <form id="pde-export-form" class="form-horizontal" method="post" action="{$form_action|escape:'html':'UTF-8'}">
            <input type="hidden" name="submitNewExport" value="1" />
            <input type="hidden" name="pde_action" value="new_export" />
            <input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}" />

            {* Type d'export *}
            <div class="form-group">
                <label class="control-label col-lg-3 required">
                    {l s='Type d\'export' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <select name="export_type" id="export_type" class="form-control fixed-width-xl">
                        {foreach from=$export_types key=type_key item=type_label}
                            <option value="{$type_key|escape:'html':'UTF-8'}">{$type_label|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
            </div>

            {* Niveau de detail *}
            <div class="form-group">
                <label class="control-label col-lg-3 required">
                    {l s='Niveau de detail' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <select name="export_level" id="export_level" class="form-control fixed-width-xl">
                        {foreach from=$export_levels key=level_key item=level_label}
                            <option value="{$level_key|escape:'html':'UTF-8'}">{$level_label|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                    <p class="help-block">
                        <strong>Essentiel :</strong> {l s='Clients + Commandes de base' mod='ps_dataexporter'}<br/>
                        <strong>Complet :</strong> {l s='+ Paiements, Transport, Etats, Factures, Coupons' mod='ps_dataexporter'}<br/>
                        <strong>Ultra :</strong> {l s='+ Paniers, Connexions, Messages, Retours' mod='ps_dataexporter'}
                    </p>
                </div>
            </div>

            {* Mode d'export *}
            <div class="form-group">
                <label class="control-label col-lg-3">
                    {l s='Mode d\'export' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <select name="export_mode" id="export_mode" class="form-control fixed-width-xl">
                        {foreach from=$export_modes key=mode_key item=mode_label}
                            <option value="{$mode_key|escape:'html':'UTF-8'}">{$mode_label|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                    <p class="help-block">
                        <strong>Relationnel :</strong> {l s='1 fichier par entite (orders.csv, customer.csv, order_detail.csv...)' mod='ps_dataexporter'}<br/>
                        <strong>Enrichi :</strong> {l s='Commandes avec infos client/adresse dans un seul fichier' mod='ps_dataexporter'}<br/>
                        <strong>Aplati :</strong> {l s='Fichier unique avec commandes + details produits + clients (1 ligne par produit commande)' mod='ps_dataexporter'}
                    </p>
                </div>
            </div>

            <hr/>
            <h4><i class="icon-filter"></i> {l s='Filtres' mod='ps_dataexporter'}</h4>

            {* Boutique (multi-shop) *}
            {if $is_multishop}
            <div class="form-group">
                <label class="control-label col-lg-3">
                    {l s='Boutique' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <select name="id_shop" class="form-control fixed-width-xl">
                        <option value="">{l s='Toutes les boutiques' mod='ps_dataexporter'}</option>
                        {foreach from=$shops item=shop}
                            <option value="{$shop.id_shop|intval}">{$shop.name|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
            </div>
            {/if}

            {* Periode *}
            <div class="form-group">
                <label class="control-label col-lg-3">
                    {l s='Periode' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <div class="row">
                        <div class="col-lg-5">
                            <div class="input-group">
                                <span class="input-group-addon">{l s='Du' mod='ps_dataexporter'}</span>
                                <input type="date" name="date_add_from" class="form-control" />
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="input-group">
                                <span class="input-group-addon">{l s='Au' mod='ps_dataexporter'}</span>
                                <input type="date" name="date_add_to" class="form-control" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Filtres montants *}
            <div class="form-group pde-filter-group" data-filter-type="orders">
                <label class="control-label col-lg-3">
                    {l s='Montant commande' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <div class="row">
                        <div class="col-lg-5">
                            <div class="input-group">
                                <span class="input-group-addon">{l s='Min' mod='ps_dataexporter'}</span>
                                <input type="number" step="0.01" name="total_paid_tax_incl_min" class="form-control" placeholder="0.00" />
                                <span class="input-group-addon">{$currency_sign|escape:'html':'UTF-8'}</span>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="input-group">
                                <span class="input-group-addon">{l s='Max' mod='ps_dataexporter'}</span>
                                <input type="number" step="0.01" name="total_paid_tax_incl_max" class="form-control" placeholder="99999.99" />
                                <span class="input-group-addon">{$currency_sign|escape:'html':'UTF-8'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Statuts de commande *}
            <div class="form-group pde-filter-group" data-filter-type="orders">
                <label class="control-label col-lg-3">
                    {l s='Statuts de commande' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <select name="current_state[]" multiple class="form-control chosen" style="width: 100%;">
                        {foreach from=$order_states item=state}
                            <option value="{$state.id_order_state|intval}">{$state.name|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                    <p class="help-block">{l s='Laisser vide pour tous les statuts' mod='ps_dataexporter'}</p>
                </div>
            </div>

            {* Moyens de paiement *}
            <div class="form-group pde-filter-group" data-filter-type="orders">
                <label class="control-label col-lg-3">
                    {l s='Moyens de paiement' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <select name="payment" class="form-control chosen" style="width: 100%;">
                        <option value="">{l s='Tous' mod='ps_dataexporter'}</option>
                        {foreach from=$payment_methods item=method}
                            <option value="{$method|escape:'html':'UTF-8'}">{$method|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
            </div>

            {* Transporteurs *}
            <div class="form-group pde-filter-group" data-filter-type="orders">
                <label class="control-label col-lg-3">
                    {l s='Transporteurs' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <select name="id_carrier[]" multiple class="form-control chosen" style="width: 100%;">
                        {foreach from=$carriers item=carrier}
                            <option value="{$carrier.id_carrier|intval}">{$carrier.name|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
            </div>

            {* Pays *}
            <div class="form-group">
                <label class="control-label col-lg-3">
                    {l s='Pays' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <select name="id_country[]" multiple class="form-control chosen" style="width: 100%;">
                        {foreach from=$countries item=country}
                            <option value="{$country.id_country|intval}">{$country.name|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
            </div>

            {* Departements FR *}
            <div class="form-group pde-filter-fr-only">
                <label class="control-label col-lg-3">
                    {l s='Departements (FR)' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <select name="dept_code[]" multiple class="form-control chosen" style="width: 100%;">
                        {foreach from=$departments item=dept}
                            <option value="{$dept.dept_code|escape:'html':'UTF-8'}">{$dept.dept_code|escape:'html':'UTF-8'} - {$dept.dept_name|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
            </div>

            {* Regions FR *}
            <div class="form-group pde-filter-fr-only">
                <label class="control-label col-lg-3">
                    {l s='Regions (FR)' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <select name="region_code[]" multiple class="form-control chosen" style="width: 100%;">
                        {foreach from=$regions item=region}
                            <option value="{$region.region_code|escape:'html':'UTF-8'}">{$region.region_name|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
            </div>

            {* Filtres clients *}
            <div class="form-group pde-filter-group" data-filter-type="customers">
                <label class="control-label col-lg-3">
                    {l s='Groupes clients' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <select name="in_group[]" multiple class="form-control chosen" style="width: 100%;">
                        {foreach from=$customer_groups item=group}
                            <option value="{$group.id_group|intval}">{$group.name|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
            </div>

            <div class="form-group pde-filter-group" data-filter-type="customers">
                <label class="control-label col-lg-3">
                    {l s='Newsletter' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <select name="newsletter" class="form-control fixed-width-lg">
                        <option value="">{l s='Tous' mod='ps_dataexporter'}</option>
                        <option value="1">{l s='Inscrits uniquement' mod='ps_dataexporter'}</option>
                        <option value="0">{l s='Non-inscrits uniquement' mod='ps_dataexporter'}</option>
                    </select>
                </div>
            </div>

            {* Options coupons *}
            <div class="form-group pde-filter-group" data-filter-type="orders">
                <label class="control-label col-lg-3">
                    {l s='Coupons' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <select name="has_coupon" class="form-control fixed-width-lg">
                        <option value="">{l s='Tous' mod='ps_dataexporter'}</option>
                        <option value="1">{l s='Avec coupon uniquement' mod='ps_dataexporter'}</option>
                        <option value="0">{l s='Sans coupon uniquement' mod='ps_dataexporter'}</option>
                    </select>
                </div>
            </div>

            {* Produits specifiques *}
            <div class="form-group pde-filter-group" data-filter-type="orders">
                <label class="control-label col-lg-3">
                    {l s='Contenant produit(s)' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <input type="text" name="has_product" class="form-control" placeholder="{l s='IDs produits separes par virgule (ex: 12,45,78)' mod='ps_dataexporter'}" />
                </div>
            </div>

            <div class="form-group pde-filter-group" data-filter-type="orders">
                <label class="control-label col-lg-3">
                    {l s='Categories' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <select name="id_category[]" multiple class="form-control chosen" style="width: 100%;">
                        {foreach from=$categories item=cat}
                            <option value="{$cat.id_category|intval}">{$cat.name|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
            </div>

            <hr/>

            {* Options avancees *}
            <h4><i class="icon-cogs"></i> {l s='Options avancees' mod='ps_dataexporter'}</h4>

            <div class="form-group">
                <label class="control-label col-lg-3">
                    {l s='Colonnes custom' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="filters[include_custom_columns]" value="1" />
                        {l s='Inclure les colonnes ajoutees par d\'autres modules' mod='ps_dataexporter'}
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-3">
                    {l s='Tables custom' mod='ps_dataexporter'}
                </label>
                <div class="col-lg-9">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="filters[include_custom_tables]" value="1" />
                        {l s='Inclure les tables liees ajoutees par d\'autres modules' mod='ps_dataexporter'}
                    </label>
                </div>
            </div>

            <hr/>

            {* Estimation *}
            <div class="form-group">
                <div class="col-lg-9 col-lg-offset-3">
                    <button type="button" id="pde-estimate-btn" class="btn btn-default">
                        <i class="icon-calculator"></i> {l s='Estimer le volume' mod='ps_dataexporter'}
                    </button>
                    <span id="pde-estimate-result" class="text-info" style="margin-left: 15px; display: none;">
                        <i class="icon-spinner icon-spin"></i> {l s='Calcul en cours...' mod='ps_dataexporter'}
                    </span>
                </div>
            </div>

            <div class="panel-footer">
                <button type="submit" class="btn btn-primary" id="pde-submit-btn" onclick="console.log('PDE: Button clicked'); return true;">
                    <i class="icon-download"></i> {l s='Demarrer l\'export' mod='ps_dataexporter'}
                </button>
            </div>
        </form>

        <script type="text/javascript">
        // Debug: Monitor form submission
        document.addEventListener('DOMContentLoaded', function() {
            var form = document.getElementById('pde-export-form');
            if (form) {
                console.log('PDE: Form found, action =', form.action);
                form.addEventListener('submit', function(e) {
                    console.log('PDE: Form submit event fired');
                    console.log('PDE: Form data:', new FormData(form));
                });
            } else {
                console.log('PDE: Form NOT found!');
            }
        });
        </script>
    </div>
</div>

<script type="text/javascript">
    var pde_ajax_url = '{$ajax_url|escape:'javascript':'UTF-8'}';
</script>
