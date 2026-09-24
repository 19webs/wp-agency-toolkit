/**
 * WP Agency Toolkit - Client Studio App JavaScript
 * Full interactive logic for the modern SaaS Editorial Studio.
 */

/* Funciones Globales para el Editor WYSIWYG */
function wpatFormatDoc(cmd, value) {
	if (typeof value === 'undefined') value = null;
	document.execCommand(cmd, false, value);
	var editor = document.getElementById('wpat-wysiwyg-editor-area');
	if (editor) editor.focus();
	wpatUpdateStats();
}

function wpatAddLink() {
	var url = prompt('Introduce la URL del enlace:', 'https://');
	if (url) {
		wpatFormatDoc('createLink', url);
	}
}

function wpatInsertMediaImage() {
	if (typeof wp !== 'undefined' && wp.media) {
		var frame = wp.media({
			title: 'Insertar Imagen en el Contenido',
			button: { text: 'Insertar en la publicación' },
			multiple: false
		});

		frame.on('select', function() {
			var attachment = frame.state().get('selection').first().toJSON();
			var imgUrl = attachment.sizes && attachment.sizes.large ? attachment.sizes.large.url : attachment.url;
			wpatFormatDoc('insertImage', imgUrl);
		});

		frame.open();
	} else {
		var manualUrl = prompt('URL de la imagen:', 'https://');
		if (manualUrl) wpatFormatDoc('insertImage', manualUrl);
	}
}

function wpatUpdateStats() {
	var editor = document.getElementById('wpat-wysiwyg-editor-area');
	if (!editor) return;
	var text = editor.innerText || editor.textContent || '';
	var clean = text.trim();
	var words = clean ? clean.split(/\s+/).length : 0;
	var minutes = Math.max(1, Math.round(words / 200));

	var wordEl = document.getElementById('wpat-word-count');
	var readEl = document.getElementById('wpat-read-time');
	if (wordEl) wordEl.innerText = words;
	if (readEl) readEl.innerText = minutes + ' min';
}

(function($) {
	'use strict';

	$(document).ready(function() {

		// 1. Alternador de Tema Oscuro / Claro
		function initTheme() {
			var saved = localStorage.getItem('wpat_studio_theme');
			if (saved === 'dark') {
				$('html').addClass('wpat-dark-mode');
				$('#theme-icon').text('☀️');
				$('#theme-text').text('Modo Claro');
			}
		}
		initTheme();

		$(document).on('click', '#theme-toggle-btn', function(e) {
			e.preventDefault();
			$('html').toggleClass('wpat-dark-mode');
			var isDark = $('html').hasClass('wpat-dark-mode');
			localStorage.setItem('wpat_studio_theme', isDark ? 'dark' : 'light');
			$('#theme-icon').text(isDark ? '☀️' : '🌙');
			$('#theme-text').text(isDark ? 'Modo Claro' : 'Modo Oscuro');
		});

		// 2. Alternador de Vistas (Listado <-> Editor)
		function switchView(view) {
			if (view === 'editor') {
				$('#view-list').addClass('wpat-hidden');
				$('#view-editor').removeClass('wpat-hidden');
				$('#btn-view-editor').addClass('active');
				$('#btn-view-list').removeClass('active');
			} else {
				$('#view-editor').addClass('wpat-hidden');
				$('#view-list').removeClass('wpat-hidden');
				$('#btn-view-list').addClass('active');
				$('#btn-view-editor').removeClass('active');
			}
		}

		$(document).on('click', '#btn-view-list', function() { switchView('list'); });
		$(document).on('click', '#btn-view-editor, #btn-create-new-post', function() {
			// Si hace clic en crear nueva, limpiar formulario si no estamos en modo nuevo
			if ($(this).is('#btn-create-new-post')) {
				$('#wpat_post_id').val('0');
				$('#wpat-post-title-input').val('');
				$('#wpat-wysiwyg-editor-area').html('<p>Escribe tu contenido aquí...</p>');
				$('#wpat-post-excerpt-input').val('');
				$('#wpat_thumbnail_id').val('0');
				$('#wpat-preview-img-tag').attr('src', '').addClass('wpat-hidden');
				$('#wpat-dropzone-empty-wrap').show();
				$('#wpat-dropzone-holder').removeClass('has-img');
				$('#wpat-remove-image-btn').addClass('wpat-hidden');
				$('#wpat-btn-publish .wpat-btn-text').text('🚀 Publicar en la Web');
				$('#wpat-status-label').text('Nuevo borrador');
				updateSeoPreview();
			}
			switchView('editor');
		});

		$(document).on('click', '#btn-back-to-list', function() { switchView('list'); });

		// 3. Selector de Tipo de Contenido
		$(document).on('change', '#wpat-post-type-selector', function() {
			var newPt = $(this).val();
			var adminBase = window.location.href.split('?')[0];
			window.location.href = adminBase + '?page=wpat-client-studio&post_type=' + newPt + '&view=list';
		});

		// 4. Filtro por Estado en Listado (Todos, Publicados, Borradores)
		$(document).on('click', '.wpat-filter-tab', function() {
			$('.wpat-filter-tab').removeClass('active');
			$(this).addClass('active');
			var status = $(this).data('status');

			$('.wpat-item-row').each(function() {
				var itemStatus = $(this).data('status');
				if (status === 'all' || itemStatus === status) {
					$(this).show();
				} else {
					$(this).hide();
				}
			});
		});

		// 5. Buscador en Tiempo Real en Listado
		$(document).on('input', '#wpat-list-search-input', function() {
			var query = $(this).val().toLowerCase().trim();
			$('.wpat-item-row').each(function() {
				var title = $(this).data('title') || '';
				if (!query || title.indexOf(query) !== -1) {
					$(this).show();
				} else {
					$(this).hide();
				}
			});
		});

		// 6. Clic en Editar Post desde el Listado
		$(document).on('click', '.wpat-trigger-edit-post', function(e) {
			e.preventDefault();
			var postId = $(this).data('id');
			var pt = $('#wpat-post-type-selector').val() || 'post';
			var adminBase = window.location.href.split('?')[0];
			window.location.href = adminBase + '?page=wpat-client-studio&post_type=' + pt + '&view=editor&post_id=' + postId;
		});

		// 7. Reactividad Google SERP & Estadísticas
		function updateSeoPreview() {
			var postTitle = $('#wpat-post-title-input').val() || '';
			var seoTitle = $('#wpat-seo-title').val() || '';
			var excerpt = $('#wpat-post-excerpt-input').val() || '';
			var seoDesc = $('#wpat-seo-desc').val() || '';

			var finalTitle = seoTitle ? seoTitle : (postTitle ? postTitle : 'Título de la publicación');
			var finalDesc = seoDesc ? seoDesc : (excerpt ? excerpt : 'Descripción previa que verán los usuarios en los resultados de Google...');

			$('#wpat-google-title-preview').text(finalTitle);
			$('#wpat-google-desc-preview').text(finalDesc);
		}

		$(document).on('input keyup', '#wpat-post-title-input, #wpat-seo-title, #wpat-post-excerpt-input, #wpat-seo-desc', updateSeoPreview);
		$(document).on('input keyup', '#wpat-wysiwyg-editor-area', wpatUpdateStats);
		wpatUpdateStats();

		// 8. Selector de Imagen Destacada con WP Media
		$(document).on('click', '#wpat-dropzone-holder, #wpat-change-image-btn', function(e) {
			if ($(e.target).is('#wpat-remove-image-btn')) return;
			e.preventDefault();

			var frame = wp.media({
				title: 'Seleccionar Imagen Destacada',
				button: { text: 'Establecer como Imagen Destacada' },
				multiple: false
			});

			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				$('#wpat_thumbnail_id').val(attachment.id);
				$('#wpat_remove_thumbnail').val('0');

				var imgUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
				$('#wpat-preview-img-tag').attr('src', imgUrl).removeClass('wpat-hidden');
				$('#wpat-dropzone-empty-wrap').hide();
				$('#wpat-dropzone-holder').addClass('has-img');
				$('#wpat-remove-image-btn').removeClass('wpat-hidden');
			});

			frame.open();
		});

		// Eliminar imagen destacada
		$(document).on('click', '#wpat-remove-image-btn', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$('#wpat_thumbnail_id').val('0');
			$('#wpat_remove_thumbnail').val('1');
			$('#wpat-preview-img-tag').attr('src', '').addClass('wpat-hidden');
			$('#wpat-dropzone-empty-wrap').show();
			$('#wpat-dropzone-holder').removeClass('has-img');
			$(this).addClass('wpat-hidden');
		});

		// 9. Guardar / Publicar Publicación vía AJAX
		$(document).on('click', '.wpat-save-post-action', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var targetStatus = $btn.data('status') || $('#wpat-status-select').val() || 'publish';
			var postId = $('#wpat_post_id').val() || 0;
			var postType = $('#wpat_post_type').val() || 'post';
			var title = $('#wpat-post-title-input').val();
			var content = $('#wpat-wysiwyg-editor-area').html();
			var excerpt = $('#wpat-post-excerpt-input').val();
			var thumbId = $('#wpat_thumbnail_id').val();
			var removeThumb = $('#wpat_remove_thumbnail').val();

			// Categorías
			var categories = [];
			$('.wpat-tax-checkbox:checked').each(function() {
				categories.push($(this).val());
			});

			// SEO
			var seoTitle = $('#wpat-seo-title').val();
			var seoDesc = $('#wpat-seo-desc').val();
			var seoKeyword = $('#wpat-seo-keyword').val();

			// CPT Meta
			var cptMeta = {
				client_company: $('#wpat-cpt-client').val(),
				project_budget: $('#wpat-cpt-budget').val(),
				delivery_date: $('#wpat-cpt-date').val(),
				project_url: $('#wpat-cpt-url').val()
			};

			$btn.addClass('loading').prop('disabled', true);
			$('#wpat-autosave-status').html('<span class="wpat-pulse-circle"></span> Guardando...');

			var ajaxUrl = (typeof wpat_object !== 'undefined' && wpat_object.ajax_url) ? wpat_object.ajax_url : ajaxurl;
			var nonce = (typeof wpatStudioConfig !== 'undefined' && wpatStudioConfig.nonce) ? wpatStudioConfig.nonce : '';

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpat_studio_save_post',
					security: nonce,
					post_id: postId,
					post_type: postType,
					title: title,
					content: content,
					excerpt: excerpt,
					post_status: targetStatus,
					thumbnail_id: thumbId,
					remove_thumbnail: removeThumb,
					categories: categories,
					seo_title: seoTitle,
					seo_desc: seoDesc,
					seo_keyword: seoKeyword,
					cpt_meta: cptMeta
				},
				success: function(response) {
					$btn.removeClass('loading').prop('disabled', false);
					if (response.success) {
						$('#wpat_post_id').val(response.data.post_id);
						$('#wpat-status-select').val(targetStatus);
						$('#wpat-autosave-status').html('<span class="wpat-pulse-circle"></span> Guardado a las ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));

						if (targetStatus === 'publish') {
							$('#wpat-btn-publish .wpat-btn-text').text('🚀 Actualizar');
						}

						if (response.data.permalink) {
							$('#wpat-btn-preview').attr('href', response.data.permalink).show();
						}
					} else {
						$('#wpat-autosave-status').text('Error al guardar');
						alert(response.data && response.data.message ? response.data.message : 'Error al guardar');
					}
				},
				error: function() {
					$btn.removeClass('loading').prop('disabled', false);
					$('#wpat-autosave-status').text('Error de conexión');
					alert('Error de conexión con el servidor.');
				}
			});
		});

		// 10. Mover a la Papelera
		$(document).on('click', '.wpat-trigger-trash-post', function(e) {
			e.preventDefault();
			var postId = $(this).data('id');
			if (!postId || !confirm('¿Estás seguro de mover esta publicación a la papelera?')) return;

			var $row = $(this).closest('.wpat-item-row');
			var ajaxUrl = (typeof wpat_object !== 'undefined' && wpat_object.ajax_url) ? wpat_object.ajax_url : ajaxurl;
			var nonce = (typeof wpatStudioConfig !== 'undefined' && wpatStudioConfig.nonce) ? wpatStudioConfig.nonce : '';

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpat_studio_trash_post',
					security: nonce,
					post_id: postId
				},
				success: function(response) {
					if (response.success) {
						if ($row.length) {
							$row.fadeOut(250, function() { $(this).remove(); });
						} else {
							// Estamos dentro del editor
							switchView('list');
							window.location.reload();
						}
					} else {
						alert(response.data && response.data.message ? response.data.message : 'Error al eliminar');
					}
				}
			});
		});

	});

})(jQuery);
