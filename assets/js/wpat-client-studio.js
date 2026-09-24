/**
 * WP Agency Toolkit - Client Studio JavaScript
 * Lógica interactiva para el entorno SaaS de redacción y edición.
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		// 1. Selector de Tema Claro / Oscuro
		$(document).on('click', '#wpat_studio_theme_toggle', function(e) {
			e.preventDefault();
			$('html').toggleClass('wpat-dark-mode');
			var isDark = $('html').hasClass('wpat-dark-mode');
			localStorage.setItem('wpat_studio_theme', isDark ? 'dark' : 'light');
		});

		// Restaurar tema guardado
		if (localStorage.getItem('wpat_studio_theme') === 'dark') {
			$('html').addClass('wpat-dark-mode');
		}

		// 2. Copiar Permalink
		$(document).on('click', '#wpat_copy_permalink_btn', function(e) {
			e.preventDefault();
			var url = $(this).data('url');
			if (!url) return;

			navigator.clipboard.writeText(url).then(function() {
				var $btn = $('#wpat_copy_permalink_btn');
				var origHtml = $btn.html();
				$btn.html('<span>¡Copiado!</span>');
				setTimeout(function() {
					$btn.html(origHtml);
				}, 2000);
			});
		});

		// 3. Uploader de Imagen Destacada (WP Media Frame)
		$(document).on('click', '#wpat_studio_image_dropzone, #wpat_studio_change_thumb_btn', function(e) {
			if ($(e.target).is('#wpat_studio_remove_thumb_btn')) return;
			e.preventDefault();

			var frame = wp.media({
				title: 'Seleccionar Imagen Destacada',
				button: { text: 'Establecer Imagen Destacada' },
				multiple: false
			});

			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				$('#wpat_studio_thumbnail_id').val(attachment.id);
				$('#wpat_studio_remove_thumb_flag').val('0');

				var imgUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
				$('#wpat_studio_thumb_img').attr('src', imgUrl);
				$('#wpat_studio_thumb_preview_wrap').show();
				$('#wpat_studio_thumb_placeholder').hide();
				$('#wpat_studio_image_dropzone').addClass('has-image');
			});

			frame.open();
		});

		// Quitar imagen destacada
		$(document).on('click', '#wpat_studio_remove_thumb_btn', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$('#wpat_studio_thumbnail_id').val('0');
			$('#wpat_studio_remove_thumb_flag').val('1');
			$('#wpat_studio_thumb_preview_wrap').hide();
			$('#wpat_studio_thumb_placeholder').show();
			$('#wpat_studio_image_dropzone').removeClass('has-image');
		});

		// 4. Reactividad en tiempo real: Título, Navbar Breadcrumbs & Google SERP
		function updateSeoPreview() {
			var mainTitle = $('#wpat_studio_post_title').val() || '';
			var seoTitle = $('#wpat_studio_seo_title').val() || '';
			var seoDesc = $('#wpat_studio_seo_desc').val() || '';
			var excerpt = $('#wpat_studio_post_excerpt').val() || '';

			// Navbar title
			$('#wpat_studio_nav_title_preview').text(mainTitle ? mainTitle : 'Nuevo borrador');

			// Google Title
			var finalTitle = seoTitle ? seoTitle : (mainTitle ? mainTitle : 'Título de la publicación');
			$('#wpat_studio_google_title_preview').text(finalTitle);

			// Google Desc
			var finalDesc = seoDesc ? seoDesc : (excerpt ? excerpt : 'Escribe una descripción atractiva para que los usuarios hagan clic en Google...');
			$('#wpat_studio_google_desc_preview').text(finalDesc);

			// Barra de caracteres Título SEO (Ideal: ~60)
			var titleLen = seoTitle.length;
			$('#wpat_seo_title_count').text(titleLen + ' / 60');
			var titlePct = Math.min(100, (titleLen / 60) * 100);
			$('#wpat_seo_title_bar').css('width', titlePct + '%');
			if (titleLen > 60) {
				$('#wpat_seo_title_bar').addClass('warning');
			} else {
				$('#wpat_seo_title_bar').removeClass('warning danger');
			}

			// Barra de caracteres Meta Descripción (Ideal: ~160)
			var descLen = seoDesc.length;
			$('#wpat_seo_desc_count').text(descLen + ' / 160');
			var descPct = Math.min(100, (descLen / 160) * 100);
			$('#wpat_seo_desc_bar').css('width', descPct + '%');
			if (descLen > 160) {
				$('#wpat_seo_desc_bar').addClass('warning');
			} else {
				$('#wpat_seo_desc_bar').removeClass('warning danger');
			}
		}

		$(document).on('input keyup', '#wpat_studio_post_title, #wpat_studio_seo_title, #wpat_studio_seo_desc, #wpat_studio_post_excerpt', updateSeoPreview);
		updateSeoPreview();

		// 5. Contador de palabras y tiempo de lectura
		function getEditorContent() {
			if (window.tinyMCE && tinyMCE.get('wpat_studio_content_editor') && !tinyMCE.get('wpat_studio_content_editor').isHidden()) {
				return tinyMCE.get('wpat_studio_content_editor').getContent();
			}
			return $('#wpat_studio_content_editor').val() || '';
		}

		function updateMetrics() {
			var content = getEditorContent();
			var text = content.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
			var words = text ? text.split(' ').length : 0;
			var minutes = Math.max(1, Math.round(words / 200));

			$('#wpat_studio_word_count').text(words);
			$('#wpat_studio_read_time').text(minutes + ' min');
		}

		$(document).on('input keyup change', '#wpat_studio_content_editor', updateMetrics);

		if (window.tinyMCE) {
			setTimeout(function() {
				if (tinyMCE.get('wpat_studio_content_editor')) {
					tinyMCE.get('wpat_studio_content_editor').on('input keyup change NodeChange', updateMetrics);
					updateMetrics();
				}
			}, 800);
		}

		// 6. Guardar / Publicar Publicación vía AJAX
		$(document).on('click', '.wpat-studio-save-action', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var targetStatus = $btn.data('status') || $('#wpat_studio_status_select').val() || 'publish';
			var postId = $('#wpat_studio_post_id').val() || 0;
			var postType = $('#wpat_studio_post_type').val() || 'post';
			var title = $('#wpat_studio_post_title').val();
			var content = getEditorContent();
			var excerpt = $('#wpat_studio_post_excerpt').val();
			var thumbId = $('#wpat_studio_thumbnail_id').val();
			var removeThumb = $('#wpat_studio_remove_thumb_flag').val();

			// Taxonomías
			var categories = [];
			$('.wpat-studio-tax-check:checked').each(function() {
				categories.push($(this).val());
			});

			// SEO
			var seoTitle = $('#wpat_studio_seo_title').val();
			var seoDesc = $('#wpat_studio_seo_desc').val();
			var seoKeyword = $('#wpat_studio_seo_keyword').val();

			// Metadatos CPT
			var cptMeta = {};
			$('.wpat-studio-cpt-field').each(function() {
				var key = $(this).data('meta-key');
				if (key) {
					cptMeta[key] = $(this).val();
				}
			});

			// Estado visual de carga
			$btn.addClass('loading').prop('disabled', true);
			$('#wpat_studio_autosave_indicator .wpat-studio-status-dot').removeClass('published draft').addClass('saving');
			$('#wpat_studio_autosave_status').text('Guardando cambios...');

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
						$('#wpat_studio_post_id').val(response.data.post_id);
						$('#wpat_studio_status_select').val(targetStatus);
						
						var dotClass = (targetStatus === 'publish') ? 'published' : 'draft';
						$('#wpat_studio_autosave_indicator .wpat-studio-status-dot').removeClass('saving draft published').addClass(dotClass);
						$('#wpat_studio_autosave_status').text('Guardado a las ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));

						// Botón publicado
						if (targetStatus === 'publish') {
							$('#wpat_studio_btn_publish .wpat-btn-label').text('Actualizar');
						}

						if (response.data.permalink) {
							$('#wpat_studio_preview_link').attr('href', response.data.permalink).css('display', 'inline-flex');
						}
					} else {
						$('#wpat_studio_autosave_status').text('Error al guardar');
						alert(response.data && response.data.message ? response.data.message : 'Error al guardar');
					}
				},
				error: function() {
					$btn.removeClass('loading').prop('disabled', false);
					$('#wpat_studio_autosave_status').text('Error de conexión');
					alert('Error de conexión con el servidor.');
				}
			});
		});

		// 7. Mover a la Papelera
		$(document).on('click', '#wpat_studio_trash_btn', function(e) {
			e.preventDefault();
			var postId = $('#wpat_studio_post_id').val();
			if (!postId || !confirm('¿Estás seguro de mover esta publicación a la papelera?')) return;

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
						window.location.href = $('.wpat-studio-back-link').attr('href');
					} else {
						alert(response.data && response.data.message ? response.data.message : 'Error al enviar a la papelera');
					}
				}
			});
		});

	});

})(jQuery);
