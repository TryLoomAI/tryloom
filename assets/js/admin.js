/**
 * TryLoom Admin JavaScript.
 *
 * @package TryLoom
 */

(function ($) {
	'use strict';

	$(document).ready(function () {

		// Initialize color picker with default color (Bug R2-9).
		$('.tryloom-admin__color-picker').wpColorPicker({
			defaultColor: '#552FBC'
		});

		// Initialize media uploader.
		$('.tryloom-admin__media-upload').on('click', function (e) {
			e.preventDefault();

			var button = $(this);
			var container = button.closest('.tryloom-admin__media-uploader');
			var preview = container.find('.tryloom-admin__media-preview');
			var input = container.find('input[type="hidden"]');

			var frame = wp.media({
				title: 'Select or Upload Media',
				button: {
					text: 'Use this media'
				},
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				preview.html('<img src="' + attachment.url + '" alt="" class="tryloom-admin__preview-img" />');
				input.val(attachment.id);
			});

			frame.open();
		});

		// Remove media.
		$('.tryloom-admin__media-remove').on('click', function (e) {
			e.preventDefault();

			var button = $(this);
			var container = button.closest('.tryloom-admin__media-uploader');
			var preview = container.find('.tryloom-admin__media-preview');
			var input = container.find('input[type="hidden"]');

			preview.html('');
			input.val('');
		});

		// Toggle shortcode display based on button placement selection.
		$('select[name="tryloom_button_placement"]').on('change', function () {
			var value = $(this).val();
			var description = $(this).next('.description');

			if ('shortcode' === value) {
				description.html(
					'Choose where to place the Try On button.<br>Use shortcode: <code>[tryloom]</code>'
				);
			} else {
				description.html('Choose where to place the Try On button.');
			}
		});

		// Initialize select2 for enhanced select fields.
		$('.wc-enhanced-select').select2();

		// Bug 4 Fix: Async subscription status check.
		// Fires in background so admin page renders instantly even if API is slow.
		if (typeof tryloom_admin_params !== 'undefined' && tryloom_admin_params.check_subscription === '1') {
			$.ajax({
				url: tryloom_admin_params.ajax_url,
				type: 'POST',
				data: {
					action: 'tryloom_check_subscription',
					nonce: tryloom_admin_params.nonce
				},
				success: function (response) {
					if (response.success && response.data.subscription_ended === 'no') {
						// Subscription restored — reload to update UI
						location.reload();
					}
				}
			});
		}

	});

})(jQuery);
