/**
 * WhatsApp API Admin Scripts
 */

jQuery(document).ready(function($) {
    // Generate new JWT Secret
    $('#wpwa_generate_jwt_secret').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(wpwa.i18n.confirm_generate_jwt)) {
            return;
        }
        
        $(this).prop('disabled', true).text('Generating...');
        
        $.ajax({
            url: wpwa.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwa_generate_jwt_secret',
                nonce: wpwa.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#wpwa_jwt_secret').val(response.data.jwt_secret);
                    alert('New JWT Secret generated successfully!');
                } else {
                    alert('Failed to generate JWT Secret: ' + response.data.message);
                }
            },
            error: function() {
                alert('Request failed. Please try again.');
            },
            complete: function() {
                $('#wpwa_generate_jwt_secret').prop('disabled', false).text('Generate New Secret');
            }
        });
    });
    
    // API URL and Key Validation
    $('#wpwa-validate-api').on('click', function(e) {
        e.preventDefault();
        
        const apiUrl = $('#wpwa_api_url').val();
        const apiKey = $('#wpwa_api_key').val();
        
        if (!apiUrl || !apiKey) {
            alert('Please enter both API URL and API Key');
            return;
        }
        
        $(this).prop('disabled', true).text('Validating...');
        
        $.ajax({
            url: wpwa.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwa_validate_api_credentials',
                nonce: wpwa.nonce,
                api_url: apiUrl,
                api_key: apiKey
            },
            success: function(response) {
                if (response.success) {
                    alert('API credentials validated successfully!');
                } else {
                    alert('API validation failed: ' + response.data.message);
                }
            },
            error: function() {
                alert('API validation request failed. Please check your connection.');
            },
            complete: function() {
                $('#wpwa-validate-api').prop('disabled', false).text('Validate API Credentials');
            }
        });
    });
    
    // Generate new API key
    $('#wpwa-generate-key').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to generate a new API key? This will invalidate the existing key.')) {
            return;
        }
        
        $(this).prop('disabled', true);
        
        $.ajax({
            url: wpwa.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwa_generate_api_key',
                nonce: wpwa.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#wpwa_api_key').val(response.data.api_key);
                    alert('New API key generated successfully!');
                } else {
                    alert('Failed to generate API key: ' + response.data.message);
                }
            },
            error: function() {
                alert('Request failed. Please try again.');
            },
            complete: function() {
                $('#wpwa-generate-key').prop('disabled', false);
            }
        });
    });
    
    // View logs
    $('#wpwa-view-logs').on('click', function(e) {
        e.preventDefault();
        
        $(this).prop('disabled', true).text('Loading...');
        
        $.ajax({
            url: wpwa.ajax_url,
            type: 'POST',
            data: {
                action: 'wpwa_admin_get_logs',
                nonce: wpwa.nonce
            },
            success: function(response) {
                if (response.success) {
                    const logs = response.data.logs || [];
                    let logsHtml = '<div class="wpwa-logs-container">';
                    
                    if (logs.length === 0) {
                        logsHtml += '<p>No logs available.</p>';
                    } else {
                        logsHtml += '<table class="widefat">';
                        logsHtml += '<thead><tr>';
                        logsHtml += '<th>Time</th>';
                        logsHtml += '<th>Level</th>';
                        logsHtml += '<th>Message</th>';
                        logsHtml += '</tr></thead>';
                        logsHtml += '<tbody>';
                        
                        logs.forEach(function(log) {
                            const levelClass = 'log-level-' + (log.level || 'info').toLowerCase();
                            logsHtml += '<tr class="' + levelClass + '">';
                            logsHtml += '<td>' + (log.time || '') + '</td>';
                            logsHtml += '<td>' + (log.level || 'info') + '</td>';
                            logsHtml += '<td>' + (log.message || '') + '</td>';
                            logsHtml += '</tr>';
                        });
                        
                        logsHtml += '</tbody></table>';
                    }
                    
                    logsHtml += '<p><button type="button" class="button" id="wpwa-clear-logs">Clear Logs</button></p>';
                    logsHtml += '</div>';
                    
                    // Create modal
                    $('<div id="wpwa-logs-dialog" title="WhatsApp API Logs">' + logsHtml + '</div>').dialog({
                        modal: true,
                        width: 800,
                        height: 500,
                        close: function() {
                            $(this).dialog('destroy').remove();
                        }
                    });
                    
                    // Clear logs button
                    $('#wpwa-clear-logs').on('click', function() {
                        if (confirm('Are you sure you want to clear all logs?')) {
                            $.ajax({
                                url: wpwa.ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'wpwa_admin_clear_logs',
                                    nonce: wpwa.nonce
                                },
                                success: function(response) {
                                    if (response.success) {
                                        alert('Logs cleared successfully!');
                                        $('#wpwa-logs-dialog').dialog('close');
                                    } else {
                                        alert('Failed to clear logs: ' + response.data.message);
                                    }
                                },
                                error: function() {
                                    alert('Request failed. Please try again.');
                                }
                            });
                        }
                    });
                } else {
                    alert('Failed to load logs: ' + response.data.message);
                }
            },
            error: function() {
                alert('Failed to load logs. Please try again.');
            },
            complete: function() {
                $('#wpwa-view-logs').prop('disabled', false).text('View Logs');
            }
        });
    });
});