/**
 * Filtro por Facetas AJAX de WooCommerce (Frontend) - WP AGENCY TOOLKIT
 */

jQuery(document).ready(function($) {
	'use strict';
	var filterTimer = null;

	// Actualizar barra de etiquetas activas
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
		if (minP || maxP) {
			var priceTxt = 'Precio: ' + (minP ? minP + '€' : '0€') + ' - ' + (maxP ? maxP + '€' : '∞');
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

	// Detectar cambio en cualquier input de faceta
	$(document).on('change input', '.wpat-facet-input', function(e) {
		updateActiveTags();
		if ($(this).attr('type') === 'number') {
			clearTimeout(filterTimer);
			filterTimer = setTimeout(function() {
				triggerFacetFilter(1);
			}, 400);
		} else {
			triggerFacetFilter(1);
		}
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
		triggerFacetFilter(1);
	});

	// Botón de limpiar todo en la barra de tags o botón principal
	$(document).on('click', '.wpat-reset-facets-btn, .wpat-clear-all-tags-btn', function(e) {
		e.preventDefault();
		var $form = $('.wpat-facets-form');
		$form.find('input[type="checkbox"], input[type="radio"]').prop('checked', false);
		$form.find('input[type="number"], select').val('');
		updateActiveTags();
		triggerFacetFilter(1);
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
				triggerFacetFilter(page);
				$('html, body').animate({ scrollTop: $('.products').offset().top - 100 }, 300);
			}
		}
	});

	/**
	 * Ejecuta la llamada AJAX para actualizar el grid de productos de WooCommerce.
	 */
	function triggerFacetFilter(page) {
		var $form = $('.wpat-facets-form');
		if (!$form.length) return;

		var formData = $form.serializeArray();
		var dataObj = {
			action: 'wpat_filter_products',
			paged: page || 1
		};

		$.each(formData, function(i, field) {
			if (dataObj[field.name]) {
				if (!$.isArray(dataObj[field.name])) {
					dataObj[field.name] = [dataObj[field.name]];
				}
				dataObj[field.name].push(field.value);
			} else {
				dataObj[field.name] = field.value;
			}
		});

		var $targetContainer = $('.products, ul.products').first();
		if (!$targetContainer.length) {
			$targetContainer = $('.woocommerce-info').first().parent();
		}

		$targetContainer.addClass('wpat-products-loading');

		$.ajax({
			url: wpatWooFacets.ajaxurl,
			type: 'POST',
			data: dataObj,
			success: function(response) {
				$targetContainer.removeClass('wpat-products-loading');
				if (response.success) {
					if ($('.products, ul.products').length) {
						$('.products, ul.products').replaceWith(response.data.html);
					} else if ($('.wpat-no-products-found').length) {
						$('.wpat-no-products-found').replaceWith(response.data.html);
					} else {
						$targetContainer.html(response.data.html);
					}

					// Actualizar paginación
					if ($('.woocommerce-pagination, nav.woocommerce-pagination').length) {
						if (response.data.pagination) {
							$('.woocommerce-pagination, nav.woocommerce-pagination').replaceWith('<nav class="woocommerce-pagination">' + response.data.pagination + '</nav>');
						} else {
							$('.woocommerce-pagination, nav.woocommerce-pagination').empty();
						}
					}
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
