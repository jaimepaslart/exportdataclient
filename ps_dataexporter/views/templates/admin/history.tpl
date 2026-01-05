{**
 * Export history view
 *}
<div class="panel pde-history">
    <div class="panel-heading">
        <i class="icon-history"></i> {l s='Historique des exports' mod='ps_dataexporter'}
    </div>
    <div class="panel-body">
        {if empty($completed_jobs)}
            <div class="alert alert-info">
                <i class="icon-info-circle"></i> {l s='Aucun export termine.' mod='ps_dataexporter'}
            </div>
        {else}
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{l s='Type' mod='ps_dataexporter'}</th>
                        <th>{l s='Niveau' mod='ps_dataexporter'}</th>
                        <th>{l s='Statut' mod='ps_dataexporter'}</th>
                        <th>{l s='Enregistrements' mod='ps_dataexporter'}</th>
                        <th>{l s='Demarre' mod='ps_dataexporter'}</th>
                        <th>{l s='Termine' mod='ps_dataexporter'}</th>
                        <th>{l s='Fichiers' mod='ps_dataexporter'}</th>
                        <th>{l s='Actions' mod='ps_dataexporter'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$completed_jobs item=job}
                        <tr class="pde-job-row {if $job.status == 'failed'}danger{elseif $job.status == 'completed'}success{/if}">
                            <td>{$job.id_export_job|intval}</td>
                            <td>
                                <span class="label label-info">{$job.export_type|escape:'html':'UTF-8'}</span>
                            </td>
                            <td>{$job.export_level|escape:'html':'UTF-8'}</td>
                            <td>
                                {if $job.status == 'completed'}
                                    <span class="label label-success">
                                        <i class="icon-check"></i> {l s='Termine' mod='ps_dataexporter'}
                                    </span>
                                {elseif $job.status == 'failed'}
                                    <span class="label label-danger" title="{$job.error_message|escape:'html':'UTF-8'}">
                                        <i class="icon-times"></i> {l s='Echec' mod='ps_dataexporter'}
                                    </span>
                                {else}
                                    <span class="label label-default">{$job.status|escape:'html':'UTF-8'}</span>
                                {/if}
                            </td>
                            <td>{$job.processed_records|intval}</td>
                            <td>{$job.started_at|escape:'html':'UTF-8'}</td>
                            <td>{$job.completed_at|escape:'html':'UTF-8'}</td>
                            <td>
                                {if !empty($job.files)}
                                    <div class="dropdown">
                                        <button class="btn btn-default btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            <i class="icon-file-text-o"></i> {$job.files|count} {l s='fichier(s)' mod='ps_dataexporter'}
                                            <span class="caret"></span>
                                        </button>
                                        <ul class="dropdown-menu">
                                            {foreach from=$job.files item=file}
                                                <li>
                                                    <a href="{$link->getAdminLink('AdminPsDataExporter')}&action=download&token_file={$file.download_token|escape:'url'}" target="_blank">
                                                        <i class="icon-download"></i>
                                                        {$file.filename|escape:'html':'UTF-8'}
                                                        <small class="text-muted">({$file.filesize|intval|number_format:0:' ':' '} octets)</small>
                                                    </a>
                                                </li>
                                            {/foreach}
                                        </ul>
                                    </div>
                                {else}
                                    <span class="text-muted">-</span>
                                {/if}
                            </td>
                            <td>
                                {if $job.status == 'completed' && !empty($job.files)}
                                    {* Find ZIP file if exists *}
                                    {assign var='zip_file' value=null}
                                    {foreach from=$job.files item=file}
                                        {if $file.entity_name == 'archive'}
                                            {assign var='zip_file' value=$file}
                                        {/if}
                                    {/foreach}

                                    {if $zip_file}
                                        <a href="{$link->getAdminLink('AdminPsDataExporter')}&action=download&token_file={$zip_file.download_token|escape:'url'}"
                                           class="btn btn-success btn-sm" target="_blank">
                                            <i class="icon-download"></i> {l s='ZIP' mod='ps_dataexporter'}
                                        </a>
                                    {/if}
                                {/if}

                                <a href="{$link->getAdminLink('AdminPsDataExporter')}&action=viewLogs&id_job={$job.id_export_job|intval}"
                                   class="btn btn-default btn-sm" title="{l s='Voir les logs' mod='ps_dataexporter'}">
                                    <i class="icon-list-alt"></i>
                                </a>

                                <a href="{$link->getAdminLink('AdminPsDataExporter')}&action=deleteJob&id_job={$job.id_export_job|intval}"
                                   class="btn btn-danger btn-sm pde-delete-job"
                                   title="{l s='Supprimer' mod='ps_dataexporter'}"
                                   onclick="return confirm('{l s='Supprimer cet export et tous ses fichiers ?' mod='ps_dataexporter' js=1}');">
                                    <i class="icon-trash"></i>
                                </a>
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>

            {* Pagination if needed *}
            {if $total_pages > 1}
                <nav class="text-center">
                    <ul class="pagination">
                        {for $p=1 to $total_pages}
                            <li class="{if $p == $current_page}active{/if}">
                                <a href="{$link->getAdminLink('AdminPsDataExporter')}&tab=history&page={$p|intval}">{$p|intval}</a>
                            </li>
                        {/for}
                    </ul>
                </nav>
            {/if}
        {/if}
    </div>

    <div class="panel-footer">
        <form method="post" action="{$link->getAdminLink('AdminPsDataExporter')}" class="form-inline">
            <input type="hidden" name="cleanupOldJobs" value="1" />
            <button type="submit" class="btn btn-default" onclick="return confirm('{l s='Supprimer les exports de plus de 7 jours ?' mod='ps_dataexporter' js=1}');">
                <i class="icon-eraser"></i> {l s='Nettoyer les anciens exports' mod='ps_dataexporter'}
            </button>
        </form>
    </div>
</div>
