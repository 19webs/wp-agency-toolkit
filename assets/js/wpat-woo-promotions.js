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

	});

})(jQuery);
