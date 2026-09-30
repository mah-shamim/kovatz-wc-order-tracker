(function ($) {
	'use strict';

	function showLoading($result, $spinner) {
		if ($spinner) {
			$spinner.removeClass('d-none');
		}
		$result.html(
			'<div class="d-flex align-items-center text-muted py-3">' +
			'<span class="spinner-border spinner-border-sm me-2"></span>' +
			WCOT_Vars.i18n.loading +
			'</div>'
		);
	}

	function showError($result, message) {
		$result.html(
			'<div class="alert alert-danger mb-0">' + (message || WCOT_Vars.i18n.error) + '</div>'
		);
	}

	function trackOrder(orderId, identifier, $result, $spinner) {
		showLoading($result, $spinner);

		$.post(WCOT_Vars.ajax_url, {
			action: 'wcot_track_order',
			nonce: WCOT_Vars.nonce,
			order_id: orderId,
			identifier: identifier
		})
			.done(function (response) {
				if (response.success) {
					$result.html(response.data.html);
					$result[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
				} else {
					showError($result, response.data && response.data.message);
				}
			})
			.fail(function () {
				showError($result);
			})
			.always(function () {
				if ($spinner) {
					$spinner.addClass('d-none');
				}
			});
	}

	$(document).on('submit', '#wcot-guest-form', function (e) {
		e.preventDefault();
		var $form = $(this);
		var $result = $('#wcot-result');
		var $spinner = $form.find('.wcot-spinner');

		trackOrder(
			$form.find('[name="order_id"]').val(),
			$form.find('[name="identifier"]').val(),
			$result,
			$spinner
		);
	});

	$(document).on('click', '.wcot-track-btn', function () {
		var $btn = $(this);
		var $result = $('#wcot-result');

		trackOrder($btn.data('order-id'), '', $result, null);
	});
})(jQuery);
