/**
 * Admin JavaScript for WP REST Endpoint Manager
 */
(function($) {
	'use strict';

	// API Tester functionality
	if ($('.wp-rem-api-tester').length) {
		// Initialize API Tester
		initAPITester();
	}

	// Log Viewer functionality
	if ($('.wp-rem-logs').length) {
		initLogViewer();
	}

	// Settings functionality
	if ($('.wp-rem-settings').length) {
		initSettings();
	}

	/**
	 * Initialize API Tester
	 */
	function initAPITester() {
		// Endpoint selection
		$('#endpoint-select').on('change', function() {
			const url = $(this).val();
			$('#custom-url').val(url);

			// Update available methods
			const methods = $(this).find(':selected').data('methods');
			if (methods && methods.length > 0) {
				$('#http-method').val(methods[0]);
			}
		});

		// Send API request
		$('#send-request').on('click', function() {
			const url = $('#custom-url').val() || $('#endpoint-select').val();
			const method = $('#http-method').val();
			let headers = {};
			let body = $('#request-body').val();

			try {
				headers = JSON.parse($('#request-headers').val());
			} catch (e) {
				alert('Invalid JSON in headers');
				return;
			}

			if (!url) {
				alert('Please enter or select a URL');
				return;
			}

			// Show loading
			$(this).prop('disabled', true).text('Sending...');
			$('#response-container').html('<div class="wp-rem-loading"></div> Sending request...');

			// Send AJAX request
			$.ajax({
				url: wpRemData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wp_rem_test_api',
					nonce: wpRemData.nonce,
					url: url,
					method: method,
					headers: JSON.stringify(headers),
					body: body
				},
				success: function(response) {
					if (response.success) {
						displayResponse(response.data);
					} else {
						displayError(response.data);
					}
				},
				error: function(xhr) {
					displayError({ message: 'Request failed: ' + xhr.statusText });
				},
				complete: function() {
					$('#send-request').prop('disabled', false).text('Send Request');
				}
			});
		});

		// Generate cURL
		$('#generate-curl').on('click', function() {
			const url = $('#custom-url').val() || $('#endpoint-select').val();
			const method = $('#http-method').val();
			let headers = {};
			const body = $('#request-body').val();

			try {
				headers = JSON.parse($('#request-headers').val());
			} catch (e) {
				alert('Invalid JSON in headers');
				return;
			}

			let curl = `curl -X ${method} '${url}'`;

			// Add headers
			for (const [key, value] of Object.entries(headers)) {
				curl += ` \\\n  -H '${key}: ${value}'`;
			}

			// Add body
			if (body && method !== 'GET') {
				curl += ` \\\n  -d '${body.replace(/'/g, "'\\''")}'`;
			}

			$('#curl-command').text(curl);
			$('#curl-modal').fadeIn();
		});

		// Copy cURL to clipboard
		$('#copy-curl').on('click', function() {
			const curlCommand = $('#curl-command').text();
			navigator.clipboard.writeText(curlCommand).then(() => {
				$(this).text('Copied!');
				setTimeout(() => {
					$(this).text('Copy to Clipboard');
				}, 2000);
			});
		});

		// Close modals
		$('.close-modal').on('click', function() {
			$(this).closest('[id$="-modal"]').fadeOut();
		});
	}

	/**
	 * Display API response
	 */
	function displayResponse(data) {
		$('#response-container').hide();
		$('#response-status').text(data.status + ' ' + getStatusText(data.status));
		$('#response-time').text(data.time + 's');
		$('#response-size').text(formatBytes(data.size));
		$('#response-headers').text(JSON.stringify(data.headers, null, 2));
		
		// Format body if JSON
		let formattedBody = data.body;
		try {
			if (typeof formattedBody === 'string') {
				formattedBody = JSON.parse(formattedBody);
			}
			formattedBody = JSON.stringify(formattedBody, null, 2);
		} catch (e) {
			// Not JSON, use as is
		}
		$('#response-body').text(formattedBody);
		$('#response-data').show();
	}

	/**
	 * Display error
	 */
	function displayError(data) {
		$('#response-container').html(
			'<div class="error-message"><strong>Error:</strong> ' + data.message + '</div>'
		).show();
		$('#response-data').hide();
	}

	/**
	 * Get HTTP status text
	 */
	function getStatusText(code) {
		const statusTexts = {
			200: 'OK',
			201: 'Created',
			204: 'No Content',
			400: 'Bad Request',
			401: 'Unauthorized',
			403: 'Forbidden',
			404: 'Not Found',
			429: 'Too Many Requests',
			500: 'Internal Server Error'
		};
		return statusTexts[code] || '';
	}

	/**
	 * Format bytes
	 */
	function formatBytes(bytes) {
		if (bytes === 0) return '0 Bytes';
		const k = 1024;
		const sizes = ['Bytes', 'KB', 'MB'];
		const i = Math.floor(Math.log(bytes) / Math.log(k));
		return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
	}

	/**
	 * Initialize Log Viewer
	 */
	function initLogViewer() {
		// View log details
		$('.view-log-details').on('click', function() {
			const logId = $(this).data('log-id');
			// Fetch and display log details (implementation needed)
			$('#log-detail-modal').fadeIn();
		});

		// Delete log
		$('.delete-log').on('click', function() {
			if (!confirm('Are you sure you want to delete this log?')) {
				return;
			}

			const logId = $(this).data('log-id');
			const $row = $(this).closest('tr');

			$.ajax({
				url: wpRemData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wp_rem_delete_log',
					nonce: wpRemData.nonce,
					log_id: logId
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(function() {
							$(this).remove();
						});
					}
				}
			});
		});

		// Export logs
		$('#export-logs').on('click', function() {
			const logType = $('#log-type-filter').val();
			const endpointId = new URLSearchParams(window.location.search).get('endpoint') || 0;

			$.ajax({
				url: wpRemData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wp_rem_export_logs',
					nonce: wpRemData.nonce,
					log_type: logType,
					endpoint_id: endpointId
				},
				success: function(response) {
					if (response.success) {
						// Download CSV
						const blob = new Blob([response.data.csv], { type: 'text/csv' });
						const url = window.URL.createObjectURL(blob);
						const a = document.createElement('a');
						a.href = url;
						a.download = 'logs-' + Date.now() + '.csv';
						a.click();
					}
				}
			});
		});
	}

	/**
	 * Initialize Settings
	 */
	function initSettings() {
		// Generate API key
		$('#generate-api-key').on('click', function() {
			const apiKey = generateRandomKey(32);
			const $textarea = $('textarea[name="wp_rem_api_keys"]');
			const currentKeys = $textarea.val();
			$textarea.val(currentKeys + (currentKeys ? '\n' : '') + apiKey);
		});

		// Export settings
		$('#export-rem-settings').on('click', function() {
			$.ajax({
				url: wpRemData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wp_rem_export_settings',
					nonce: wpRemData.nonce
				},
				success: function(response) {
					if (response.success) {
						const blob = new Blob([JSON.stringify(response.data.settings, null, 2)], { type: 'application/json' });
						const url = window.URL.createObjectURL(blob);
						const a = document.createElement('a');
						a.href = url;
						a.download = 'rem-settings-' + Date.now() + '.json';
						a.click();
					}
				}
			});
		});

		// Import settings
		$('#import-rem-settings').on('click', function() {
			const fileInput = document.getElementById('import-rem-settings-file');
			if (!fileInput.files.length) {
				alert('Please select a file to import.');
				return;
			}

			const file = fileInput.files[0];
			const reader = new FileReader();

			reader.onload = function(e) {
				const settings = e.target.result;
				if (!confirm('Are you sure you want to import these settings? This will overwrite your current configuration.')) {
					return;
				}

				$.ajax({
					url: wpRemData.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wp_rem_import_settings',
						nonce: wpRemData.nonce,
						settings: settings
					},
					success: function(response) {
						if (response.success) {
							alert(response.data.message);
							location.reload();
						} else {
							alert('Error: ' + response.data.message);
						}
					}
				});
			};

			reader.readAsText(file);
		});
	}

	/**
	 * Generate random API key
	 */
	function generateRandomKey(length) {
		const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		let result = '';
		for (let i = 0; i < length; i++) {
			result += chars.charAt(Math.floor(Math.random() * chars.length));
		}
		return result;
	}

})(jQuery);
