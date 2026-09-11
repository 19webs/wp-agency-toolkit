/**
 * AUTOCOMPLETADO DE CP, PROVINCIA Y POBLACIÓN - WP AGENCY TOOLKIT
 */
jQuery(document).ready(function($) {
	'use strict';

	if (typeof wpatAutofillOptions === 'undefined' || !wpatAutofillOptions.spain_provinces) {
		return;
	}

	function isCountrySpain(type) {
		var $countryField = $('#' + type + '_country');
		if (!$countryField.length) {
			// Si no existe campo de país, asumir España por defecto si WC solo tiene 1 país habilitado
			return true;
		}

		var val = $countryField.val();
		if (!val && $countryField.is('input[type="hidden"]')) {
			val = $countryField.attr('value');
		}

		if (!val) {
			// Buscar en contenedor o texto estático
			var text = $('#' + type + '_country_field').text();
			if (text && text.indexOf('España') !== -1) {
				return true;
			}
		}

		return (!val || val === 'ES');
	}

	function handleAddressAutofill(type) {
		if (!isCountrySpain(type)) {
			return;
		}

		var $postcodeField = $('#' + type + '_postcode');
		if (!$postcodeField.length) return;

		var cp = $postcodeField.val() ? $.trim($postcodeField.val()) : '';
		if (cp.length < 2) return;

		// 1. Detectar Provincia por los primeros 2 dígitos
		var prefix = cp.substring(0, 2);
		var provinceCode = wpatAutofillOptions.spain_provinces[prefix];

		if (provinceCode) {
			var $stateField = $('#' + type + '_state');
			if ($stateField.length && $stateField.val() !== provinceCode) {
				$stateField.val(provinceCode).trigger('change');
				$(document.body).trigger('country_to_state_changed');

				if ($.fn.select2 && $stateField.hasClass('select2-hidden-accessible')) {
					$stateField.trigger('change.select2');
				}
			}
		}

		// 2. Autocompletar / Sugerir Población si coincide con CP completo (5 dígitos)
		if (wpatAutofillOptions.autofill_city === '1' && cp.length === 5 && wpatAutofillOptions.spain_cities) {
			var cityMatch = wpatAutofillOptions.spain_cities[cp];
			if (cityMatch) {
				var $cityField = $('#' + type + '_city');
				if ($cityField.length && (!$cityField.val() || $cityField.data('wpat-autofilled') === '1')) {
					$cityField.val(cityMatch).trigger('change');
					$cityField.data('wpat-autofilled', '1');
				}
			}
		}
	}

	// Escuchar eventos en tiempo real
	$(document).on('input blur change', '#billing_postcode', function() {
		handleAddressAutofill('billing');
	});

	$(document).on('input blur change', '#shipping_postcode', function() {
		handleAddressAutofill('shipping');
	});

	// Ejecución inicial por si el CP viene pre-rellenado (ej: usuarios registrados o borrado de formulario)
	setTimeout(function() {
		handleAddressAutofill('billing');
		handleAddressAutofill('shipping');
	}, 400);
});
