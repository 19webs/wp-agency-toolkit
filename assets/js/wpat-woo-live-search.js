/**
 * Buscador AJAX en Vivo de WooCommerce (Frontend) - WP Agency Toolkit
 */

jQuery(document).ready(function($) {
	var searchTimer = null;
	var activeIndex = -1;

	$(document).on('input', '.wpat-live-search-field', function() {
		var $field = $(this);
		var $wrap = $field.closest('.wpat-live-search-wrapper');
		var $dropdown = $wrap.find('.wpat-live-search-dropdown');
		var $spinner = $wrap.find('.wpat-live-search-spinner');
		var term = $.trim($field.val());

		clearTimeout(searchTimer);
		activeIndex = -1;

		if (term.length < wpatWooSearch.min_chars) {
			$dropdown.hide().empty();
			$spinner.hide();
			return;
		}

		$spinner.show();

		searchTimer = setTimeout(function() {
			$.ajax({
				url: wpatWooSearch.ajaxurl,
				type: 'GET',
				data: {
					action: 'wpat_frontend_product_search',
					term: term
				},
				success: function(response) {
					$spinner.hide();
					if (response.success && response.data.results && response.data.results.length > 0) {
						var html = '';
						$.each(response.data.results, function(i, item) {
							html += '<a href="' + item.url + '" class="wpat-live-search-item">';
							
							if (item.thumb) {
								html += '<img src="' + item.thumb + '" class="wpat-live-search-thumb" alt="" />';
							}

							html += '<div class="wpat-live-search-info">';
							html += '<h4 class="wpat-live-search-title">' + item.title + '</h4>';
							if (item.meta_text) {
								html += '<span class="wpat-live-search-meta">' + item.meta_text + '</span>';
							}
							html += '</div>';

							if (item.price_html || item.stock_html) {
								html += '<div class="wpat-live-search-price-stock">';
								if (item.price_html) {
									html += '<span class="wpat-live-search-price">' + item.price_html + '</span>';
								}
								if (item.stock_html) {
									html += item.stock_html;
								}
								html += '</div>';
							}

							html += '</a>';
						});

						if (response.data.total > response.data.results.length) {
							html += '<a href="' + response.data.view_all_url + '" class="wpat-live-search-view-all">' + wpatWooSearch.view_all + ' (' + response.data.total + ') &rarr;</a>';
						}

						$dropdown.html(html).show();
					} else {
						$dropdown.html('<div class="wpat-live-search-no-results">' + wpatWooSearch.no_results + '</div>').show();
					}
				},
				error: function() {
					$spinner.hide();
					$dropdown.html('<div class="wpat-live-search-no-results">Error en la búsqueda</div>').show();
				}
			});
		}, 300);
	});

	// Navegación por Teclado (Flechas arriba/abajo + Enter)
	$(document).on('keydown', '.wpat-live-search-field', function(e) {
		var $field = $(this);
		var $wrap = $field.closest('.wpat-live-search-wrapper');
		var $dropdown = $wrap.find('.wpat-live-search-dropdown');
		var $items = $dropdown.find('.wpat-live-search-item');

		if (!$dropdown.is(':visible') || !$items.length) {
			return;
		}

		if (e.keyCode === 40) { // Flecha abajo
			e.preventDefault();
			activeIndex++;
			if (activeIndex >= $items.length) activeIndex = 0;
			$items.removeClass('active').eq(activeIndex).addClass('active');
		} else if (e.keyCode === 38) { // Flecha arriba
			e.preventDefault();
			activeIndex--;
			if (activeIndex < 0) activeIndex = $items.length - 1;
			$items.removeClass('active').eq(activeIndex).addClass('active');
		} else if (e.keyCode === 13) { // Enter
			if (activeIndex >= 0 && activeIndex < $items.length) {
				e.preventDefault();
				window.location.href = $items.eq(activeIndex).attr('href');
			}
		}
	});

	// Cerrar desplegable si se hace clic fuera
	$(document).on('click', function(e) {
		if (!$(e.target).closest('.wpat-live-search-wrapper').length) {
			$('.wpat-live-search-dropdown').hide();
		}
	});
});
