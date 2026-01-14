/**
 * Autofill and UI Helpers for WP REST Endpoint Manager
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		// Only run on REST Endpoint editor
		if (!$('body.post-type-rest_endpoint').length) {
			return;
		}

		const $namespace = $('input[name="rem_namespace"]');
		const $route = $('input[name="rem_route"]');

		// 1. Suggest Current Theme/Plugin Namespace
		if (!$namespace.val()) {
			// Extract site domain as a namespace suggestion
			const host = window.location.hostname.split('.')[0];
			if (host) {
				const suggestion = host + '/v1';
				$namespace.attr('placeholder', suggestion);
				
				// Add a "Use Suggestion" link
				$namespace.after('<div class="rem-suggestion" style="margin-top:5px;font-size:12px;color:#2271b1;cursor:pointer;">' + 
					'💡 ' + wpRemData.i18n.suggest_namespace + ': <strong>' + suggestion + '</strong>' + 
				'</div>');

				$('.rem-suggestion').on('click', function() {
					$namespace.val(suggestion);
					$(this).fadeOut();
				});
			}
		}

		// 2. Common Route Patterns Autocomplete
		const routes = [
			'/items',
			'/items/(?P<id>\\d+)',
			'/list',
			'/submit',
			'/process',
			'/webhook/callback'
		];

		$route.on('focus', function() {
			if (!$(this).val()) {
				// Show a simple list or just help text
				if (!$('#route-helpers').length) {
					let html = '<div id="route-helpers" style="background:#fff;border:1px solid #ccd0d4;padding:10px;margin-top:5px;border-radius:4px;z-index:100;position:absolute;width:90%;box-shadow:0 2px 4px rgba(0,0,0,0.1);">';
					html += '<strong>' + wpRemData.i18n.common_patterns + ':</strong><ul style="margin:5px 0 0;padding-left:20px;list-style:disc;">';
					routes.forEach(function(r) {
						html += '<li style="margin:2px 0;cursor:pointer;color:#2271b1;" class="route-hint" data-route="' + r + '">' + r + '</li>';
					});
					html += '</ul></div>';
					$route.after(html);

					$('.route-hint').on('click', function() {
						$route.val($(this).data('route'));
						$('#route-helpers').fadeOut();
					});
				} else {
					$('#route-helpers').fadeIn();
				}
			}
		});

		$(document).on('click', function(e) {
			if (!$(e.target).closest('#route-helpers, input[name="rem_route"]').length) {
				$('#route-helpers').fadeOut();
			}
		});

		// 3. Dynamic Tooltips for Methods
		$('.methods-check-group label').each(function() {
			const method = $(this).text().trim();
			let help = '';
			switch(method) {
				case 'GET': help = wpRemData.i18n.get_help; break;
				case 'POST': help = wpRemData.i18n.post_help; break;
				case 'PUT': help = wpRemData.i18n.put_help; break;
				case 'DELETE': help = wpRemData.i18n.delete_help; break;
			}
			if (help) {
				$(this).append(' <span class="dashicons dashicons-editor-help" title="' + help + '" style="font-size:16px;color:#c3c4c7;cursor:help;"></span>');
			}
		});
	});

})(jQuery);
