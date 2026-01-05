/**
 * PS Data Exporter - Admin JavaScript
 */

(function() {
    'use strict';

    // Wait for jQuery to be available
    function initPDE() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initPDE, 100);
            return;
        }

        var $ = jQuery;

    var PDE = {
        ajaxUrl: '',
        refreshInterval: null,
        refreshDelay: 3000,

        init: function() {
            console.log('PDE: Initializing...');
            this.ajaxUrl = typeof pde_ajax_url !== 'undefined' ? pde_ajax_url : '';
            console.log('PDE: Ajax URL set to:', this.ajaxUrl);

            this.initExportForm();
            this.initProgressView();
            this.initHistoryView();
            this.initFilterVisibility();
            console.log('PDE: Initialization complete');
        },

        /**
         * Initialize export form
         */
        initExportForm: function() {
            var self = this;

            // Estimate button
            $('#pde-estimate-btn').on('click', function() {
                self.estimateVolume();
            });

            // Export type change - show/hide relevant filters
            $('#export_type').on('change', function() {
                self.updateFilterVisibility($(this).val());
            });

            // Form submit validation
            $('#pde-export-form').on('submit', function() {
                var btn = $('#pde-submit-btn');
                btn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Démarrage...');
                console.log('PDE: Form submitted');
            });

            // Initialize chosen selects
            if ($.fn.chosen) {
                $('.chosen').chosen({
                    width: '100%',
                    placeholder_text_multiple: 'Sélectionner...',
                    no_results_text: 'Aucun résultat pour'
                });
            }
        },

        /**
         * Update filter visibility based on export type
         */
        initFilterVisibility: function() {
            var currentType = $('#export_type').val();
            if (currentType) {
                this.updateFilterVisibility(currentType);
            }
        },

        updateFilterVisibility: function(exportType) {
            // Show/hide order-specific filters
            if (exportType === 'customers') {
                $('.pde-filter-group[data-filter-type="orders"]').addClass('hidden');
                $('.pde-filter-group[data-filter-type="customers"]').removeClass('hidden');
            } else if (exportType === 'orders') {
                $('.pde-filter-group[data-filter-type="orders"]').removeClass('hidden');
                $('.pde-filter-group[data-filter-type="customers"]').addClass('hidden');
            } else {
                // Full - show all
                $('.pde-filter-group').removeClass('hidden');
            }
        },

        /**
         * Estimate export volume
         */
        estimateVolume: function() {
            var self = this;
            var $result = $('#pde-estimate-result');
            var $form = $('#pde-export-form');

            $result.removeClass('error').addClass('loading').show()
                   .html('<i class="icon-spinner icon-spin"></i> Calcul en cours...');

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&ajax=1&action=estimate',
                dataType: 'json',
                success: function(response) {
                    $result.removeClass('loading');
                    if (response.success) {
                        var text = '<i class="icon-check"></i> ';
                        text += 'Estimation : <strong>' + self.formatNumber(response.total) + '</strong> enregistrements';
                        if (response.details) {
                            text += '<br><small>';
                            if (response.details.orders) {
                                text += 'Commandes: ' + self.formatNumber(response.details.orders) + ' | ';
                            }
                            if (response.details.customers) {
                                text += 'Clients: ' + self.formatNumber(response.details.customers);
                            }
                            text += '</small>';
                        }
                        $result.html(text);
                    } else {
                        $result.addClass('error').html('<i class="icon-times"></i> ' + response.error);
                    }
                },
                error: function() {
                    $result.removeClass('loading').addClass('error')
                           .html('<i class="icon-times"></i> Erreur de communication');
                }
            });
        },

        /**
         * Initialize progress view
         */
        initProgressView: function() {
            var self = this;

            // Pause button
            $(document).on('click', '.pde-pause-btn', function() {
                var jobId = $(this).data('job-id');
                self.pauseJob(jobId);
            });

            // Resume button
            $(document).on('click', '.pde-resume-btn', function() {
                var jobId = $(this).data('job-id');
                self.resumeJob(jobId);
            });

            // Cancel button
            $(document).on('click', '.pde-cancel-btn', function() {
                if (confirm('Annuler cet export ?')) {
                    var jobId = $(this).data('job-id');
                    self.cancelJob(jobId);
                }
            });

            // Start auto-refresh if there are running jobs
            console.log('PDE: Checking for running jobs...', typeof pde_running_jobs !== 'undefined' ? pde_running_jobs : 'undefined');
            if (typeof pde_running_jobs !== 'undefined' && pde_running_jobs.length > 0) {
                console.log('PDE: Found ' + pde_running_jobs.length + ' job(s), starting batch execution');
                this.startProgressRefresh();
                this.runNextBatch();
            } else {
                console.log('PDE: No running jobs found');
            }
        },

        /**
         * Start progress auto-refresh
         */
        startProgressRefresh: function() {
            var self = this;

            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
            }

            this.refreshInterval = setInterval(function() {
                self.refreshProgress();
            }, this.refreshDelay);
        },

        /**
         * Refresh progress for all running jobs
         */
        refreshProgress: function() {
            var self = this;
            var $cards = $('.pde-job-card');

            $cards.each(function() {
                var jobId = $(this).data('job-id');
                self.getJobProgress(jobId, $(this));
            });
        },

        /**
         * Get job progress
         */
        getJobProgress: function(jobId, $card) {
            var self = this;

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    ajax: 1,
                    action: 'progress',
                    id_job: jobId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        self.updateProgressCard($card, response);

                        // If completed or failed, stop refresh and show message
                        if (response.status === 'completed') {
                            clearInterval(self.refreshInterval);
                            self.showNotification('success', 'Export terminé avec succès !');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else if (response.status === 'failed') {
                            clearInterval(self.refreshInterval);
                            self.showNotification('error', 'L\'export a échoué: ' + response.error);
                        }
                    }
                }
            });
        },

        /**
         * Update progress card UI
         */
        updateProgressCard: function($card, data) {
            var percent = data.progress_percent || 0;

            $card.find('.pde-progress-bar')
                 .css('width', percent + '%')
                 .attr('aria-valuenow', percent);

            $card.find('.pde-progress-text').text(percent + '%');
            $card.find('.pde-processed').text(data.processed_records || 0);
            $card.find('.pde-total').text(data.total_records || 0);

            if (data.current_entity) {
                $card.find('.pde-current-entity').text(data.current_entity);
            }
        },

        /**
         * Run next batch for pending/running jobs
         */
        runNextBatch: function() {
            var self = this;
            var runningJobs = typeof pde_running_jobs !== 'undefined' ? pde_running_jobs : [];

            runningJobs.forEach(function(job) {
                // Start pending jobs and continue running jobs
                if (job.status === 'running' || job.status === 'pending') {
                    self.executeBatch(job.id_export_job);
                }
            });
        },

        /**
         * Execute a batch
         */
        executeBatch: function(jobId) {
            var self = this;
            console.log('PDE: Executing batch for job #' + jobId);
            console.log('PDE: AJAX URL:', this.ajaxUrl);

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    ajax: 1,
                    action: 'runBatch',
                    id_job: jobId
                },
                dataType: 'json',
                success: function(response) {
                    console.log('PDE: Batch response:', response);
                    if (response.success) {
                        if (response.status === 'running') {
                            // Continue with next batch
                            setTimeout(function() {
                                self.executeBatch(jobId);
                            }, 500);
                        } else {
                            console.log('PDE: Job status is now:', response.status);
                        }
                    } else {
                        console.error('PDE: Batch error:', response.error);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('PDE: Batch AJAX error:', error);
                    console.error('PDE: XHR response:', xhr.responseText);
                    // Retry after delay
                    setTimeout(function() {
                        self.executeBatch(jobId);
                    }, 5000);
                }
            });
        },

        /**
         * Pause job
         */
        pauseJob: function(jobId) {
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    ajax: 1,
                    action: 'pauseJob',
                    id_job: jobId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        },

        /**
         * Resume job
         */
        resumeJob: function(jobId) {
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    ajax: 1,
                    action: 'resumeJob',
                    id_job: jobId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        },

        /**
         * Cancel job
         */
        cancelJob: function(jobId) {
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    ajax: 1,
                    action: 'cancelJob',
                    id_job: jobId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        },

        /**
         * Initialize history view
         */
        initHistoryView: function() {
            // Delete confirmation is handled inline

            // Refresh download tokens on hover (optional enhancement)
            $(document).on('mouseenter', '.dropdown-toggle', function() {
                var $dropdown = $(this).next('.dropdown-menu');
                // Could refresh token validity here if needed
            });
        },

        /**
         * Show notification
         */
        showNotification: function(type, message) {
            var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            var icon = type === 'success' ? 'icon-check' : 'icon-times';

            var $alert = $('<div class="alert ' + alertClass + ' pde-fade-in">' +
                          '<i class="' + icon + '"></i> ' + message +
                          '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                          '</div>');

            $('.pde-progress .panel-body').prepend($alert);

            setTimeout(function() {
                $alert.fadeOut();
            }, 5000);
        },

        /**
         * Format number with spaces
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        PDE.init();
    });

    } // End of initPDE function

    // Start initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPDE);
    } else {
        initPDE();
    }

})();
