{*
 * Tabs navigation for admin
 *}
<div class="pde-tabs-container">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item{if $current_tab == 'new'} active{/if}">
            <a class="nav-link" href="{$link->getAdminLink('AdminPsDataExporter')}&tab=new">
                <i class="icon-plus"></i> {l s='Nouvel export' mod='ps_dataexporter'}
            </a>
        </li>
        <li class="nav-item{if $current_tab == 'progress'} active{/if}">
            <a class="nav-link" href="{$link->getAdminLink('AdminPsDataExporter')}&tab=progress">
                <i class="icon-spinner"></i> {l s='En cours' mod='ps_dataexporter'}
                {if isset($running_count) && $running_count > 0}
                    <span class="badge badge-warning">{$running_count|intval}</span>
                {/if}
            </a>
        </li>
        <li class="nav-item{if $current_tab == 'history'} active{/if}">
            <a class="nav-link" href="{$link->getAdminLink('AdminPsDataExporter')}&tab=history">
                <i class="icon-history"></i> {l s='Historique' mod='ps_dataexporter'}
            </a>
        </li>
        <li class="nav-item{if $current_tab == 'settings'} active{/if}">
            <a class="nav-link" href="{$link->getAdminLink('AdminPsDataExporter')}&tab=settings">
                <i class="icon-cog"></i> {l s='Configuration' mod='ps_dataexporter'}
            </a>
        </li>
    </ul>
</div>
