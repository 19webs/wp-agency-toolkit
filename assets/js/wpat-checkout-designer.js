/**
 * DISEÑADOR DE CHECKOUT HIGH-CONVERSION - WP AGENCY TOOLKIT
 */
jQuery(document).ready(function($) {
	'use strict';

	// --- 1. MOBILE ORDER SUMMARY TOGGLE ---
	$(document).on('click', '.wpat-mobile-order-summary-toggle', function() {
		var $review = $('#order_review');
		var $arrow = $(this).find('.wpat-summary-arrow');
		
		$review.slideToggle(250, function() {
			if ($review.is(':visible')) {
				$arrow.text('▲');
			} else {
				$arrow.text('▼');
			}
		});
	});

	// --- 2. MULTI-STEP NAVIGATION (NEXT / PREV BUTTONS & STEPS BAR) ---
	function goToStep(step) {
		step = parseInt(step, 10);
		if (isNaN(step) || step < 1 || step > 3) return;

		// Actualizar barra de pasos
		$('.wpat-step-item').removeClass('active');
		$('.wpat-step-item[data-step="' + step + '"]').addClass('active');

		// Mostrar panel correspondiente
		$('.wpat-step-panel').hide().removeClass('active');
		$('.wpat-step-panel[data-step="' + step + '"]').fadeIn(200).addClass('active');

		// Scroll suave al inicio del checkout
		if ($('.wpat-checkout-container').length) {
			$('html, body').animate({
				scrollTop: $('.wpat-checkout-container').offset().top - 40
			}, 300);
		}
	}

	$(document).on('click', '.wpat-next-btn', function(e) {
		e.preventDefault();
		var nextStep = $(this).data('next');
		goToStep(nextStep);
	});

	$(document).on('click', '.wpat-prev-btn', function(e) {
		e.preventDefault();
		var prevStep = $(this).data('prev');
		goToStep(prevStep);
	});

	$(document).on('click', '.wpat-step-item', function() {
		var step = $(this).data('step');
		goToStep(step);
	});

	// --- 3. CUSTOM CLEAN COUPON SUBMIT ---
	$(document).on('click', '#wpat_coupon_apply_btn', function(e) {
		e.preventDefault();
		var code = $('#wpat_coupon_code_field').val().trim();
		if (!code) return;

		var $btn = $(this);
		$btn.prop('disabled', true).text('Aplicando...');

		var data = {
			action: 'woocommerce_apply_coupon',
			security: typeof wc_checkout_params !== 'undefined' ? wc_checkout_params.apply_coupon_nonce : '',
			coupon_code: code
		};

		$.ajax({
			type: 'POST',
			url: typeof wc_checkout_params !== 'undefined' ? wc_checkout_params.ajax_url : '/?wc-ajax=apply_coupon',
			data: data,
			success: function(response) {
				$btn.prop('disabled', false).text('Aplicar cupón');
				$(document.body).trigger('update_checkout');
			},
			error: function() {
				$btn.prop('disabled', false).text('Aplicar cupón');
				$(document.body).trigger('update_checkout');
			}
		});
	});

	// --- 4. SMART EMAIL AUTOCORRECT SUGGESTION ---
	if (typeof wpatCheckoutOptions !== 'undefined' && wpatCheckoutOptions.email_autocorrect === '1') {
		var commonDomains = {
			'gmai.com': 'gmail.com',
			'gamil.com': 'gmail.com',
			'gmial.com': 'gmail.com',
			'hotmai.com': 'hotmail.com',
			'hotmial.com': 'hotmail.com',
			'yaho.com': 'yahoo.com',
			'outloo.com': 'outlook.com'
		};

		$(document).on('blur', '#billing_email', function() {
			var email = $(this).val().trim();
			$('.wpat-email-autocorrect-notice').remove();

			if (email && email.indexOf('@') !== -1) {
				var parts = email.split('@');
				var domain = parts[1].toLowerCase();

				if (commonDomains[domain]) {
					var suggestedEmail = parts[0] + '@' + commonDomains[domain];
					var htmlNotice = '<div class="wpat-email-autocorrect-notice">¿Quisiste decir <a class="wpat-apply-email-suggestion" data-suggested="' + suggestedEmail + '">' + suggestedEmail + '</a>?</div>';
					$(this).after(htmlNotice);
				}
			}
		});

		$(document).on('click', '.wpat-apply-email-suggestion', function(e) {
			e.preventDefault();
			var suggested = $(this).data('suggested');
			$('#billing_email').val(suggested).trigger('change');
			$('.wpat-email-autocorrect-notice').remove();
		});
	}

	// --- 5. SPAIN POSTAL CODE AUTO-FILL (PROVINCE & CITY DETECTOR) ---
	if (typeof wpatCheckoutOptions !== 'undefined' && wpatCheckoutOptions.autofill_spain_cp === '1' && wpatCheckoutOptions.spain_provinces) {
		function handleSpainPostcode(type) {
			var countryField = $('#' + type + '_country');
			var country = countryField.val();
			if (country !== 'ES') return;

			var cpField = $('#' + type + '_postcode');
			var cp = cpField.val() ? cpField.val().trim() : '';
			if (cp.length < 2) return;

			var prefix = cp.substring(0, 2);
			var provinceCode = wpatCheckoutOptions.spain_provinces[prefix];

			if (provinceCode) {
				var $state = $('#' + type + '_state');
				if ($state.length && $state.val() !== provinceCode) {
					$state.val(provinceCode).trigger('change');
					if ($.fn.select2 && $state.hasClass('select2-hidden-accessible')) {
						$state.trigger('change.select2');
					}
				}
			}
		}

		$(document).on('input blur change', '#billing_postcode', function() {
			handleSpainPostcode('billing');
		});

		$(document).on('input blur change', '#shipping_postcode', function() {
			handleSpainPostcode('shipping');
		});
	}
});
