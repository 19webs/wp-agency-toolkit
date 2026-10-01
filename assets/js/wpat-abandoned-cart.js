/**
 * MÓDULO: RECUPERADOR DE CARRITOS ABANDONADOS - WP AGENCY TOOLKIT
 * Captura asíncrona de email y datos de checkout para usuarios invitados
 */
jQuery(document).ready(function($) {
	'use strict';

	if (typeof wpatAbandonedCartParams === 'undefined') {
		return;
	}

	var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
	var lastCapturedEmail = '';
	var captureTimer = null;

	function captureGuestCartData() {
		var email = $('#billing_email').val() ? $('#billing_email').val().trim() : '';
		if (!email || !emailRegex.test(email)) {
			return;
		}

		var firstName = $('#billing_first_name').val() ? $('#billing_first_name').val().trim() : '';
		var lastName  = $('#billing_last_name').val() ? $('#billing_last_name').val().trim() : '';
		var phone     = $('#billing_phone').val() ? $('#billing_phone').val().trim() : '';

		// Evitar peticiones redundantes si no ha cambiado el email ni los datos básicos
		var currentPayload = email + '|' + firstName + '|' + lastName + '|' + phone;
		if (lastCapturedEmail === currentPayload) {
			return;
		}
		lastCapturedEmail = currentPayload;

		$.ajax({
			type: 'POST',
			url: wpatAbandonedCartParams.ajax_url,
			data: {
				action: 'wpat_capture_guest_cart',
				security: wpatAbandonedCartParams.nonce,
				email: email,
				first_name: firstName,
				last_name: lastName,
				phone: phone
			},
			success: function(response) {
				// Capturado en segundo plano silenciosamente
			}
		});
	}

	// Escuchar cambios en campos de checkout
	$(document).on('blur change', '#billing_email, #billing_first_name, #billing_last_name, #billing_phone', function() {
		clearTimeout(captureTimer);
		captureTimer = setTimeout(captureGuestCartData, 400);
	});

	// Captura inicial si el campo ya viene pre-rellenado (por ejemplo, autocompletado del navegador)
	if ($('#billing_email').length && $('#billing_email').val()) {
		setTimeout(captureGuestCartData, 1200);
	}
});
