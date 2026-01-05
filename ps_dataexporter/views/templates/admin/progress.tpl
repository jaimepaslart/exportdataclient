{**
 * Progress view for running exports
 *}
<div class="panel pde-progress">
    <div class="panel-heading">
        <i class="icon-spinner"></i> {l s='Exports en cours' mod='ps_dataexporter'}
    </div>
    <div class="panel-body">
        {if empty($running_jobs)}
            <div class="alert alert-info">
                <i class="icon-info-circle"></i> {l s='Aucun export en cours actuellement.' mod='ps_dataexporter'}
                <a href="{$link->getAdminLink('AdminPsDataExporter')}&tab=new" class="alert-link">
                    {l s='Demarrer un nouvel export' mod='ps_dataexporter'}
                </a>
            </div>
        {else}
            {foreach from=$running_jobs item=job}
                <div class="pde-job-card" data-job-id="{$job.id_export_job|intval}">
                    <div class="row">
                        <div class="col-lg-8">
                            <h4>
                                <span class="label label-{if $job.status == 'running'}primary{elseif $job.status == 'paused'}warning{else}default{/if}">
                                    {$job.status|escape:'html':'UTF-8'}
                                </span>
                                Export #{$job.id_export_job|intval}
                                <small class="text-muted">
                                    - {$job.export_type|escape:'html':'UTF-8'} / {$job.export_level|escape:'html':'UTF-8'}
                                </small>
                            </h4>

                            <div class="progress" style="margin: 15px 0;">
                                <div class="progress-bar progress-bar-striped active pde-progress-bar"
                                     role="progressbar"
                                     style="width: {$job.progress_percent|floatval}%"
                                     aria-valuenow="{$job.progress_percent|floatval}"
                                     aria-valuemin="0"
                                     aria-valuemax="100">
                                    <span class="pde-progress-text">{$job.progress_percent|floatval}%</span>
                                </div>
                            </div>

                            <p class="text-muted">
                                <i class="icon-tasks"></i>
                                <span class="pde-current-entity">{$job.current_entity|escape:'html':'UTF-8'}</span>
                                -
                                <span class="pde-processed">{$job.processed_records|intval}</span> /
                                <span class="pde-total">{$job.total_records|intval}</span> {l s='enregistrements' mod='ps_dataexporter'}
                            </p>

                            <p class="text-muted">
                                <i class="icon-clock-o"></i>
                                {l s='Demarre le' mod='ps_dataexporter'} {$job.started_at|escape:'html':'UTF-8'}
                                {if $job.firstname}
                                    {l s='par' mod='ps_dataexporter'} {$job.firstname|escape:'html':'UTF-8'} {$job.lastname|escape:'html':'UTF-8'}
                                {/if}
                            </p>
                        </div>

                        <div class="col-lg-4 text-right">
                            {if $job.status == 'running'}
                                <button type="button" class="btn btn-warning pde-pause-btn" data-job-id="{$job.id_export_job|intval}">
                                    <i class="icon-pause"></i> {l s='Pause' mod='ps_dataexporter'}
                                </button>
                            {elseif $job.status == 'paused'}
                                <button type="button" class="btn btn-success pde-resume-btn" data-job-id="{$job.id_export_job|intval}">
                                    <i class="icon-play"></i> {l s='Reprendre' mod='ps_dataexporter'}
                                </button>
                            {/if}

                            <button type="button" class="btn btn-danger pde-cancel-btn" data-job-id="{$job.id_export_job|intval}">
                                <i class="icon-times"></i> {l s='Annuler' mod='ps_dataexporter'}
                            </button>
                        </div>
                    </div>
                </div>
                <hr/>
            {/foreach}
        {/if}
    </div>
</div>

{* Auto-refresh JavaScript *}
<script type="text/javascript">
    var pde_ajax_url = '{$ajax_url|escape:'javascript':'UTF-8'}';
    var pde_running_jobs = {$running_jobs|json_encode};
</script>
