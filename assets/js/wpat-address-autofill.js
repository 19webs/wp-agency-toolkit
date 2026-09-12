/**
 * AUTOCOMPLETADO RECÍPROCO DE CP, PROVINCIA Y POBLACIÓN (RESET AL CAMBIAR PROVINCIA) - WP AGENCY TOOLKIT
 */
jQuery(document).ready(function($) {
	'use strict';

	if (typeof wpatAutofillOptions === 'undefined' || !wpatAutofillOptions.spain_provinces) {
		return;
	}

	var isProgrammatic = false;

	function getCountryData(type) {
		var $countryField = $('#' + type + '_country');
		var countryCode = 'ES';

		if ($countryField.length) {
			var val = $countryField.val() || $countryField.attr('value') || '';
			val = $.trim(val).toUpperCase();
			if (val) {
				countryCode = val;
			}
		} else if (type === 'shipping' || type === 'calc_shipping') {
			return getCountryData('billing');
		}

		if (wpatAutofillOptions.countries && wpatAutofillOptions.countries[countryCode]) {
			return wpatAutofillOptions.countries[countryCode];
		}

		// Fallback para España (ES)
		if (countryCode === 'ES' || countryCode === 'SPAIN' || countryCode === 'ESPAÑA') {
			return {
				provinces: wpatAutofillOptions.spain_provinces || {},
				prefix_to_city: wpatAutofillOptions.spain_prefix_to_city || {},
				city_to_cp: wpatAutofillOptions.spain_city_to_cp || {},
				province_cities: wpatAutofillOptions.spain_province_cities || {}
			};
		}

		return null;
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

	function getProvinceCodeFromVal(type, stateVal) {
		if (!stateVal) return '';
		var cData = getCountryData(type);
		if (!cData || !cData.provinces) return stateVal;

		var pCode = stateVal;
		$.each(cData.provinces, function(prefix, data) {
			if (data.code === stateVal || (data.name && data.name.toLowerCase() === stateVal.toLowerCase())) {
				pCode = data.code;
				return false;
			}
		});
		return pCode;
	}

	function convertCityToSelect(type, towns, currentVal) {
		var $cityField = $('#' + type + '_city');
		if (!$cityField.length) return;

		var nameAttr = $cityField.attr('name') || (type + '_city');
		var idAttr = $cityField.attr('id') || (type + '_city');

		var $select = $('<select name="' + nameAttr + '" id="' + idAttr + '" class="input-text wpat-city-select" style="width:100%; height:46px; border:1px solid #cbd5e1; border-radius:8px; padding:0 14px; font-size:14px; color:#0f172a; background:#ffffff; outline:none; appearance:none; -webkit-appearance:none; background-image:url(\'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%2364748b%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%222%22 d=%22M19 9l-7 7-7-7%22%3E%3C/path%3E%3C/svg%3E\'); background-repeat:no-repeat; background-position:right 12px center; background-size:16px 16px;"></select>');

		$select.append('<option value="">Selecciona tu población...</option>');

		var foundMatch = false;
		if (towns && towns.length) {
			$.each(towns, function(i, town) {
				var selectedAttr = '';
				if (currentVal && (town.toLowerCase() === currentVal.toLowerCase() || sanitizeKey(town) === sanitizeKey(currentVal))) {
					selectedAttr = ' selected="selected"';
					foundMatch = true;
				}
				$select.append('<option value="' + town + '"' + selectedAttr + '>' + town + '</option>');
			});
		}

		if (currentVal && !foundMatch && currentVal !== '__custom__') {
			$select.append('<option value="' + currentVal + '" selected="selected">' + currentVal + '</option>');
		}

		$select.append('<option value="__custom__">✏️ Otra población (Escribir manualmente)...</option>');

		if ($cityField.is('input')) {
			$cityField.replaceWith($select);
		} else if ($cityField.is('select')) {
			$cityField.html($select.html());
			if (currentVal && foundMatch) {
				$cityField.val(currentVal);
			} else {
				$cityField.val('');
			}
		}
	}

	function convertCityToInput(type, currentVal) {
		var $cityField = $('#' + type + '_city');
		if (!$cityField.length || $cityField.is('input')) return;

		var nameAttr = $cityField.attr('name') || (type + '_city');
		var idAttr = $cityField.attr('id') || (type + '_city');

		var $input = $('<input type="text" name="' + nameAttr + '" id="' + idAttr + '" class="input-text" style="width:100%; height:46px; border:1px solid #cbd5e1; border-radius:8px; padding:10px 14px; font-size:14px; color:#0f172a; background:#ffffff; outline:none;" placeholder="Escribe tu población..." />');
		if (currentVal && currentVal !== '__custom__') {
			$input.val(currentVal);
		}

		$cityField.replaceWith($input);
		$input.focus();
	}

	// --- 1. CP -> PROVINCIA Y POBLACIÓN ---
	function handlePostcodeChange(type) {
		if (isProgrammatic) return;

		var cData = getCountryData(type);
		if (!cData || !cData.provinces) return;

		var $postcodeField = $('#' + type + '_postcode');
		if (!$postcodeField.length) return;

		var cp = $.trim($postcodeField.val() || '');
		if (cp.length < 2) return;

		isProgrammatic = true;

		// 1A. Detectar Provincia con 2 dígitos
		var prefix2 = cp.substring(0, 2);
		var provinceData = cData.provinces[prefix2];

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

			// Actualizar el desplegable de poblaciones de esa provincia
			var towns = cData.province_cities ? cData.province_cities[pCode] : null;
			var currentCityVal = $('#' + type + '_city').val();

			// 1B. Detectar ciudad específica si tenemos 3 dígitos de CP
			var prefix3 = cp.substring(0, 3);
			var matchedCity = cData.prefix_to_city ? cData.prefix_to_city[prefix3] : null;

			if (matchedCity) {
				currentCityVal = matchedCity;
			}

			convertCityToSelect(type, towns, currentCityVal);

			if (matchedCity) {
				$('#' + type + '_city').val(matchedCity);
			}
		}

		isProgrammatic = false;
	}

	// --- 2. CAMBIO DE PROVINCIA (RESET POBLACIÓN & LIMPIAR CP) ---
	function handleStateChange(type) {
		if (isProgrammatic) return;

		var cData = getCountryData(type);
		if (!cData || !cData.provinces) return;

		var $stateField = $('#' + type + '_state');
		var stateVal = $.trim($stateField.val() || '');
		var pCode = getProvinceCodeFromVal(type, stateVal);

		isProgrammatic = true;

		// 2A. Limpiar el Código Postal al cambiar manualmente de provincia
		var $postcodeField = $('#' + type + '_postcode');
		if ($postcodeField.length) {
			$postcodeField.val('');
		}

		// 2B. Resetear la población a "Selecciona tu población..." ("") y actualizar municipios de la nueva provincia
		if (pCode && cData.province_cities && cData.province_cities[pCode]) {
			var towns = cData.province_cities[pCode];
			convertCityToSelect(type, towns, '');
		} else {
			convertCityToSelect(type, [], '');
		}

		isProgrammatic = false;
	}

	// --- 3. SELECCIÓN DE POBLACIÓN -> AUTOCOMPLETAR CP ---
	function handleCityChange(type) {
		if (isProgrammatic) return;

		var cData = getCountryData(type);
		if (!cData) return;

		var $stateField = $('#' + type + '_state');
		var stateVal = $.trim($stateField.val() || '');
		var pCode = getProvinceCodeFromVal(type, stateVal);

		var $cityField = $('#' + type + '_city');
		var cityVal = $.trim($cityField.val() || '');

		if (cityVal === '__custom__') {
			convertCityToInput(type, '');
			return;
		}

		if (!cityVal || !pCode) return;

		isProgrammatic = true;

		// Buscar el Código Postal correspondiente a la población elegida
		var key1 = sanitizeKey(cityVal) + '_' + pCode.toLowerCase();
		var key2 = sanitizeKey(cityVal);
		var matchedCP = null;

		if (cData.city_to_cp) {
			matchedCP = cData.city_to_cp[key1] || cData.city_to_cp[key2];
		}

		if (matchedCP) {
			var $postcodeField = $('#' + type + '_postcode');
			if ($postcodeField.length) {
				$postcodeField.val(matchedCP).trigger('change');
			}
		}

		isProgrammatic = false;
	}

	// Initial load helper
	function initAddressFields(type) {
		var $stateField = $('#' + type + '_state');
		if (!$stateField.length) return;
		var stateVal = $.trim($stateField.val() || '');
		var pCode = getProvinceCodeFromVal(type, stateVal);
		var cityVal = $.trim($('#' + type + '_city').val() || '');
		var cData = getCountryData(type);
		if (!cData) return;

		if (pCode && cData.province_cities && cData.province_cities[pCode]) {
			var towns = cData.province_cities[pCode];
			convertCityToSelect(type, towns, cityVal);
		}
	}

	// Escuchadores de eventos
	$(document).on('input keyup blur change', '#billing_postcode', function() {
		handlePostcodeChange('billing');
	});

	$(document).on('input keyup blur change', '#shipping_postcode', function() {
		handlePostcodeChange('shipping');
	});

	$(document).on('input keyup blur change', '#calc_shipping_postcode', function() {
		handlePostcodeChange('calc_shipping');
	});

	$(document).on('change', '#billing_state', function() {
		handleStateChange('billing');
	});

	$(document).on('change', '#shipping_state', function() {
		handleStateChange('shipping');
	});

	$(document).on('change', '#calc_shipping_state', function() {
		handleStateChange('calc_shipping');
	});

	$(document).on('change', '#billing_city', function() {
		handleCityChange('billing');
	});

	$(document).on('change', '#shipping_city', function() {
		handleCityChange('shipping');
	});

	$(document).on('change', '#calc_shipping_city', function() {
		handleCityChange('calc_shipping');
	});

	$(document).on('change', '#ship-to-different-address-checkbox', function() {
		if ($(this).is(':checked')) {
			setTimeout(function() {
				initAddressFields('shipping');
			}, 200);
		}
	});

	$(document.body).on('updated_cart_totals updated_wc_div', function() {
		initAddressFields('calc_shipping');
	});

	$(document).on('click', '.shipping-calculator-button', function() {
		setTimeout(function() {
			initAddressFields('calc_shipping');
		}, 100);
	});

	// Inicialización al cargar la página (respetando datos pre-rellenados)
	setTimeout(function() {
		initAddressFields('billing');
		initAddressFields('shipping');
		initAddressFields('calc_shipping');
	}, 400);
});
