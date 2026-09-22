/**
 * JAVASCRIPT FRONTEND DE PROMOCIONES DINÁMICAS - WP AGENCY TOOLKIT
 */

(function($) {
	'use strict';

	/**
	 * Inicializa los contadores regresivos de tiempo en las tarjetas de promoción
	 */
	function initCountdownTimers() {
		$('.wpat-promo-countdown-card').each(function() {
			var $card = $(this);
			if ($card.data('wpat-timer-active')) {
				return;
			}
			$card.data('wpat-timer-active', true);

			var endTimestamp = parseInt($card.attr('data-end-timestamp'), 10);
			if (!endTimestamp || isNaN(endTimestamp)) {
				var d = new Date();
				d.setHours(23, 59, 59, 999);
				endTimestamp = Math.floor(d.getTime() / 1000);
			}

			var $days  = $card.find('.wpat-cd-days');
			var $hours = $card.find('.wpat-cd-hours');
			var $mins  = $card.find('.wpat-cd-mins');
			var $secs  = $card.find('.wpat-cd-secs');

			function pad(n) {
				return n < 10 ? '0' + n : n;
			}

			function updateTimer() {
				var now = Math.floor(Date.now() / 1000);
				var diff = endTimestamp - now;

				if (diff <= 0) {
					$days.text('00');
					$hours.text('00');
					$mins.text('00');
					$secs.text('00');
					return;
				}

				var days  = Math.floor(diff / (3600 * 24));
				var hours = Math.floor((diff % (3600 * 24)) / 3600);
				var mins  = Math.floor((diff % 3600) / 60);
				var secs  = Math.floor(diff % 60);

				$days.text(pad(days));
				$hours.text(pad(hours));
				$mins.text(pad(mins));
				$secs.text(pad(secs));
			}

			updateTimer();
			setInterval(updateTimer, 1000);
		});
	}

	$(document).ready(function() {

		// Recalculo dinámico al cambiar el método de pago en Checkout
		$(document.body).on('change', 'input[name="payment_method"]', function() {
			$(document.body).trigger('update_checkout');
		});

		initCountdownTimers();

		$(window).on('load', initCountdownTimers);

		// Eventos dinámicos de WooCommerce, AJAX y Maquetadores
		$(document.body).on('updated_cart_totals updated_checkout post-load yith_infs_added_elem wc_fragments_refreshed found_variation reset_data', function() {
			initCountdownTimers();
		});

		// Compatibilidad con Elementor Frontend
		$(window).on('elementor/frontend/init', function() {
			if (window.elementorFrontend && window.elementorFrontend.hooks) {
				window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function() {
					initCountdownTimers();
				});
			}
		});

		// MutationObserver para contenido inyectado dinámicamente o por tabs/acordeones
		if (window.MutationObserver) {
			var observer = new MutationObserver(function(mutations) {
				var hasNew = false;
				for (var i = 0; i < mutations.length; i++) {
					if (mutations[i].addedNodes && mutations[i].addedNodes.length > 0) {
						hasNew = true;
						break;
					}
				}
				if (hasNew) {
					initCountdownTimers();
				}
			});
			observer.observe(document.body, { childList: true, subtree: true });
		}

	});

})(jQuery);
