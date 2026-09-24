/**
 * WP Agency Toolkit - JavaScript para Diseño Moderno SaaS de Tablas (edit.php)
 * Mejora de forma no destructiva estados, miniaturas y acciones de fila.
 */

(function($) {
	'use strict';

	var config = window.wpatTablesConfig || {
		enablePills: true,
		enableRowActions: true,
		enableSticky: false,
		enableThumbZoom: true,
		i18n: {
			published: 'Publicado',
			draft: 'Borrador',
			pending: 'Pendiente',
			private: 'Privado',
			scheduled: 'Programado',
			trash: 'Papelera'
		}
	};

	// 1. Transformar estados de publicación en Badges tipo Pill
	function enhancePostStatePills() {
		if (!config.enablePills) return;

		$('.wp-list-table tbody tr').each(function() {
			var $tr = $(this);
			var $titleCell = $tr.find('.column-title, .column-name, .column-primary');
			if (!$titleCell.length) return;

			// Buscar elementos .post-state de WP nativo
			$titleCell.find('.post-state').each(function() {
				var $state = $(this);
				if ($state.hasClass('wpat-processed')) return;

				var rawText = $state.text().trim();
				var lower = rawText.toLowerCase();
				var pillClass = 'wpat-status-draft';

				if (lower.indexOf('borrador') !== -1 || lower.indexOf('draft') !== -1) {
					pillClass = 'wpat-status-draft';
				} else if (lower.indexOf('privad') !== -1 || lower.indexOf('private') !== -1) {
					pillClass = 'wpat-status-private';
				} else if (lower.indexOf('pendient') !== -1 || lower.indexOf('pending') !== -1) {
					pillClass = 'wpat-status-pending';
				} else if (lower.indexOf('programad') !== -1 || lower.indexOf('future') !== -1 || lower.indexOf('scheduled') !== -1) {
					pillClass = 'wpat-status-future';
				} else if (lower.indexOf('papelera') !== -1 || lower.indexOf('trash') !== -1) {
					pillClass = 'wpat-status-trash';
				}

				$state.addClass('wpat-status-pill ' + pillClass + ' wpat-processed');
			});

			// Eliminar guiones sueltos " — " que WordPress inserta antes del estado
			$titleCell.contents().filter(function() {
				return this.nodeType === 3 && $.trim(this.nodeValue) === '—';
			}).remove();
		});
	}

	// 2. Zoom flotante de imagen destacada al pasar el ratón
	function initThumbnailHoverZoom() {
		if (!config.enableThumbZoom) return;

		var $preview = $('#wpat_thumb_hover_preview');
		if (!$preview.length) {
			$preview = $('<div id="wpat_thumb_hover_preview"><img src="" alt="" /></div>').appendTo('body');
		}

		$(document).on('mouseenter', '.wpat-table-thumb-wrap[data-large-img]', function(e) {
			var largeUrl = $(this).data('large-img');
			if (!largeUrl) return;

			$preview.find('img').attr('src', largeUrl);
			$preview.css({
				top: (e.clientY - 60) + 'px',
				left: (e.clientX + 24) + 'px'
			}).stop(true, true).fadeIn(150);
		});

		$(document).on('mousemove', '.wpat-table-thumb-wrap[data-large-img]', function(e) {
			$preview.css({
				top: (e.clientY - 60) + 'px',
				left: (e.clientX + 24) + 'px'
			});
		});

		$(document).on('mouseleave', '.wpat-table-thumb-wrap[data-large-img]', function() {
			$preview.stop(true, true).fadeOut(100);
		});
	}

	// 3. Limpiar separadores de tubería "|" en .row-actions de forma no destructiva
	function cleanRowActionPipes() {
		$('.wp-list-table .row-actions').each(function() {
			var $actions = $(this);
			$actions.contents().filter(function() {
				return this.nodeType === 3 && $.trim(this.nodeValue) === '|';
			}).remove();
		});
	}

	// Inicialización en carga del DOM
	$(document).ready(function() {
		enhancePostStatePills();
		cleanRowActionPipes();
		initThumbnailHoverZoom();

		// Re-aplicar mejoras si se ejecuta edición rápida AJAX de WordPress
		$(document).ajaxComplete(function(event, xhr, settings) {
			if (settings.data && typeof settings.data === 'string' && settings.data.indexOf('action=inline-save') !== -1) {
				setTimeout(function() {
					enhancePostStatePills();
					cleanRowActionPipes();
				}, 100);
			}
		});
	});

})(jQuery);
