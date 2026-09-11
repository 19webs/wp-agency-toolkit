/**
 * AUTOCOMPLETADO RECÍPROCO DE CP, PROVINCIA Y POBLACIÓN - WP AGENCY TOOLKIT
 */
jQuery(document).ready(function($) {
	'use strict';

	if (typeof wpatAutofillOptions === 'undefined' || !wpatAutofillOptions.spain_provinces) {
		return;
	}

	var isProgrammaticChange = false;

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

	function sanitizeKey(str) {
		if (!str) return '';
		return str.toLowerCase()
			.replace(/[áàäâ]/g, 'a')
			.replace(/[éèëê]/g, 'e')
			.replace(/[íìïî]/g, 'i')
			.replace(/[óòöô]/g, 'o')
			.replace(/[úùüû]/g, 'u')
			.replace(/ñ/g, 'n')
			.replace(/[^a-z0-9]/g, '');
	}

	function updateCityDatalist(type, towns) {
		var listId = type + '_city_datalist';
		var $list = $('#' + listId);
		if (!$list.length) {
			$list = $('<datalist id="' + listId + '"></datalist>');
			$('body').append($list);
		}

		$list.empty();
		if (towns && towns.length) {
			$.each(towns, function(i, town) {
				$list.append('<option value="' + town + '">');
			});
			$('#' + type + '_city').attr('list', listId);
		}
	}

	// --- 1. DIRECCIÓN A: CP -> PROVINCIA Y POBLACIÓN ---
	function handlePostcodeToAddress(type) {
		if (isProgrammaticChange || !isCountrySpain(type)) {
			return;
		}

		var $postcodeField = $('#' + type + '_postcode');
		if (!$postcodeField.length) return;

		var cp = $.trim($postcodeField.val() || '');
		if (cp.length < 2) return;

		isProgrammaticChange = true;

		// A1. Detectar provincia por 2 dígitos
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

					if ($opt.length && $stateField.val() !== $opt.val()) {
						$stateField.val($opt.val()).trigger('change');
						if ($.fn.select2) {
							$stateField.trigger('change.select2');
						}
					}
				} else {
					if ($stateField.val() !== pName && $stateField.val() !== pCode) {
						$stateField.val(pName).trigger('change');
					}
				}
			}

			// Actualizar sugerencias de localidades para esa provincia
			if (wpatAutofillOptions.spain_province_cities && wpatAutofillOptions.spain_province_cities[pCode]) {
				updateCityDatalist(type, wpatAutofillOptions.spain_province_cities[pCode]);
			}
		}

		// A2. Detectar población SOLO cuando el CP tiene 5 dígitos completos
		if (cp.length === 5 && wpatAutofillOptions.autofill_city === '1') {
			var $cityField = $('#' + type + '_city');
			if ($cityField.length) {
				var townsMatch = wpatAutofillOptions.spain_cp_to_cities ? wpatAutofillOptions.spain_cp_to_cities[cp] : null;
				if (townsMatch && townsMatch.length) {
					updateCityDatalist(type, townsMatch);
					if (!$cityField.val() || $cityField.data('wpat-autofilled') === '1') {
						$cityField.val(townsMatch[0]).trigger('change');
						$cityField.data('wpat-autofilled', '1');
					}
				}
			}
		}

		isProgrammaticChange = false;
	}

	// --- 2. DIRECCIÓN B: PROVINCIA + POBLACIÓN -> CÓDIGO POSTAL (RECÍPROCO) ---
	function handleAddressToPostcode(type) {
		if (isProgrammaticChange || !isCountrySpain(type)) {
			return;
		}

		var $stateField = $('#' + type + '_state');
		var $cityField = $('#' + type + '_city');
		var $postcodeField = $('#' + type + '_postcode');

		if (!$stateField.length || !$cityField.length || !$postcodeField.length) {
			return;
		}

		var stateVal = $.trim($stateField.val() || '');
		var cityVal = $.trim($cityField.val() || '');

		if (!stateVal || !cityVal) {
			return;
		}

		isProgrammaticChange = true;

		// Convertir código de estado a código corto de 2 letras (ej: 'MA', 'M', 'SE')
		var pCode = stateVal;
		if (stateVal.length > 2) {
			$.each(wpatAutofillOptions.spain_provinces, function(pref, data) {
				if (data.name.toLowerCase() === stateVal.toLowerCase()) {
					pCode = data.code;
					return false;
				}
			});
		}

		var lookupKey1 = sanitizeKey(cityVal) + '_' + pCode.toLowerCase();
		var lookupKey2 = sanitizeKey(cityVal);

		var matchedCP = null;
		if (wpatAutofillOptions.spain_city_to_cp) {
			matchedCP = wpatAutofillOptions.spain_city_to_cp[lookupKey1] || wpatAutofillOptions.spain_city_to_cp[lookupKey2];
		}

		if (matchedCP) {
			if (!$postcodeField.val() || $postcodeField.data('wpat-autofilled') === '1') {
				$postcodeField.val(matchedCP).trigger('change');
				$postcodeField.data('wpat-autofilled', '1');
			}
		}

		isProgrammaticChange = false;
	}

	// Escuchar cuando el usuario cambia la provincia -> Cargar sugerencias de municipios de esa provincia
	function onStateChange(type) {
		var $stateField = $('#' + type + '_state');
		var stateVal = $.trim($stateField.val() || '');
		if (!stateVal) return;

		var pCode = stateVal;
		$.each(wpatAutofillOptions.spain_provinces, function(pref, data) {
			if (data.code === stateVal || data.name.toLowerCase() === stateVal.toLowerCase()) {
				pCode = data.code;
				return false;
			}
		});

		if (wpatAutofillOptions.spain_province_cities && wpatAutofillOptions.spain_province_cities[pCode]) {
			updateCityDatalist(type, wpatAutofillOptions.spain_province_cities[pCode]);
		}

		handleAddressToPostcode(type);
	}

	// Escuchar eventos en billing y shipping
	$(document).on('input keyup blur change', '#billing_postcode', function() {
		handlePostcodeToAddress('billing');
	});

	$(document).on('input keyup blur change', '#shipping_postcode', function() {
		handlePostcodeToAddress('shipping');
	});

	$(document).on('change', '#billing_state', function() {
		onStateChange('billing');
	});

	$(document).on('change', '#shipping_state', function() {
		onStateChange('shipping');
	});

	$(document).on('input blur change', '#billing_city', function() {
		handleAddressToPostcode('billing');
	});

	$(document).on('input blur change', '#shipping_city', function() {
		handleAddressToPostcode('shipping');
	});

	$(document).on('change', '#ship-to-different-address-checkbox', function() {
		if ($(this).is(':checked')) {
			setTimeout(function() {
				handlePostcodeToAddress('shipping');
			}, 200);
		}
	});

	// Inicialización suave
	setTimeout(function() {
		if ($('#billing_postcode').val()) handlePostcodeToAddress('billing');
		if ($('#shipping_postcode').val()) handlePostcodeToAddress('shipping');
	}, 400);
});
