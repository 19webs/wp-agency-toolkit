/**
 * Filtro por Facetas AJAX de WooCommerce (Frontend) - WP Agency Toolkit
 */

jQuery(document).ready(function($) {
	var filterTimer = null;

	// Detectar cambio en cualquier input de faceta
	$(document).on('change input', '.wpat-facet-input', function(e) {
		// Si es input de número (precio), debounce de 400ms
		if ($(this).attr('type') === 'number') {
			clearTimeout(filterTimer);
			filterTimer = setTimeout(function() {
				triggerFacetFilter(1);
			}, 400);
		} else {
			triggerFacetFilter(1);
		}
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

	// Botón de resetear filtros
	$(document).on('click', '.wpat-reset-facets-btn', function(e) {
		e.preventDefault();
		var $form = $(this).closest('.wpat-facets-form');
		$form.find('input[type="checkbox"], input[type="radio"]').prop('checked', false);
		$form.find('input[type="number"], select').val('');
		triggerFacetFilter(1);
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

					// Actualizar URL con pushState
					if (window.history && window.history.pushState) {
						var queryString = $.param(dataObj);
						window.history.pushState(null, '', '?' + queryString);
					}
				}
			},
			error: function() {
				$targetContainer.removeClass('wpat-products-loading');
			}
		});
	}
});
