{*
 * Progress view for running exports
 *}
<div class="panel pde-progress">
    <div class="panel-heading">
        <i class="icon-spinner"></i> {l s='Exports en cours' mod='ps_dataexporter'}
    </div>
    <div class="panel-body">
        {if empty($jobs)}
            <div class="alert alert-info">
                <i class="icon-info-circle"></i> {l s='Aucun export en cours actuellement.' mod='ps_dataexporter'}
                <a href="{$link->getAdminLink('AdminPsDataExporter')}&tab=new" class="alert-link">
                    {l s='Demarrer un nouvel export' mod='ps_dataexporter'}
                </a>
            </div>
        {else}
            {foreach from=$jobs item=job}
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
                                     style="width: {$job.progress|floatval}%"
                                     aria-valuenow="{$job.progress|floatval}"
                                     aria-valuemin="0"
                                     aria-valuemax="100">
                                    <span class="pde-progress-text">{$job.progress|floatval}%</span>
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

{* Auto-refresh JavaScript - Inline version that works without external files *}
<script type="text/javascript">
(function() {
    'use strict';

    var pde_ajax_url = '{$ajax_url|escape:'javascript':'UTF-8'}';
    var pde_running_jobs = [];
    {foreach from=$jobs item=job}
    pde_running_jobs.push({literal}{{/literal}
        'id_export_job': {$job.id_export_job|intval},
        'status': '{$job.status|escape:'javascript':'UTF-8'}'
    {literal}}{/literal});
    {/foreach}

    console.log('PDE: Inline script loaded');
    console.log('PDE: Jobs:', pde_running_jobs);
    console.log('PDE: Ajax URL:', pde_ajax_url);

    // Wait for jQuery to be available
    function waitForjQuery(callback) {
        if (typeof jQuery !== 'undefined') {
            callback(jQuery);
        } else {
            console.log('PDE: Waiting for jQuery...');
            setTimeout(function() { waitForjQuery(callback); }, 100);
        }
    }

    // Update progress bar UI
    function updateProgressUI($card, data) {
        var percent = parseFloat(data.progress) || 0;
        $card.find('.pde-progress-bar')
            .css('width', percent + '%')
            .attr('aria-valuenow', percent);
        $card.find('.pde-progress-text').text(percent.toFixed(1) + '%');
        $card.find('.pde-processed').text(data.processed || 0);
        $card.find('.pde-total').text(data.total || 0);
        if (data.current_entity) {
            $card.find('.pde-current-entity').text(data.current_entity);
        }
        // Update status label
        var $label = $card.find('.label');
        $label.text(data.status);
        if (data.status === 'running') {
            $label.removeClass('label-default label-warning').addClass('label-primary');
        }
    }

    // Execute batch processing
    function executeBatch($, jobId, $card) {
        console.log('PDE: Executing batch for job #' + jobId);

        $.ajax({
            url: pde_ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax: 1,
                action: 'runBatch',
                id_job: jobId
            },
            success: function(response) {
                console.log('PDE: Batch response:', response);

                if (response.success) {
                    updateProgressUI($card, response);

                    if (response.status === 'running') {
                        // Continue with next batch after small delay
                        setTimeout(function() {
                            executeBatch($, jobId, $card);
                        }, 500);
                    } else if (response.status === 'completed') {
                        console.log('PDE: Export completed!');
                        alert('Export terminé avec succès !');
                        location.reload();
                    } else if (response.status === 'failed') {
                        console.error('PDE: Export failed:', response.error);
                        alert('Erreur: ' + response.error);
                        location.reload();
                    }
                } else {
                    console.error('PDE: Batch error:', response.error);
                    // Retry after delay
                    setTimeout(function() {
                        executeBatch($, jobId, $card);
                    }, 3000);
                }
            },
            error: function(xhr, status, error) {
                console.error('PDE: AJAX error:', error);
                console.error('PDE: Response:', xhr.responseText);
                // Retry after delay
                setTimeout(function() {
                    executeBatch($, jobId, $card);
                }, 5000);
            }
        });
    }

    // Main function
    function startExportProcessing($) {
        console.log('PDE: Starting export processing...');

        if (!pde_running_jobs || pde_running_jobs.length === 0) {
            console.log('PDE: No jobs to process');
            return;
        }

        pde_running_jobs.forEach(function(job) {
            if (job.status === 'pending' || job.status === 'running') {
                var $card = $('.pde-job-card[data-job-id="' + job.id_export_job + '"]');
                if ($card.length) {
                    console.log('PDE: Starting batch execution for job #' + job.id_export_job);
                    executeBatch($, job.id_export_job, $card);
                }
            }
        });
    }

    // Initialize when DOM and jQuery are ready
    waitForjQuery(function($) {
        $(document).ready(function() {
            console.log('PDE: Document ready, starting processing...');
            startExportProcessing($);
        });
    });

})();
</script>
