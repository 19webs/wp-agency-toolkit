/**
 * WP Agency Toolkit - Client Studio JavaScript
 * Lógica interactiva para el entorno de redacción y CPTs para clientes.
 */

(function($) {
	'use strict';

	// 1. Selector de Imagen Destacada con WP Media
	$(document).on('click', '#wpat_studio_featured_img_btn, .wpat-studio-featured-img-holder', function(e) {
		e.preventDefault();
		var frame = wp.media({
			title: 'Seleccionar Imagen Destacada',
			button: { text: 'Establecer como Imagen Destacada' },
			multiple: false
		});

		frame.on('select', function() {
			var attachment = frame.state().get('selection').first().toJSON();
			$('#wpat_studio_thumbnail_id').val(attachment.id);
			$('#wpat_studio_remove_thumb_flag').val('0');

			var imgUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
			$('#wpat_studio_thumb_preview').html('<img src="' + imgUrl + '" alt="" />').show();
			$('#wpat_studio_thumb_placeholder').hide();
			$('#wpat_studio_remove_thumb_btn').show();
		});

		frame.open();
	});

	// Quitar imagen destacada
	$(document).on('click', '#wpat_studio_remove_thumb_btn', function(e) {
		e.preventDefault();
		e.stopPropagation();
		$('#wpat_studio_thumbnail_id').val('0');
		$('#wpat_studio_remove_thumb_flag').val('1');
		$('#wpat_studio_thumb_preview').empty().hide();
		$('#wpat_studio_thumb_placeholder').show();
		$(this).hide();
	});

	// 2. Reactividad en tiempo real con Google SERP Snippet
	$(document).on('input keyup', '#wpat_studio_post_title, #wpat_studio_seo_title', function() {
		var title = $('#wpat_studio_seo_title').val() || $('#wpat_studio_post_title').val() || 'Título de la publicación';
		$('#wpat_studio_google_title_preview').text(title);
	});

	$(document).on('input keyup', '#wpat_studio_post_excerpt, #wpat_studio_seo_desc', function() {
		var desc = $('#wpat_studio_seo_desc').val() || $('#wpat_studio_post_excerpt').val() || 'Descripción previa del artículo que se mostrará en los resultados de búsqueda de Google.';
		$('#wpat_studio_google_desc_preview').text(desc);
	});

	// 3. Contador de palabras y tiempo de lectura
	function updateWordCount() {
		var content = getEditorContent();
		var text = content.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
		var words = text ? text.split(' ').length : 0;
		var minutes = Math.max(1, Math.round(words / 200));

		$('#wpat_studio_word_count').text(words);
		$('#wpat_studio_read_time').text(minutes + ' min');
	}

	function getEditorContent() {
		if (window.tinyMCE && tinyMCE.get('wpat_studio_content_editor')) {
			return tinyMCE.get('wpat_studio_content_editor').getContent();
		}
		return $('#wpat_studio_content_editor').val() || '';
	}

	$(document).on('input keyup change', '#wpat_studio_content_editor', updateWordCount);

	if (window.tinyMCE) {
		setTimeout(function() {
			if (tinyMCE.get('wpat_studio_content_editor')) {
				tinyMCE.get('wpat_studio_content_editor').on('input keyup change', updateWordCount);
				updateWordCount();
			}
		}, 1000);
	}

	// 4. Guardar / Publicar Publicación vía AJAX
	$(document).on('click', '.wpat-studio-save-action', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var targetStatus = $btn.data('status') || 'publish';
		var postId = $('#wpat_studio_post_id').val() || 0;
		var postType = $('#wpat_studio_post_type').val() || 'post';
		var title = $('#wpat_studio_post_title').val();
		var content = getEditorContent();
		var excerpt = $('#wpat_studio_post_excerpt').val();
		var thumbId = $('#wpat_studio_thumbnail_id').val();
		var removeThumb = $('#wpat_studio_remove_thumb_flag').val();

		// Categorías
		var categories = [];
		$('input[name="wpat_studio_categories[]"]:checked').each(function() {
			categories.push($(this).val());
		});

		// SEO
		var seoTitle = $('#wpat_studio_seo_title').val();
		var seoDesc = $('#wpat_studio_seo_desc').val();
		var seoKeyword = $('#wpat_studio_seo_keyword').val();

		// Campos CPT dinámicos
		var cptMeta = {};
		$('.wpat-studio-cpt-field').each(function() {
			var key = $(this).data('meta-key');
			if (key) {
				cptMeta[key] = $(this).val();
			}
		});

		$btn.prop('disabled', true).addClass('opacity-75');

		$.ajax({
			url: wpat_object.ajax_url,
			type: 'POST',
			data: {
				action: 'wpat_studio_save_post',
				security: wpatStudioConfig.nonce,
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
				$btn.prop('disabled', false).removeClass('opacity-75');
				if (response.success) {
					$('#wpat_studio_post_id').val(response.data.post_id);
					$('#wpat_studio_autosave_status').html('<span class="wpat-pulse-dot" style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#10b981; margin-right:4px;"></span> Guardado a las ' + new Date().toLocaleTimeString());
					if (typeof showToast === 'function') {
						showToast(response.data.message || 'Publicación guardada con éxito', false);
					} else {
						alert(response.data.message || 'Publicación guardada');
					}
					if (response.data.permalink) {
						$('#wpat_studio_preview_link').attr('href', response.data.permalink).show();
					}
				} else {
					if (typeof showToast === 'function') {
						showToast(response.data.message || 'Error al guardar', true);
					} else {
						alert(response.data.message);
					}
				}
			},
			error: function() {
				$btn.prop('disabled', false).removeClass('opacity-75');
				if (typeof showToast === 'function') {
					showToast('Error de conexión AJAX al guardar la publicación', true);
				} else {
					alert('Error de conexión');
				}
			}
		});
	});

	// 5. Enviar Publicación a la Papelera
	$(document).on('click', '.wpat-studio-trash-btn', function(e) {
		e.preventDefault();
		var postId = $(this).data('id');
		if (!confirm('¿Estás seguro de que deseas mover esta publicación a la papelera?')) return;

		var $row = $(this).closest('.wpat-studio-item-row');

		$.ajax({
			url: wpat_object.ajax_url,
			type: 'POST',
			data: {
				action: 'wpat_studio_trash_post',
				security: wpatStudioConfig.nonce,
				post_id: postId
			},
			success: function(response) {
				if (response.success) {
					$row.fadeOut(250, function() { $(this).remove(); });
					if (typeof showToast === 'function') {
						showToast('Publicación movida a la papelera', false);
					}
				} else {
					alert(response.data.message || 'Error al eliminar');
				}
			}
		});
	});

})(jQuery);
