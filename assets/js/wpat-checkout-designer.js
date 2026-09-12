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
		var origText = $btn.text();
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
				$btn.prop('disabled', false).text(origText);
				$(document.body).trigger('update_checkout');
			},
			error: function() {
				$btn.prop('disabled', false).text(origText);
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

	// --- 6. CART DESIGNER: QUANTITY BUTTONS (+ / -) & UPDATE CART ---
	function markCartUpdateAvailable() {
		var $updateBtn = $('button[name="update_cart"], input[name="update_cart"], .wpat-cart-update-btn');
		$updateBtn.prop('disabled', false).removeClass('disabled').removeAttr('disabled');
	}

	$(document).on('click', '.wpat-qty-plus', function(e) {
		e.preventDefault();
		var $box = $(this).closest('.wpat-cart-table-qty-box, .wpat-cart-modern-qty-box, .quantity, td.product-quantity');
		var $input = $box.find('input.qty');
		if (!$input.length) $input = $(this).siblings('.quantity').find('input.qty');
		if (!$input.length) $input = $(this).siblings('input.qty');

		var val = parseInt($input.val(), 10) || 1;
		var max = parseInt($input.attr('max'), 10);
		if (isNaN(max) || val < max) {
			$input.val(val + 1).trigger('change');
			markCartUpdateAvailable();
		}
	});

	$(document).on('click', '.wpat-qty-minus', function(e) {
		e.preventDefault();
		var $box = $(this).closest('.wpat-cart-table-qty-box, .wpat-cart-modern-qty-box, .quantity, td.product-quantity');
		var $input = $box.find('input.qty');
		if (!$input.length) $input = $(this).siblings('.quantity').find('input.qty');
		if (!$input.length) $input = $(this).siblings('input.qty');

		var val = parseInt($input.val(), 10) || 1;
		var min = parseInt($input.attr('min'), 10);
		if (isNaN(min)) min = 1;
		if (val > min) {
			$input.val(val - 1).trigger('change');
			markCartUpdateAvailable();
		}
	});

	$(document).on('input change', 'input.qty', function() {
		markCartUpdateAvailable();
	});

	// Enable update cart button before submit
	$(document).on('click', '.wpat-cart-update-btn, button[name="update_cart"]', function() {
		$(this).prop('disabled', false).removeAttr('disabled');
	});

	// --- 6.1 EMPTY COUPON VALIDATION ---
	$(document).on('click', '.wpat-cart-coupon-btn, button[name="apply_coupon"]', function(e) {
		var $input = $('#coupon_code, input[name="coupon_code"]');
		var code = $input.val() ? $input.val().trim() : '';
		if (!code) {
			e.preventDefault();
			e.stopPropagation();
			$('.wpat-cart-coupon-notice').remove();
			$input.css('border-color', '#ef4444').focus();

			var $notice = $('<div class="wpat-cart-coupon-notice" style="color: #ef4444; font-size: 13px; font-weight: 600; margin-top: 6px;">Por favor, escribe un código de cupón antes de aplicar.</div>');
			$input.parent().append($notice);

			setTimeout(function() {
				$notice.fadeOut(300, function() { $(this).remove(); });
				$input.css('border-color', '');
			}, 3500);
			return false;
		}
	});

	// --- 7. CART DESIGNER: SLIDE-OUT DRAWER CART ---
	function openCartDrawer() {
		$('.wpat-cart-drawer-panel').addClass('open');
		$('.wpat-drawer-overlay').addClass('active');
		$('body').addClass('wpat-drawer-open');
	}

	function closeCartDrawer() {
		$('.wpat-cart-drawer-panel').removeClass('open');
		$('.wpat-drawer-overlay').removeClass('active');
		$('body').removeClass('wpat-drawer-open');
	}

	$(document).on('click', '.wpat-drawer-close-btn, .wpat-drawer-overlay', function(e) {
		e.preventDefault();
		closeCartDrawer();
	});

	$(document).on('click', '.wpat-open-drawer-cart', function(e) {
		e.preventDefault();
		openCartDrawer();
	});

	// Auto-abrir mini-carrito deslizable al añadir producto vía AJAX
	$(document.body).on('added_to_cart', function(event, fragments, cart_hash, $button) {
		if (typeof wpatCheckoutOptions !== 'undefined' && wpatCheckoutOptions.cart_layout === 'wpat-cart-drawer' && wpatCheckoutOptions.drawer_auto_open === '1') {
			openCartDrawer();
		}
	});
});
