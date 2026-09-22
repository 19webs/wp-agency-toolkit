/**
 * Filtro por Facetas AJAX de WooCommerce (Frontend) - WP AGENCY TOOLKIT
 */

jQuery(document).ready(function($) {
	'use strict';
	var filterTimer = null;
	var isPopState = false;

	// Sincronizar y actualizar barra de etiquetas activas
	function updateActiveTags() {
		var $bar = $('.wpat-facets-active-tags-bar');
		if (!$bar.length) return;

		var $list = $bar.find('.wpat-facets-tags-list');
		$list.empty();
		var tagCount = 0;

		var $form = $('.wpat-facets-form');
		if (!$form.length) return;

		// Checkboxes & Radios marcados
		$form.find('input[type="checkbox"]:checked, input[type="radio"]:checked').each(function() {
			var $input = $(this);
			var labelText = $input.siblings('.wpat-facet-label').text().trim() || $input.val();
			if (labelText) {
				var name = $input.attr('name');
				var val = $input.val();
				var $pill = $('<span class="wpat-facet-tag-pill">' + labelText + ' <span class="wpat-facet-tag-remove" data-name="' + name + '" data-val="' + val + '">&times;</span></span>');
				$list.append($pill);
				tagCount++;
			}
		});

		// Precios
		var minP = $form.find('input[name="min_price"]').val();
		var maxP = $form.find('input[name="max_price"]').val();
		var currSym = (typeof wpatWooFacets !== 'undefined' && wpatWooFacets.currency_symbol) ? wpatWooFacets.currency_symbol : '€';
		if (minP || maxP) {
			var priceTxt = 'Precio: ' + (minP ? minP + currSym : '0' + currSym) + ' - ' + (maxP ? maxP + currSym : '∞');
			var $pill = $('<span class="wpat-facet-tag-pill">' + priceTxt + ' <span class="wpat-facet-tag-remove" data-type="price">&times;</span></span>');
			$list.append($pill);
			tagCount++;
		}

		if (tagCount > 0) {
			$bar.slideDown(200);
		} else {
			$bar.slideUp(200);
		}
	}

	// Sincronizar parámetros de filtros con la URL del navegador
	function syncUrlState() {
		if (isPopState) return;
		var $form = $('.wpat-facets-form');
		if (!$form.length) return;

		var formData = $form.serializeArray();
		var params = new URLSearchParams();

		$.each(formData, function(i, field) {
			if (field.value !== '' && field.value !== null) {
				if (field.name.indexOf('[]') !== -1) {
					var cleanKey = field.name.replace('[]', '');
					params.append(cleanKey, field.value);
				} else {
					params.set(field.name, field.value);
				}
			}
		});

		var newQuery = params.toString();
		var newUrl = window.location.pathname + (newQuery ? '?' + newQuery : '');
		if (window.history && window.history.pushState) {
			window.history.pushState({ path: newUrl }, '', newUrl);
		}
	}

	// Restaurar formulario al usar botones Adelante/Atrás del navegador
	window.addEventListener('popstate', function(e) {
		isPopState = true;
		var params = new URLSearchParams(window.location.search);
		var $form = $('.wpat-facets-form');
		if ($form.length) {
			$form.find('input[type="checkbox"], input[type="radio"]').prop('checked', false);
			$form.find('input[type="number"]').val('');
			$form.find('select').val('menu_order');

			params.forEach(function(val, key) {
				var $input = $form.find('input[name="' + key + '[]"][value="' + val + '"], input[name="' + key + '"][value="' + val + '"]');
				if ($input.length) {
					$input.prop('checked', true);
				} else if ($form.find('input[name="' + key + '"]').length) {
					$form.find('input[name="' + key + '"]').val(val);
				} else if ($form.find('select[name="' + key + '"]').length) {
					$form.find('select[name="' + key + '"]').val(val);
				}
			});
			updateActiveTags();
			triggerFacetFilter(1, false);
		}
		isPopState = false;
	});

	// Detectar cambio en cualquier input de faceta
	$(document).on('change', '.wpat-facet-input:not([type="number"])', function(e) {
		updateActiveTags();
		syncUrlState();
		triggerFacetFilter(1, true);
	});

	$(document).on('input', '.wpat-facet-input[type="number"]', function(e) {
		updateActiveTags();
		clearTimeout(filterTimer);
		filterTimer = setTimeout(function() {
			syncUrlState();
			triggerFacetFilter(1, true);
		}, 400);
	});

	// Eliminar etiqueta individual
	$(document).on('click', '.wpat-facet-tag-remove', function(e) {
		e.preventDefault();
		var $tag = $(this);
		var name = $tag.data('name');
		var val = $tag.data('val');
		var type = $tag.data('type');

		var $form = $('.wpat-facets-form');
		if (type === 'price') {
			$form.find('input[name="min_price"], input[name="max_price"]').val('');
		} else if (name && val) {
			$form.find('input[name="' + name + '"][value="' + val + '"]').prop('checked', false);
		}

		updateActiveTags();
		syncUrlState();
		triggerFacetFilter(1, true);
	});

	// Botón de limpiar todo en la barra de tags o botón principal
	$(document).on('click', '.wpat-reset-facets-btn, .wpat-clear-all-tags-btn', function(e) {
		e.preventDefault();
		var $form = $('.wpat-facets-form');
		$form.find('input[type="checkbox"], input[type="radio"]').prop('checked', false);
		$form.find('input[type="number"]').val('');
		$form.find('select').val('menu_order');
		updateActiveTags();
		syncUrlState();
		triggerFacetFilter(1, true);
	});

	// Paginación AJAX
	$(document).on('click', '.woocommerce-pagination a, ul.page-numbers a', function(e) {
		var $link = $(this);
		var href = $link.attr('href');
		if (href) {
			var match = href.match(/paged?[\/=](\d+)/i);
			var page = match ? parseInt(match[1]) : 1;
			if (page) {
				e.preventDefault();
				triggerFacetFilter(page, true);
				var $target = $('.products, ul.products, .wpat-no-products-wrapper').first();
				if ($target.length) {
					$('html, body').animate({ scrollTop: $target.offset().top - 120 }, 300);
				}
			}
		}
	});

	// Abrir / Cerrar Drawer Móvil
	$(document).on('click', '.wpat-facets-mobile-toggle-btn', function(e) {
		e.preventDefault();
		$('#wpat-facets-main-container').addClass('wpat-drawer-open');
		$('.wpat-facets-backdrop').addClass('active');
		$('body').addClass('wpat-facets-drawer-active');
	});

	$(document).on('click', '.wpat-facets-mobile-close-btn, .wpat-facets-backdrop', function(e) {
		e.preventDefault();
		$('#wpat-facets-main-container').removeClass('wpat-drawer-open');
		$('.wpat-facets-backdrop').removeClass('active');
		$('body').removeClass('wpat-facets-drawer-active');
	});

	/**
	 * Ejecuta la llamada AJAX para actualizar el grid de productos de WooCommerce.
	 */
	function triggerFacetFilter(page, shouldSync) {
		var $form = $('.wpat-facets-form');
		if (!$form.length) return;

		var formData = $form.serializeArray();
		var dataObj = {
			action: 'wpat_filter_products',
			security: (typeof wpatWooFacets !== 'undefined' && wpatWooFacets.security) ? wpatWooFacets.security : '',
			paged: page || 1
		};

		$.each(formData, function(i, field) {
			if (field.name.indexOf('[]') !== -1) {
				var cleanName = field.name.replace('[]', '');
				if (!dataObj[cleanName]) {
					dataObj[cleanName] = [];
				}
				dataObj[cleanName].push(field.value);
			} else {
				dataObj[field.name] = field.value;
			}
		});

		var $targetContainer = $('.products, ul.products, .wpat-no-products-wrapper').first();
		if (!$targetContainer.length) {
			$targetContainer = $('.woocommerce-info').first().parent();
		}

		$targetContainer.addClass('wpat-products-loading');

		$.ajax({
			url: (typeof wpatWooFacets !== 'undefined' && wpatWooFacets.ajaxurl) ? wpatWooFacets.ajaxurl : '/wp-admin/admin-ajax.php',
			type: 'POST',
			data: dataObj,
			success: function(response) {
				$targetContainer.removeClass('wpat-products-loading');
				if (response.success) {
					// Actualizar catálogo de productos
					var $existingList = $('.products, ul.products, .wpat-no-products-wrapper').first();
					if ($existingList.length) {
						$existingList.replaceWith(response.data.html);
					} else {
						$targetContainer.html(response.data.html);
					}

					// Actualizar paginación
					var $pagination = $('.woocommerce-pagination, nav.woocommerce-pagination');
					if ($pagination.length) {
						if (response.data.pagination) {
							$pagination.replaceWith('<nav class="woocommerce-pagination">' + response.data.pagination + '</nav>');
						} else {
							$pagination.empty();
						}
					}

					// Disparar evento para compatibilidad con lazy load y tooltips
					$(document.body).trigger('wpat_facets_updated', [response.data]);
				}
			},
			error: function() {
				$targetContainer.removeClass('wpat-products-loading');
			}
		});
	}

	// Inicializar tags en carga inicial
	updateActiveTags();
});
