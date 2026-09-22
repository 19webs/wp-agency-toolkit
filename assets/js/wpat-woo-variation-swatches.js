/**
 * Swatches de Variación de Producto (Frontend) - WP Agency Toolkit
 */
jQuery(document).ready(function($) {

	// Manejo de clic en botón de Swatch
	$(document).on('click', '.wpat-vswatch-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		if ($btn.hasClass('disabled') || $btn.prop('disabled')) {
			return;
		}

		var $wrap = $btn.closest('.wpat-variation-swatches-wrap');
		var selectId = $wrap.data('select-id');
		var $select = $('#' + selectId);

		if (!$select.length) {
			$select = $wrap.closest('.variations').find('select[name="attribute_' + selectId + '"]');
		}
		if (!$select.length) {
			$select = $wrap.prev('select');
		}
		if (!$select.length) {
			return;
		}

		var val = $btn.attr('data-value') || '';

		if ($btn.hasClass('selected')) {
			// Deseleccionar
			$btn.removeClass('selected').attr('aria-checked', 'false');
			$select.val('').trigger('change');
		} else {
			// Seleccionar
			$wrap.find('.wpat-vswatch-btn').removeClass('selected').attr('aria-checked', 'false');
			$btn.addClass('selected').attr('aria-checked', 'true');
			$select.val(val).trigger('change');
		}
	});

	// Sincronizar swatches cuando cambie el select nativo o se limpien variaciones
	$(document).on('woocommerce_variation_has_changed reset_data check_variations', '.variations_form', function() {
		var $form = $(this);

		$form.find('.wpat-variation-swatches-wrap').each(function() {
			var $wrap = $(this);
			var selectId = $wrap.data('select-id');
			var $select = $('#' + selectId);

			if (!$select.length) {
				$select = $wrap.closest('.variations').find('select[name="attribute_' + selectId + '"]');
			}
			if (!$select.length) {
				$select = $wrap.prev('select');
			}
			if (!$select.length) {
				return;
			}

			var currentVal = $select.val();

			$wrap.find('.wpat-vswatch-btn').each(function() {
				var $btn = $(this);
				var btnVal = $btn.attr('data-value');

				if (currentVal && btnVal === currentVal) {
					$btn.addClass('selected').attr('aria-checked', 'true');
				} else {
					$btn.removeClass('selected').attr('aria-checked', 'false');
				}

				// Comprobar si la opción está habilitada en el select nativo
				if ($select.find('option[value="' + CSS.escape(btnVal) + '"]').length === 0 && btnVal !== '') {
					$btn.addClass('disabled').attr('aria-disabled', 'true');
				} else {
					$btn.removeClass('disabled').removeAttr('aria-disabled');
				}
			});
		});
	});

	// Inicializar estado de swatches en carga de página
	setTimeout(function() {
		$('.variations_form').trigger('check_variations');
	}, 100);
});
