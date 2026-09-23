/**
 * Script Frontend: Venta Directa & Pagos Rápidos (Quick Pay) - WP Agency Toolkit
 */
(function($) {
	'use strict';

	var currentProduct = null;
	var currentBreakdown = null;

	function formatMoney(amount) {
		var sym = (window.wpatQuickPay && window.wpatQuickPay.currency_symbol) ? window.wpatQuickPay.currency_symbol : '€';
		var pos = (window.wpatQuickPay && window.wpatQuickPay.currency_pos) ? window.wpatQuickPay.currency_pos : 'right';
		var str = (parseFloat(amount) || 0).toFixed(2).replace('.', ',');

		return ('left' === pos) ? sym + ' ' + str : str + ' ' + sym;
	}

	// 1. Abrir Modal al hacer clic en cualquier botón disparador
	$(document).on('click', '.wpat-qp-trigger-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var productId = $btn.data('product-id');
		var productPayload = $btn.attr('data-product-payload') || '';

		if (!productId && !productPayload) return;

		$btn.prop('disabled', true);

		// Reiniciar formulario
		$('#wpat_qp_checkout_form')[0].reset();
		$('#wpat_qp_f_applied_coupon').val('');
		$('#wpat_qp_coupon_input').val('');
		$('#wpat_qp_coupon_status_badge').hide().text('');
		$('#wpat_qp_coupon_input_row').hide();
		$('#wpat_qp_open_coupon_btn').show();
		$('#wpat_qp_b_discount_row').hide();
		$('#wpat_qp_success_screen').hide();
		$('#wpat_qp_checkout_form').show();
		$('#wpat_qp_f_product_payload').val(productPayload);

		$.ajax({
			url: wpatQuickPay.ajax_url,
			type: 'POST',
			data: {
				action: 'wpat_qp_get_checkout_data',
				security: wpatQuickPay.nonce,
				product_id: productId,
				product_payload: productPayload
			},
			success: function(response) {
				$btn.prop('disabled', false);
				if (response.success && response.data) {
					var d = response.data;
					currentProduct = d.product;
					currentBreakdown = d;

					$('#wpat_qp_f_product_id').val(currentProduct.id);
					$('#wpat_qp_m_product_title').text(currentProduct.name);

					var typeLabel = (currentProduct.type === 'digital') ? '📥 Descarga Digital' : ((currentProduct.type === 'physical') ? '📦 Envío Físico' : '🎓 Servicio / Acceso');
					$('#wpat_qp_m_product_badge').text(typeLabel);

					$('#wpat_qp_m_total_display').text(formatMoney(d.total));
					$('#wpat_qp_b_subtotal').text(formatMoney(d.price));
					$('#wpat_qp_b_tax_rate').text(d.tax_rate);
					$('#wpat_qp_b_tax_amount').text(formatMoney(d.tax_amount));
					$('#wpat_qp_b_final_total').text(formatMoney(d.total));

					// Mostrar u ocultar campos de envío físico
					if (currentProduct.type === 'physical') {
						$('#wpat_qp_shipping_fields_wrapper').slideDown(150);
						$('#wpat_qp_f_address, #wpat_qp_f_city, #wpat_qp_f_postcode').prop('required', true);
						if (parseFloat(d.shipping) > 0) {
							$('#wpat_qp_b_shipping_row').show();
							$('#wpat_qp_b_shipping').text(formatMoney(d.shipping));
						} else {
							$('#wpat_qp_b_shipping_row').hide();
						}
					} else {
						$('#wpat_qp_shipping_fields_wrapper').hide();
						$('#wpat_qp_f_address, #wpat_qp_f_city, #wpat_qp_f_postcode').prop('required', false);
						$('#wpat_qp_b_shipping_row').hide();
					}

					$('#wpat_qp_checkout_modal').css('display', 'flex').hide().fadeIn(150);
				} else {
					alert(response.data ? response.data.message : 'Error al cargar producto');
				}
			},
			error: function() {
				$btn.prop('disabled', false);
				alert('Error de conexión con el servidor.');
			}
		});
	});

	// 2. Cerrar Modal
	$(document).on('click', '.wpat-qp-close-modal', function(e) {
		e.preventDefault();
		$('#wpat_qp_checkout_modal').fadeOut(150);
	});

	$(document).on('click', '#wpat_qp_checkout_modal', function(e) {
		if ($(e.target).is('#wpat_qp_checkout_modal')) {
			$('#wpat_qp_checkout_modal').fadeOut(150);
		}
	});

	// 3. Selección visual de Pasarela
	$(document).on('change', 'input[name="wpat_gw_radio"]', function() {
		var gw = $(this).val();
		$('.wpat-qp-gateway-option').removeClass('active');
		$(this).closest('.wpat-qp-gateway-option').addClass('active');
		$('#wpat_qp_f_gateway').val(gw);
	});

	// 4. Desplegar campo de Cupón
	$(document).on('click', '#wpat_qp_open_coupon_btn', function(e) {
		e.preventDefault();
		$(this).hide();
		$('#wpat_qp_coupon_input_row').css('display', 'flex').hide().slideDown(120);
		$('#wpat_qp_coupon_input').focus();
	});

	// 5. Aplicar Cupón AJAX
	$(document).on('click', '#wpat_qp_apply_coupon_btn', function(e) {
		e.preventDefault();
		var code = $('#wpat_qp_coupon_input').val().trim();
		if (!code || !currentProduct) return;

		var $btn = $(this);
		$btn.prop('disabled', true).text('...');

		$.ajax({
			url: wpatQuickPay.ajax_url,
			type: 'POST',
			data: {
				action: 'wpat_qp_apply_coupon',
				security: wpatQuickPay.nonce,
				product_id: currentProduct.id,
				product_payload: $('#wpat_qp_f_product_payload').val(),
				coupon_code: code
			},
			success: function(response) {
				$btn.prop('disabled', false).text('Aplicar');
				if (response.success && response.data) {
					var d = response.data;
					$('#wpat_qp_f_applied_coupon').val(d.coupon_code);
					$('#wpat_qp_coupon_input_row').slideUp(100);
					$('#wpat_qp_coupon_status_badge').show().text('Cupón "' + d.coupon_code + '" aplicado (-' + formatMoney(d.discount_amount) + ')');

					$('#wpat_qp_b_discount_row').css('display', 'flex');
					$('#wpat_qp_b_discount').text('-' + formatMoney(d.discount_amount));
					$('#wpat_qp_b_final_total').text(formatMoney(d.new_total));
					$('#wpat_qp_m_total_display').text(formatMoney(d.new_total));
					$('#wpat_qp_b_tax_amount').text(formatMoney(d.tax_amount));
				} else {
					alert(response.data ? response.data.message : 'Cupón no válido');
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Aplicar');
				alert('Error al validar cupón.');
			}
		});
	});

	// 6. Procesar el Envío del Checkout
	$(document).on('submit', '#wpat_qp_checkout_form', function(e) {
		e.preventDefault();
		var gateway = $('#wpat_qp_f_gateway').val();
		var $submitBtn = $('#wpat_qp_submit_btn');
		var origText = $submitBtn.html();

		$submitBtn.prop('disabled', true).html('⏳ ' + (wpatQuickPay.i18n ? wpatQuickPay.i18n.loading : 'Procesando...'));

		var formData = {
			security: wpatQuickPay.nonce,
			product_id: $('#wpat_qp_f_product_id').val(),
			product_payload: $('#wpat_qp_f_product_payload').val(),
			gateway: gateway,
			customer_name: $('#wpat_qp_f_name').val(),
			customer_email: $('#wpat_qp_f_email').val(),
			customer_phone: $('#wpat_qp_f_phone').val(),
			customer_dni: $('#wpat_qp_f_dni').val(),
			coupon_code: $('#wpat_qp_f_applied_coupon').val(),
			shipping_address: $('#wpat_qp_f_address').val(),
			shipping_city: $('#wpat_qp_f_city').val(),
			shipping_postcode: $('#wpat_qp_f_postcode').val()
		};

		// 6.1. Pasarela: STRIPE
		if (gateway === 'stripe') {
			formData.action = 'wpat_qp_create_stripe_session';
			$.ajax({
				url: wpatQuickPay.ajax_url,
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success && response.data && response.data.checkout_url) {
						$submitBtn.html('✓ Redirigiendo a Stripe...');
						window.location.href = response.data.checkout_url;
					} else {
						$submitBtn.prop('disabled', false).html(origText);
						alert(response.data ? response.data.message : 'Error al conectar con Stripe');
					}
				},
				error: function() {
					$submitBtn.prop('disabled', false).html(origText);
					alert('Error de conexión con la pasarela Stripe.');
				}
			});
			return;
		}

		// 6.2. Pasarela: REDSYS TPV
		if (gateway === 'redsys') {
			formData.action = 'wpat_qp_process_redsys_order';
			$.ajax({
				url: wpatQuickPay.ajax_url,
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success && response.data) {
						var d = response.data;
						var $form = $('<form action="' + d.action_url + '" method="POST" style="display:none;">' +
							'<input type="hidden" name="Ds_SignatureVersion" value="' + d.signature_version + '">' +
							'<input type="hidden" name="Ds_MerchantParameters" value="' + d.params_base64 + '">' +
							'<input type="hidden" name="Ds_Signature" value="' + d.signature + '">' +
							'</form>');
						$('body').append($form);
						$form.submit();
					} else {
						$submitBtn.prop('disabled', false).html(origText);
						alert(response.data ? response.data.message : 'Error al procesar Redsys');
					}
				},
				error: function() {
					$submitBtn.prop('disabled', false).html(origText);
					alert('Error al conectar con Redsys.');
				}
			});
			return;
		}

		// 6.3. Pasarela: PAYPAL
		if (gateway === 'paypal') {
			formData.action = 'wpat_qp_process_paypal_order';
			$.ajax({
				url: wpatQuickPay.ajax_url,
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success && response.data && response.data.redirect_url) {
						window.location.href = response.data.redirect_url;
					} else {
						$submitBtn.prop('disabled', false).html(origText);
						alert(response.data ? response.data.message : 'Error al procesar PayPal');
					}
				},
				error: function() {
					$submitBtn.prop('disabled', false).html(origText);
					alert('Error de conexión con PayPal.');
				}
			});
			return;
		}

		// 6.4. Pasarela: BIZUM / TRANSFERENCIA MANUAL
		formData.action = 'wpat_qp_process_manual_order';
		$.ajax({
			url: wpatQuickPay.ajax_url,
			type: 'POST',
			data: formData,
			success: function(response) {
				$submitBtn.prop('disabled', false).html(origText);
				if (response.success && response.data) {
					$('#wpat_qp_checkout_form').slideUp(150);
					$('#wpat_qp_manual_instructions_box').show().html(response.data.instructions);
					$('#wpat_qp_success_screen').slideDown(150);
				} else {
					alert(response.data ? response.data.message : 'Error al registrar pedido');
				}
			},
			error: function() {
				$submitBtn.prop('disabled', false).html(origText);
				alert('Error al registrar pedido manual.');
			}
		});
	});

})(jQuery);
