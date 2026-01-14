/**
 * WP Admin Pointers handler
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		if (typeof wpRemPointers === 'undefined' || !wpRemPointers.pointers) {
			return;
		}

		$.each(wpRemPointers.pointers, function(i, pointer) {
			const $target = $(pointer.target);

			if (!$target.length) {
				return;
			}

			const options = $.extend(pointer.options, {
				close: function() {
					// Mark as dismissed via AJAX.
					$.post(ajaxurl, {
						pointer: pointer.id,
						action: 'dismiss-wp-pointer'
					});
				}
			});

			$target.pointer(options).pointer('open');
		});
	});

})(jQuery);
