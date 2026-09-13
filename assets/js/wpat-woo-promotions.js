/**
 * JAVASCRIPT FRONTEND DE PROMOCIONES DINÁMICAS - WP AGENCY TOOLKIT
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		/**
		 * Recalcula y actualiza reactivamente la barra de progreso en carrito/checkout
		 */
		function refreshPromotionsProgressBar() {
			var $container = $('#wpat-tiered-progress-container');
			if (!$container.length) {
				return;
			}

			// Si WooCommerce refresca fragmentos mediante AJAX, este trigger mantendrá la sincronización sin F5
			$(document.body).trigger('wc_update_cart');
		}

		// Escuchar eventos de cambio en carrito y checkout de WooCommerce
		$(document.body).on('updated_cart_totals updated_checkout wc_fragments_refreshed', function() {
			refreshPromotionsProgressBar();
		});

		// Recalculo al cambiar el método de pago en Checkout
		$(document.body).on('change', 'input[name="payment_method"]', function() {
			$(document.body).trigger('update_checkout');
		});

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
					return;
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
						$card.fadeOut(400);
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

		initCountdownTimers();

		$(document.body).on('updated_cart_totals updated_checkout post-load yith_infs_added_elem', function() {
			initCountdownTimers();
		});

	});

})(jQuery);
