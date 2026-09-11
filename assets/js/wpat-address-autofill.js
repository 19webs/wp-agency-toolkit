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
			if (type === 'shipping') {
				return isCountrySpain('billing');
			}
			return true;
		}

		var val = $countryField.val() || $countryField.attr('value') || '';
		val = $.trim(val).toUpperCase();

		if (!val || val === 'ES' || val === 'SPAIN' || val === 'ESPAÑA') {
			return true;
		}

		var text = $('#' + type + '_country_field').text() || '';
		if (text.indexOf('España') !== -1 || text.indexOf('Spain') !== -1) {
			return true;
		}

		return false;
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
		var provinceData = wpatAutofillOptions.spain_provinces[prefix];

		if (provinceData) {
			var pCode = provinceData.code;
			var pName = provinceData.name;
			var $stateField = $('#' + type + '_state');

			if ($stateField.length) {
				if ($stateField.is('select')) {
					var $opt = $stateField.find('option[value="' + pCode + '"]');
					if (!$opt.length && pName) {
						$opt = $stateField.find('option').filter(function() {
							return $(this).text().toLowerCase().indexOf(pName.toLowerCase()) !== -1;
						});
					}

					if ($opt.length) {
						var targetVal = $opt.val();
						if ($stateField.val() !== targetVal) {
							$stateField.val(targetVal).trigger('change');

							if ($.fn.select2) {
								$stateField.trigger('change.select2');
								if ($stateField.data('select2')) {
									$stateField.trigger('select2:select');
								}
							}
							$(document.body).trigger('country_to_state_changed');
						}
					}
				} else {
					if ($stateField.val() !== pName && $stateField.val() !== pCode) {
						$stateField.val(pName).trigger('change');
					}
				}
			}
		}

		// 2. Autocompletar / Sugerir Población si coincide con CP completo (5 dígitos)
		if (wpatAutofillOptions.autofill_city === '1') {
			var $cityField = $('#' + type + '_city');
			if ($cityField.length) {
				var cityMatch = (cp.length === 5 && wpatAutofillOptions.spain_cities) ? wpatAutofillOptions.spain_cities[cp] : '';
				if (!cityMatch && provinceData && provinceData.name) {
					if (!$cityField.val()) {
						cityMatch = provinceData.name;
					}
				}

				if (cityMatch && (!$cityField.val() || $cityField.data('wpat-autofilled') === '1')) {
					$cityField.val(cityMatch).trigger('change');
					$cityField.data('wpat-autofilled', '1');
				}
			}
		}
	}

	// Escuchar eventos en tiempo real en billing y shipping
	$(document).on('input keyup blur change', '#billing_postcode', function() {
		handleAddressAutofill('billing');
	});

	$(document).on('input keyup blur change', '#shipping_postcode', function() {
		handleAddressAutofill('shipping');
	});

	// Escuchar cuando el usuario marca o desmarca "Enviar a una dirección diferente"
	$(document).on('change', '#ship-to-different-address-checkbox', function() {
		if ($(this).is(':checked')) {
			setTimeout(function() {
				handleAddressAutofill('shipping');
			}, 200);
		}
	});

	// Escuchar cambio de país
	$(document).on('change', '#billing_country, #shipping_country', function() {
		var id = $(this).attr('id');
		var type = id.replace('_country', '');
		handleAddressAutofill(type);
	});

	// Ejecución inicial por si vienen valores pre-rellenados
	setTimeout(function() {
		handleAddressAutofill('billing');
		handleAddressAutofill('shipping');
	}, 300);

	setTimeout(function() {
		handleAddressAutofill('billing');
		handleAddressAutofill('shipping');
	}, 1000);
});
