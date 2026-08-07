/**
 * Script de Administración - WP Agency Toolkit
 */

jQuery(document).ready(function($) {
	
	// 1. Control de Pestañas (Tabs)
	$('.wpat-tab-link').on('click', function(e) {
		e.preventDefault();
		
		var targetTab = $(this).data('tab');
		
		// Activar enlace
		$('.wpat-tab-link').removeClass('active');
		$(this).addClass('active');
		
		// Mostrar panel correspondiente
		$('.wpat-tab-panel').removeClass('active');
		$('#' + targetTab).addClass('active');
		
		// Guardar pestaña activa en input hidden y localStorage
		$('#wpat_active_tab_input').val(targetTab);
		localStorage.setItem('wpat_active_tab', targetTab);
	});
	
	// Restaurar pestaña activa guardada en localStorage (evitando dobles clics si PHP ya lo ha renderizado)
	var activeTab = localStorage.getItem('wpat_active_tab');
	var currentActiveInHtml = $('.wpat-tab-link.active').data('tab');
	if (activeTab && $('#' + activeTab).length && activeTab !== currentActiveInHtml) {
		$('.wpat-tab-link[data-tab="' + activeTab + '"]').click();
	}

	// Restaurar kit activo si estaba guardado en sessionStorage
	var activeKitSlug = sessionStorage.getItem('wpat_active_kit_slug');
	if (activeKitSlug) {
		setTimeout(function() {
			var $btn = $('.wpat-view-kit-templates-btn[data-slug="' + activeKitSlug + '"]');
			if ($btn.length) {
				$btn.trigger('click');
			}
		}, 150);
	}
	
	// Helper para mostrar notificaciones flotantes (Toast)
	function showToast(message, type) {
		$('.wpat-toast').remove(); // Evitar duplicados
		var bg = '#10b981'; // Verde por defecto para éxito/activación
		
		if (type === true || type === 'error') {
			bg = '#ea580c'; // Naranja para errores
		} else if (type === 'deactivate') {
			bg = '#ef4444'; // Rojo con la misma tonalidad moderna para desactivación
		}

		var $toast = $('<div class="wpat-toast" style="position: fixed; bottom: 20px; right: 20px; background: ' + bg + '; color: #fff; padding: 12px 20px; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 999999; font-weight: 600; font-size: 13px; display: none;">' + message + '</div>');
		$('body').append($toast);
		$toast.fadeIn(300).delay(2500).fadeOut(300, function() {
			$(this).remove();
		});
	}

	// 2. Mostrar/Ocultar Opciones de Módulo al cambiar el Switch + Auto-guardado AJAX
	$('.wpat-switch input[type="checkbox"]').on('change', function() {
		var $checkbox = $(this);
		var $card = $checkbox.closest('.wpat-module-card');
		var $body = $card.find('.wpat-module-body');
		
		// Animación visual
		if ($body.length) {
			var $collapseBtn = $card.find('.wpat-collapse-btn');
			if ($checkbox.is(':checked')) {
				$body.slideDown(250);
				$collapseBtn.fadeIn(200).removeClass('collapsed');
			} else {
				$body.slideUp(250);
				$collapseBtn.fadeOut(200).addClass('collapsed');
			}
		}

		// Auto-guardar si es un conmutador principal de módulo (dentro de .wpat-module-header)
		if ($checkbox.closest('.wpat-module-header').length) {
			var nameAttr = $checkbox.attr('name');
			var match = nameAttr.match(/wpat_settings\[(.*?)\]/);
			if (match) {
				var moduleId = match[1];
				var state = $checkbox.is(':checked') ? '1' : '0';
				var nonce = $('#wpat_settings_nonce').val();

				// Efecto visual de guardando
				$card.css('opacity', '0.7');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpat_toggle_module',
						security: nonce,
						module_id: moduleId,
						state: state
					},
					success: function(response) {
						$card.css('opacity', '1');
						if (response.success) {
							if (state === '1') {
								showToast('Módulo activado', false);
							} else {
								showToast('Módulo desactivado', 'deactivate');
							}
						} else {
							// Revertir interruptor
							$checkbox.prop('checked', state === '0');
							if (state === '0') { $body.slideDown(250); } else { $body.slideUp(250); }
							showToast('Error al actualizar módulo: ' + (response.data ? response.data.message : 'Error desconocido'), true);
						}
					},
					error: function() {
						$card.css('opacity', '1');
						$checkbox.prop('checked', state === '0');
						if (state === '0') { $body.slideDown(250); } else { $body.slideUp(250); }
						showToast('Error de conexión al guardar el estado.', true);
					}
				});
			}
		}
	});

	// 3. Inicializar Selector de Color (Color Picker)
	if ($.fn.wpColorPicker) {
		$('.wpat-color-picker').wpColorPicker();
	}

	// 4. Cargador de Medios de WordPress para el Logotipo
	var custom_uploader;
	
	$('#wpat_login_logo_btn').on('click', function(e) {
		e.preventDefault();
		
		// Si el uploader ya existe, abrirlo
		if (custom_uploader) {
			custom_uploader.open();
			return;
		}
		
		// Crear el uploader de medios
		custom_uploader = wp.media.frames.file_frame = wp.media({
			title: 'Seleccionar Logotipo para la pantalla de Login',
			button: {
				text: 'Usar este Logo'
			},
			multiple: false
		});
		
		// Acción al seleccionar un archivo
		custom_uploader.on('select', function() {
			var attachment = custom_uploader.state().get('selection').first().toJSON();
			
			// Guardar URL en el input
			$('#wpat_login_logo').val(attachment.url);
			
			// Mostrar vista previa
			var $preview = $('#wpat_login_logo_preview');
			$preview.html('<img src="' + attachment.url + '" alt="Logo de Login" />').show();
			
			// Mostrar botón de eliminar
			$('#wpat_login_logo_remove').show();
		});
		
		// Abrir modal
		custom_uploader.open();
	});
	
	// Eliminar logotipo seleccionado
	$('#wpat_login_logo_remove').on('click', function(e) {
		e.preventDefault();
		
		$('#wpat_login_logo').val('');
		$('#wpat_login_logo_preview').html('').hide();
		$(this).hide();
	});

	// Selector de imagen de fondo de login moderno
	var bg_uploader;
	$('#wpat_login_bg_image_btn').on('click', function(e) {
		e.preventDefault();
		if (bg_uploader) {
			bg_uploader.open();
			return;
		}
		bg_uploader = wp.media.frames.file_frame = wp.media({
			title: 'Seleccionar Imagen de Fondo para el Login',
			button: {
				text: 'Usar esta Imagen'
			},
			multiple: false
		});
		bg_uploader.on('select', function() {
			var attachment = bg_uploader.state().get('selection').first().toJSON();
			$('#wpat_login_bg_image').val(attachment.url);
			var $preview = $('#wpat_login_bg_image_preview');
			$preview.html('<img src="' + attachment.url + '" style="max-width:200px; max-height:100px; display:block; border-radius:4px;" />').show();
			$('#wpat_login_bg_image_remove').show();
		});
		bg_uploader.open();
	});
	
	$('#wpat_login_bg_image_remove').on('click', function(e) {
		e.preventDefault();
		$('#wpat_login_bg_image').val('');
		$('#wpat_login_bg_image_preview').html('').hide();
		$(this).hide();
	});

	// Mostrar/Ocultar campos avanzados según el estilo de login seleccionado
	$('#wpat_login_style').on('change', function() {
		if ($(this).val() === 'modern') {
			$('.wpat-modern-login-subfields').slideDown(250);
		} else {
			$('.wpat-modern-login-subfields').slideUp(250);
		}
	}).trigger('change');

	// Mostrar/Ocultar cargador de imagen de fondo según el tipo de fondo seleccionado
	$('#wpat_login_bg_type').on('change', function() {
		if ($(this).val() === 'image') {
			$('.wpat-login-bg-image-group').slideDown(200);
		} else {
			$('.wpat-login-bg-image-group').slideUp(200);
		}
	}).trigger('change');

	// 5. Ocultar/Mostrar subcampos de límite de intentos de login
	$('#wpat_hide_login_limit_attempts').on('change', function() {
		var $subfields = $(this).closest('.wpat-module-body').find('.wpat-sub-field');
		if ($(this).is(':checked')) {
			$subfields.slideDown(200);
		} else {
			$subfields.slideUp(200);
		}
	});

	// 6. Optimización masiva retroactiva de imágenes por lotes AJAX
	var imagesToOptimizeIds = [];
	var totalImagesToOptimize = 0;
	var optimizedSuccessCount = 0;
	var optimizedFailedCount = 0;
	var currentOptimizeIndex = 0;

	// Acción: Escanear biblioteca
	$('#wpat_scan_images_btn').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		$btn.prop('disabled', true).text('Escaneando...');
		$('#wpat_start_bulk_btn').hide();
		$('#wpat_bulk_status').hide();

		var minSize = $('#wpat_bulk_filter_min_size').val();
		var dateStart = $('#wpat_bulk_filter_date_start').val();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_scan_images',
				min_size: minSize,
				date_start: dateStart
			},
			success: function(response) {
				$btn.prop('disabled', false).text('Escanear Biblioteca');
				
				if (response.success) {
					imagesToOptimizeIds = response.data.ids;
					totalImagesToOptimize = response.data.count;
					optimizedSuccessCount = 0;
					optimizedFailedCount = 0;
					currentOptimizeIndex = 0;

					// Actualizar interfaz
					$('#wpat_stat_pending').text(totalImagesToOptimize);
					$('#wpat_stat_processed').text(0);
					$('#wpat_stat_failed').text(0);
					$('#wpat_bulk_progress_fill').css('width', '0%');
					$('#wpat_bulk_progress_percent').text('0%');
					
					if (totalImagesToOptimize > 0) {
						$('#wpat_stat_total_weight').text(response.data.total_bytes_pref);
						$('#wpat_stat_opt_weight').text(response.data.est_opt_bytes_f);
						$('#wpat_stat_weight_container').show();
					} else {
						$('#wpat_stat_weight_container').hide();
					}

					$('#wpat_bulk_log').html('<div>[Escaneo completado. Encontradas ' + totalImagesToOptimize + ' imágenes que coinciden con los filtros.]</div>');
					$('#wpat_bulk_status').slideDown(200);

					if (totalImagesToOptimize > 0) {
						$('#wpat_start_bulk_btn').show();
					} else {
						$('#wpat_bulk_log').append('<div>[No hay imágenes que requieran optimización con los filtros aplicados.]</div>');
					}
				} else {
					alert('Error al escanear la biblioteca: ' + (response.data ? response.data.message : 'Error desconocido.'));
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Escanear Biblioteca');
				alert('Fallo en la comunicación con el servidor.');
			}
		});
	});

	// Acción: Iniciar Optimización
	$('#wpat_start_bulk_btn').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		$btn.prop('disabled', true).text('Procesando...');
		$('#wpat_scan_images_btn').prop('disabled', true);
		
		$('#wpat_bulk_log').append('<div>[Iniciando proceso de conversión...]</div>');
		
		runOptimizationBatch($btn);
	});

	function runOptimizationBatch($startBtn) {
		if (currentOptimizeIndex >= imagesToOptimizeIds.length) {
			$('#wpat_bulk_log').append('<div style="color:var(--wpat-success); font-weight:bold;">[Proceso completado. ' + optimizedSuccessCount + ' imágenes procesadas con éxito.]</div>');
			$startBtn.hide().prop('disabled', false).text('Iniciar Optimización');
			$('#wpat_scan_images_btn').prop('disabled', false);
			return;
		}

		// Tomar lote de 5 IDs
		var batchIds = imagesToOptimizeIds.slice(currentOptimizeIndex, currentOptimizeIndex + 5);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_optimize_image_batch',
				ids: batchIds
			},
			success: function(response) {
				if (response.success) {
					var data = response.data;
					
					// Acumular contadores e índice
					optimizedSuccessCount += parseInt(data.processed);
					optimizedFailedCount += parseInt(data.failed);
					currentOptimizeIndex += batchIds.length;
					
					// Escribir logs
					if (data.log && data.log.length > 0) {
						data.log.forEach(function(msg) {
							$('#wpat_bulk_log').append('<div>' + msg + '</div>');
						});
					}

					// Desplazar log al final
					var logBox = document.getElementById('wpat_bulk_log');
					if (logBox) {
						logBox.scrollTop = logBox.scrollHeight;
					}

					// Calcular restantes y porcentaje
					var remaining = Math.max(0, totalImagesToOptimize - currentOptimizeIndex);
					var percent = Math.round((currentOptimizeIndex / totalImagesToOptimize) * 100);
					percent = Math.min(100, Math.max(0, percent));

					// Actualizar estadísticas en UI
					$('#wpat_stat_pending').text(remaining);
					$('#wpat_stat_processed').text(optimizedSuccessCount);
					$('#wpat_stat_failed').text(optimizedFailedCount);
					$('#wpat_bulk_progress_fill').css('width', percent + '%');
					$('#wpat_bulk_progress_percent').text(percent + '%');

					// Siguiente lote
					setTimeout(function() {
						runOptimizationBatch($startBtn);
					}, 300);
				} else {
					$('#wpat_bulk_log').append('<div style="color:#ea580c;">[Error en lote: ' + (response.data ? response.data.message : 'Error de respuesta') + ']</div>');
					currentOptimizeIndex += batchIds.length;
					setTimeout(function() {
						runOptimizationBatch($startBtn);
					}, 300);
				}
			},
			error: function() {
				$('#wpat_bulk_log').append('<div style="color:#ea580c;">[Fallo de conexión en este lote. Reintentando...]</div>');
				setTimeout(function() {
					runOptimizationBatch($startBtn);
				}, 1000);
			}
		});
	}

	// --- LIMPIADOR DE IMÁGENES HUÉRFANAS (NO USADAS) ---
	var orphanCandidateIds = [];
	var totalOrphansScanned = 0;
	var orphansFoundCount = 0;
	var currentOrphanIndex = 0;
	var detectedOrphans = [];

	// Escanear huérfanas
	$('#wpat_scan_orphans_btn').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		$btn.prop('disabled', true).text('Buscando...');
		$('#wpat_delete_selected_orphans_btn').hide();
		$('#wpat_orphans_results_wrapper').hide();
		$('#wpat_orphans_table_body').html('');
		$('#wpat_orphans_status').hide();
		
		orphanCandidateIds = [];
		detectedOrphans = [];
		totalOrphansScanned = 0;
		orphansFoundCount = 0;
		currentOrphanIndex = 0;

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_scan_unused_images'
			},
			success: function(response) {
				if (response.success) {
					orphanCandidateIds = response.data.ids;
					totalOrphansScanned = orphanCandidateIds.length;

					if (totalOrphansScanned > 0) {
						$('#wpat_orphans_stat_scanned').text(0);
						$('#wpat_orphans_stat_found').text(0);
						$('#wpat_orphans_progress_fill').css('width', '0%');
						$('#wpat_orphans_progress_percent').text('0%');
						$('#wpat_orphans_status').slideDown(200);
						
						runOrphansBatchScan($btn);
					} else {
						$btn.prop('disabled', false).text('Buscar Imágenes Huérfanas');
						alert('No se encontraron imágenes en la biblioteca para analizar.');
					}
				} else {
					$btn.prop('disabled', false).text('Buscar Imágenes Huérfanas');
					alert('Error al escanear: ' + (response.data ? response.data.message : 'Error desconocido.'));
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Buscar Imágenes Huérfanas');
				alert('Fallo de conexión al escanear la biblioteca.');
			}
		});
	});

	function runOrphansBatchScan($scanBtn) {
		if (currentOrphanIndex >= orphanCandidateIds.length) {
			// Finalizado
			$scanBtn.prop('disabled', false).text('Buscar Imágenes Huérfanas');
			
			if (detectedOrphans.length > 0) {
				// Dibujar resultados
				renderOrphansTable();
				$('#wpat_orphans_results_wrapper').slideDown(200);
				$('#wpat_delete_selected_orphans_btn').show();
			} else {
				alert('¡Enhorabuena! Todas las imágenes de tu biblioteca están en uso.');
			}
			return;
		}

		var batchIds = orphanCandidateIds.slice(currentOrphanIndex, currentOrphanIndex + 20);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_check_unused_images_batch',
				ids: batchIds
			},
			success: function(response) {
				if (response.success) {
					var unusedList = response.data.unused;
					if (unusedList && unusedList.length > 0) {
						detectedOrphans = detectedOrphans.concat(unusedList);
						orphansFoundCount += unusedList.length;
					}

					currentOrphanIndex += batchIds.length;
					
					// Calcular porcentaje
					var percent = Math.round((currentOrphanIndex / totalOrphansScanned) * 100);
					percent = Math.min(100, Math.max(0, percent));

					// Actualizar interfaz
					$('#wpat_orphans_stat_scanned').text(currentOrphanIndex);
					$('#wpat_orphans_stat_found').text(orphansFoundCount);
					$('#wpat_orphans_progress_fill').css('width', percent + '%');
					$('#wpat_orphans_progress_percent').text(percent + '%');

					// Siguiente lote
					setTimeout(function() {
						runOrphansBatchScan($scanBtn);
					}, 150);
				} else {
					currentOrphanIndex += batchIds.length;
					setTimeout(function() {
						runOrphansBatchScan($scanBtn);
					}, 150);
				}
			},
			error: function() {
				// Reintentar en 1s
				setTimeout(function() {
					runOrphansBatchScan($scanBtn);
				}, 1000);
			}
		});
	}

	function renderOrphansTable() {
		var html = '';
		detectedOrphans.forEach(function(img) {
			var thumbHtml = img.url ? '<img src="' + img.url + '" style="width:40px; height:40px; object-fit:cover; border-radius:4px; border:1px solid #ddd;" />' : '<span class="dashicons dashicons-format-image" style="font-size:30px; width:30px; height:30px; color:#ccc;"></span>';
			
			html += '<tr>';
			html += '<td style="text-align:center; padding:10px; vertical-align:middle;"><input type="checkbox" class="wpat-orphan-checkbox" value="' + img.id + '" /></td>';
			html += '<td style="text-align:center; padding:10px; vertical-align:middle;">' + thumbHtml + '</td>';
			html += '<td style="padding:10px; vertical-align:middle;"><strong>' + img.name + '</strong><br/><span style="color:#94a3b8; font-size:11px;">ID: ' + img.id + '</span></td>';
			html += '<td style="padding:10px; vertical-align:middle;">' + img.size + '</td>';
			html += '<td style="padding:10px; vertical-align:middle;">' + img.date + '</td>';
			html += '</tr>';
		});
		$('#wpat_orphans_table_body').html(html);
	}

	// Seleccionar/Deseleccionar todas las huérfanas
	$(document).on('change', '#wpat_select_all_orphans', function() {
		var checked = $(this).is(':checked');
		$('.wpat-orphan-checkbox').prop('checked', checked);
	});

	// Eliminar seleccionadas
	$('#wpat_delete_selected_orphans_btn').on('click', function(e) {
		e.preventDefault();
		var selectedIds = [];
		$('.wpat-orphan-checkbox:checked').each(function() {
			selectedIds.push(parseInt($(this).val()));
		});

		if (selectedIds.length === 0) {
			alert('Por favor, selecciona al menos una imagen para eliminar.');
			return;
		}

		if (!confirm('¿Estás seguro de que deseas eliminar permanentemente las ' + selectedIds.length + ' imágenes seleccionadas de tu servidor y base de datos? Esta acción es irreversible.')) {
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true).text('Eliminando...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_delete_unused_images',
				ids: selectedIds
			},
			success: function(response) {
				$btn.prop('disabled', false).text('Eliminar Seleccionadas');
				if (response.success) {
					showToast(response.data.deleted + ' imágenes eliminadas correctamente.', false);
					
					// Quitar de la lista local
					detectedOrphans = detectedOrphans.filter(function(img) {
						return !selectedIds.includes(img.id);
					});

					// Actualizar contadores
					orphansFoundCount = detectedOrphans.length;
					$('#wpat_orphans_stat_found').text(orphansFoundCount);
					
					// Re-renderizar tabla o cerrar si está vacía
					if (detectedOrphans.length > 0) {
						renderOrphansTable();
						$('#wpat_select_all_orphans').prop('checked', false);
					} else {
						$('#wpat_orphans_results_wrapper').slideUp(200);
						$btn.hide();
					}
				} else {
					alert('Error al eliminar: ' + (response.data ? response.data.message : 'Error desconocido.'));
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Eliminar Seleccionadas');
				alert('Fallo de conexión al eliminar las imágenes.');
			}
		});
	});


	// 7. Ocultar/Mostrar opciones selectivas de comentarios según deshabilitación global
	$('#wpat_disable_comments_global').on('change', function() {
		var $options = $(this).closest('.wpat-module-body').find('.wpat-comments-options');
		if ($(this).is(':checked')) {
			$options.slideUp(200);
		} else {
			$options.slideDown(200);
		}
	});

	// 8. Ocultar/Mostrar opciones de Modo Catálogo según "Desactivar añadir al carrito"
	$('input[name="wpat_settings[woo_catalog_hide_cart]"]').on('change', function() {
		if ($(this).is(':checked')) {
			$('.wpat-catalog-actions-container').slideDown(250);
		} else {
			$('.wpat-catalog-actions-container').slideUp(250);
		}
	});

	$('#wpat_woo_catalog_wa_enable').on('change', function() {
		if ($(this).is(':checked')) {
			$('.wpat-catalog-wa-subfields').slideDown(250);
		} else {
			$('.wpat-catalog-wa-subfields').slideUp(250);
		}
	});

	$('#wpat_woo_catalog_form_enable').on('change', function() {
		if ($(this).is(':checked')) {
			$('.wpat-catalog-form-subfields').slideDown(250);
		} else {
			$('.wpat-catalog-form-subfields').slideUp(250);
		}
	});


	// --- GESTOR DE SNIPPETS (AJAX) ---

	// Importación rápida de fragmentos de código (.json)
	$('#wpat_import_snippets_only_btn').on('click', function(e) {
		e.preventDefault();
		$('#wpat_import_snippets_only_file').trigger('click');
	});

	$('#wpat_import_snippets_only_file').on('change', function() {
		if ($(this).val()) {
			$('#wpat_execute_import_snippets_only_submit').trigger('click');
		}
	});

	// 1. Mostrar formulario de Añadir Nuevo Snippet
	$('#wpat_add_new_snippet_btn').on('click', function(e) {
		e.preventDefault();
		
		// Limpiar campos del editor
		$('#wpat_editor_id').val('');
		$('#wpat_editor_name').val('');
		$('#wpat_editor_type').val('php');
		$('#wpat_editor_code').val('');
		$('#wpat_editor_active').prop('checked', true);
		
		$('.wpat-editor-title').text('Añadir Nuevo Fragmento');
		
		// Cambiar vistas con animación suave
		$('.wpat-snippets-list').hide();
		$('.wpat-snippet-editor').fadeIn(200);
	});

	// 2. Cancelar edición/creación
	$('#wpat_cancel_snippet_btn').on('click', function(e) {
		e.preventDefault();
		$('.wpat-snippet-editor').hide();
		$('.wpat-snippets-list').fadeIn(200);
	});

	// 3. Cargar para editar
	$(document).on('click', '.wpat-snippet-edit-btn, .wpat-snippet-edit-link', function(e) {
		e.preventDefault();
		var $row = $(this).closest('tr');
		
		var id = $row.data('id');
		var name = $row.data('name');
		var type = $row.data('type');
		var active = $row.data('active');
		var code = $row.find('.wpat-snippet-raw-code').text(); // Obtener el código crudo

		// Llenar campos
		$('#wpat_editor_id').val(id);
		$('#wpat_editor_name').val(name);
		$('#wpat_editor_type').val(type);
		$('#wpat_editor_code').val(code);
		$('#wpat_editor_active').prop('checked', active === 1 || active === '1');
		
		$('.wpat-editor-title').text('Editar Fragmento: ' + name);

		// Cambiar vistas
		$('.wpat-snippets-list').hide();
		$('.wpat-snippet-editor').fadeIn(200);
	});

	// 4. Guardar fragmento vía AJAX
	$('#wpat_save_snippet_btn').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		
		var id = $('#wpat_editor_id').val();
		var name = $('#wpat_editor_name').val().trim();
		var type = $('#wpat_editor_type').val();
		var code = $('#wpat_editor_code').val();
		var active = $('#wpat_editor_active').is(':checked') ? '1' : '0';
		var nonce = $('#wpat_snippet_ajax_nonce').val();

		if (name === '') {
			alert('Por favor, introduce el nombre del fragmento.');
			return;
		}

		$btn.prop('disabled', true).text('Guardando...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_save_snippet',
				security: nonce,
				snippet_id: id,
				snippet_name: name,
				snippet_type: type,
				snippet_code: code,
				snippet_active: active
			},
			success: function(response) {
				$btn.prop('disabled', false).text('Guardar Fragmento');
				if (response.success) {
					// Actualizar tabla
					$('#wpat_snippets_table_body').html(response.data.html);
					// Volver al listado
					$('.wpat-snippet-editor').hide();
					$('.wpat-snippets-list').fadeIn(200);
				} else {
					alert('Error al guardar: ' + (response.data ? response.data.message : 'Error desconocido.'));
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Guardar Fragmento');
				alert('Fallo de conexión al guardar el fragmento.');
			}
		});
	});

	// 5. Alternar estado ON/OFF (badge) vía AJAX
	$(document).on('click', '.wpat-snippet-toggle-badge', function(e) {
		e.preventDefault();
		var $badge = $(this);
		var $row = $badge.closest('tr');
		var id = $row.data('id');
		var nonce = $('#wpat_snippet_ajax_nonce').val();

		$badge.text('Cargando...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_toggle_snippet',
				security: nonce,
				snippet_id: id
			},
			success: function(response) {
				if (response.success) {
					$('#wpat_snippets_table_body').html(response.data.html);
				} else {
					$badge.text($row.data('active') === '1' ? 'Activo' : 'Inactivo');
					alert('Error al alternar estado: ' + (response.data ? response.data.message : 'Error desconocido.'));
				}
			},
			error: function() {
				$badge.text($row.data('active') === '1' ? 'Activo' : 'Inactivo');
				alert('Fallo de conexión al cambiar el estado del fragmento.');
			}
		});
	});

	// 6. Clonar fragmento vía AJAX
	$(document).on('click', '.wpat-snippet-clone-btn', function(e) {
		e.preventDefault();
		var $row = $(this).closest('tr');
		var id = $row.data('id');
		var nonce = $('#wpat_snippet_ajax_nonce').val();

		if (!confirm('¿Deseas duplicar este fragmento de código?')) {
			return;
		}

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_clone_snippet',
				security: nonce,
				snippet_id: id
			},
			success: function(response) {
				if (response.success) {
					$('#wpat_snippets_table_body').html(response.data.html);
				} else {
					alert('Error al clonar: ' + (response.data ? response.data.message : 'Error desconocido.'));
				}
			},
			error: function() {
				alert('Fallo de conexión al clonar el fragmento.');
			}
		});
	});

	// 7. Eliminar fragmento vía AJAX
	$(document).on('click', '.wpat-snippet-delete-btn', function(e) {
		e.preventDefault();
		var $row = $(this).closest('tr');
		var id = $row.data('id');
		var nonce = $('#wpat_snippet_ajax_nonce').val();

		if (!confirm('¿Estás seguro de que deseas eliminar este fragmento de código?')) {
			return;
		}

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_delete_snippet',
				security: nonce,
				snippet_id: id
			},
			success: function(response) {
				if (response.success) {
					$('#wpat_snippets_table_body').html(response.data.html);
				} else {
					alert('Error al eliminar: ' + (response.data ? response.data.message : 'Error desconocido.'));
				}
			},
			error: function() {
				alert('Fallo de conexión al eliminar el fragmento.');
			}
		});
	});

	// --- OPTIMIZACIÓN Y LIMPIEZA DE BASE DE DATOS ---

	// Limpiador individual
	$(document).on('click', '.wpat-db-clean-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var type = $btn.data('type');
		var nonce = $('#wpat_cleanup_ajax_nonce').val();

		$btn.prop('disabled', true).text('Limpiando...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_cleanup_database',
				security: nonce,
				cleanup_type: type
			},
			success: function(response) {
				if (response.success) {
					// Actualizar contador a 0
					var $counter = $('.wpat-db-counter[data-type="' + type + '"]');
					$counter.text('0').css('color', '#94a3b8');
					$btn.prop('disabled', true).text('Limpio');
					showToast('Limpieza completada con éxito.', false);
				} else {
					$btn.prop('disabled', false).text('Limpiar');
					alert('Error al limpiar: ' + (response.data ? response.data.message : 'Error desconocido.'));
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Limpiar');
				alert('Fallo de conexión al realizar la limpieza.');
			}
		});
	});

	// Limpiar todo (Optimizar todo)
	$('#wpat_db_clean_all_btn').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var nonce = $('#wpat_cleanup_ajax_nonce').val();

		if (!confirm('¿Estás seguro de que deseas limpiar y optimizar la base de datos por completo? Se vaciarán revisiones, borradores automáticos, papelera, spam y transitorios expirados.')) {
			return;
		}

		$btn.prop('disabled', true).text('Optimizando...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_cleanup_database',
				security: nonce,
				cleanup_type: 'all'
			},
			success: function(response) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools" style="vertical-align: middle; font-size:16px; width:16px; height:16px; margin-right:5px;"></span> Limpiar y Optimizar Todo');
				if (response.success) {
					// Actualizar todos los contadores a 0 y deshabilitar botones
					$('.wpat-db-counter').text('0').css('color', '#94a3b8');
					$('.wpat-db-clean-btn').prop('disabled', true).text('Limpio');
					showToast('Base de datos optimizada y limpia al completo.', false);
				} else {
					alert('Error al optimizar: ' + (response.data ? response.data.message : 'Error desconocido.'));
				}
			},
			error: function() {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools" style="vertical-align: middle; font-size:16px; width:16px; height:16px; margin-right:5px;"></span> Limpiar y Optimizar Todo');
				alert('Fallo de conexión al optimizar la base de datos.');
			}
		});
	});

	// Actualizar datos de Salud y Base de Datos vía AJAX
	$(document).on('click', '#wpat_refresh_health_btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var nonce = $('#wpat_cleanup_ajax_nonce').val();
		var originalHtml = $btn.html();

		// Deshabilitar y mostrar loader con animación de giro wpat-spin
		$btn.prop('disabled', true).html('<span class="dashicons dashicons-update wpat-spin" style="vertical-align: middle; font-size:16px; width:16px; height:16px; margin-right:5px;"></span> Cargando...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_get_health_status',
				security: nonce
			},
			success: function(response) {
				$btn.prop('disabled', false).html(originalHtml);
				if (response.success) {
					$('#wpat_health_content_wrapper').html(response.data.html);
					showToast('Datos de salud actualizados.', false);
				} else {
					alert('Error al actualizar datos: ' + (response.data ? response.data.message : 'Error desconocido.'));
				}
			},
			error: function() {
				$btn.prop('disabled', false).html(originalHtml);
				alert('Fallo de conexión al actualizar datos.');
			}
		});
	});

	// --- IMPORTADOR DE KITS DE PLANTILLAS (ENVATO) ---
	var $dragDropZone = $('#wpat_kit_dragdrop_zone');
	var $fileInput    = $('#wpat_kit_file_input');
	var $selectBtn    = $('#wpat_select_kit_zip_btn');

	if ($dragDropZone.length) {
		// Abrir explorador de archivos al pulsar el botón
		$selectBtn.on('click', function(e) {
			e.preventDefault();
			$fileInput.click();
		});

		// Manejar cambio en el input file
		$fileInput.on('change', function() {
			var files = this.files;
			if (files.length > 0) {
				handleKitZipUpload(files[0]);
			}
		});

		// Eventos Drag & Drop
		$dragDropZone.on('dragover', function(e) {
			e.preventDefault();
			$(this).css('background-color', '#f1f5f9').css('border-color', '#9333ea');
		});

		$dragDropZone.on('dragleave', function(e) {
			e.preventDefault();
			$(this).css('background-color', '#f8fafc').css('border-color', 'var(--wpat-border)');
		});

		$dragDropZone.on('drop', function(e) {
			e.preventDefault();
			$(this).css('background-color', '#f8fafc').css('border-color', 'var(--wpat-border)');
			
			var files = e.originalEvent.dataTransfer.files;
			if (files.length > 0) {
				handleKitZipUpload(files[0]);
			}
		});
	}

	function handleKitZipUpload(file) {
		if (file.type !== 'application/zip' && !file.name.endsWith('.zip')) {
			alert('Por favor, selecciona un archivo comprimido .zip.');
			return;
		}

		var nonce = $('#wpat_envato_importer_nonce').val();
		var formData = new FormData();
		formData.append('action', 'wpat_upload_envato_kit');
		formData.append('security', nonce);
		formData.append('kit_zip', file);

		$('#wpat_kit_upload_progress').show();
		$('#wpat_kit_upload_bar').css('width', '0%');
		$('#wpat_kit_upload_status').text('Subiendo kit: ' + file.name + ' (0%)');
		$selectBtn.prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			contentType: false,
			processData: false,
			xhr: function() {
				var myXhr = $.ajaxSettings.xhr();
				if (myXhr.upload) {
					myXhr.upload.addEventListener('progress', function(e) {
						if (e.lengthComputable) {
							var percent = Math.round((e.loaded / e.total) * 100);
							$('#wpat_kit_upload_bar').css('width', percent + '%');
							$('#wpat_kit_upload_status').text('Subiendo kit: ' + file.name + ' (' + percent + '%)');
						}
					}, false);
				}
				return myXhr;
			},
			success: function(response) {
				$selectBtn.prop('disabled', false);
				if (response.success) {
					var newKit = response.data.kit;
					
					// Asegurar la variable de datos global
					if (typeof wpatEnvatoEditor === 'undefined') {
						wpatEnvatoEditor = { kits: {} };
					}
					if (!wpatEnvatoEditor.kits) {
						wpatEnvatoEditor.kits = {};
					}
					wpatEnvatoEditor.kits[newKit.slug] = newKit;
					
					// Limpiar progreso e input
					$('#wpat_kit_upload_progress').hide();
					$('#wpat_kit_file_input').val('');
					
					// Función auxiliar para renderizar el HTML del card
					function renderKitCardHtml(slug, kit) {
						var thumbHtml = '';
						if (kit.thumbnail) {
							thumbHtml = '<div class="wpat-kit-cover" style="height: 140px; background-image: url(\'' + kit.thumbnail + '\'); background-size: cover; background-position: center; border-bottom: 1px solid var(--wpat-border);"></div>';
						} else {
							thumbHtml = '<div class="wpat-kit-cover" style="height: 140px; background: linear-gradient(135deg, #f1f5f9 0%, #cbd5e1 100%); display: flex; align-items: center; justify-content: center; border-bottom: 1px solid var(--wpat-border);">' +
										'  <span class="dashicons dashicons-download" style="font-size: 48px; width:48px; height:48px; color: #94a3b8;"></span>' +
										'</div>';
						}
						
						var templatesCount = kit.templates ? kit.templates.length : 0;
						var pluginsCount = kit.required_plugins ? kit.required_plugins.length : 0;
						var pluginsBadge = '';
						if (pluginsCount > 0) {
							pluginsBadge = '<span class="wpat-badge" style="background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">' + pluginsCount + ' plugins necesarios</span>';
						} else {
							pluginsBadge = '<span class="wpat-badge" style="background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">0 plugins necesarios</span>';
						}

						var html = '<div class="wpat-module-card wpat-kit-card" style="margin: 0; padding: 0; overflow:hidden; display: flex; flex-direction: column; border: 1px solid var(--wpat-border);" data-slug="' + slug + '">' +
								   thumbHtml +
								   '  <div style="padding: 20px; display: flex; flex-direction: column; flex-grow: 1;">' +
								   '    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 12px;">' +
								   '      <h4 style="margin: 0; font-size: 15px; font-weight: 600; color: var(--wpat-text);">' + kit.title + '</h4>' +
								   '      <button type="button" class="wpat-delete-kit-btn button-link-delete" data-slug="' + slug + '" title="Eliminar Kit Completo" style="border:none; background:none; cursor:pointer; padding:0; color:#ea580c;"><span class="dashicons dashicons-trash" style="font-size:18px;"></span></button>' +
								   '    </div>' +
								   '    <div style="display: flex; gap: 5px; margin-bottom: 15px; flex-wrap: wrap;">' +
								   '      <span class="wpat-badge" style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">' + templatesCount + ' plantillas</span>' +
								   pluginsBadge +
								   '    </div>' +
								   '    <button type="button" class="button wpat-view-kit-templates-btn" data-slug="' + slug + '" style="width: 100%; margin-top: auto;">Ver Plantillas</button>' +
								   '  </div>' +
								   '</div>';
						return html;
					}

					var cardHtml = renderKitCardHtml(newKit.slug, newKit);
					var $container = $('#wpat_installed_kits_container');
					var $emptyState = $container.find('.wpat-empty-kits');
					
					if ($emptyState.length > 0) {
						$emptyState.remove();
						$container.html('<div class="wpat-kits-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;"></div>');
					}
					
					var $grid = $container.find('.wpat-kits-grid');
					if ($grid.length > 0) {
						var $newCard = $(cardHtml).hide();
						$grid.append($newCard);
						$newCard.fadeIn(300);
					}
					
					showToast('Kit subido y descomprimido correctamente.', false);
				} else {
					$('#wpat_kit_upload_progress').hide();
					alert('Error al subir el kit: ' + (response.data ? response.data.message : 'Error desconocido.'));
				}
			},
			error: function(xhr, status, errorThrown) {
				$selectBtn.prop('disabled', false);
				$('#wpat_kit_upload_progress').hide();
				
				var errorMsg = 'Fallo al subir el kit de plantilla.\n\n';
				
				if (xhr.status === 413) {
					errorMsg += 'El servidor rechazó el archivo porque es demasiado grande (Error 413: Payload Too Large).\n\nSolución: Solicita a tu hosting aumentar los límites "upload_max_filesize" y "post_max_size" en el archivo php.ini de tu servidor.';
				} else if (xhr.status === 504 || status === 'timeout') {
					errorMsg += 'Se agotó el tiempo de espera del servidor (Error 504 / Timeout).\n\nSolución: El servidor tardó demasiado en cargar o descomprimir el kit. Incrementa "max_execution_time" en tu configuración de PHP.';
				} else if (xhr.status === 500) {
					errorMsg += 'Error interno del servidor (Error 500).\n\nSolución: Esto suele deberse a un límite de memoria PHP excedido (Memory Limit) o un error de código crítico al descomprimir. Revisa el registro de errores de PHP (error_log) en tu hosting.';
				} else if (status === 'parsererror') {
					errorMsg += 'Respuesta del servidor corrupta (Error de análisis JSON).\n\nSolución: Esto ocurre si WordPress tiene activada la visualización de avisos o advertencias de PHP (PHP Notices/Warnings) en pantalla. Puedes ver la respuesta cruda en la consola del navegador (F12).';
				} else if (xhr.status === 0) {
					errorMsg += 'No se pudo conectar con el servidor. Revisa tu conexión a Internet o comprueba si la subida fue bloqueada por un plugin de seguridad o cortafuegos.';
				} else {
					errorMsg += 'Detalle del error: HTTP ' + xhr.status + ' (' + (errorThrown || 'Error de Red') + ').';
				}
				
				alert(errorMsg);
			}
		});
	}

	// Acción: Ver las plantillas de un kit
	$(document).on('click', '.wpat-view-kit-templates-btn', function(e) {
		e.preventDefault();
		var slug = $(this).data('slug');
		
		// Guardar en sessionStorage para restaurar después de recargar
		sessionStorage.setItem('wpat_active_kit_slug', slug);
		
		// Ocultar cuadrícula de kits
		$('#wpat_installed_kits_container').hide();
		
		// Obtener datos del kit desde los datos encolados en PHP
		var kits = wpatEnvatoEditor.kits || {};
		var kit = kits[slug];

		if (kit) {
			console.log("WPAT Debug: Kit loaded info:", kit);
			$('#wpat_active_kit_title').text('Plantillas del Kit: ' + kit.title);
			
			// Renderizar plugins requeridos si los hay
			var pluginsHtml = '';
			if (kit.required_plugins && kit.required_plugins.length > 0) {
				pluginsHtml += '<div class="wpat-module-card" style="margin: 0 0 25px 0; padding: 20px; background: #fafafa; border: 1px dashed var(--wpat-border);">';
				pluginsHtml += '  <h4 style="margin: 0 0 10px 0; font-size:14px; font-weight:600;"><span class="dashicons dashicons-admin-plugins" style="vertical-align: middle; margin-right:5px; font-size:18px; width:18px; height:18px;"></span> Plugins Requeridos por el Kit</h4>';
				pluginsHtml += '  <p style="margin:0 0 15px 0; font-size:12px; color:#64748b;">Para garantizar que las plantillas de este kit funcionen y se vean correctamente, es necesario tener instalados y activos los siguientes plugins:</p>';
				pluginsHtml += '  <ul style="margin:0; padding:0; list-style:none;">';
				
				kit.required_plugins.forEach(function(p) {
					var badge = '';
					var button = '';
					
					if (p.is_plugin === false) {
						// Es un ajuste de configuración de Elementor
						if (p.active) {
							badge = '<span style="background:#d1fae5; color:#065f46; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; margin-left:10px;">Aplicado</span>';
						} else {
							badge = '<span style="background:#fee2e2; color:#991b1b; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; margin-left:10px;">No configurado</span>';
							button = '<button type="button" class="button button-small button-primary wpat-configure-setting-btn" data-slug="' + p.slug + '" style="margin-left:auto;">Configurar</button>';
						}
					} else {
						// Es un plugin
						if (p.active) {
							badge = '<span style="background:#d1fae5; color:#065f46; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; margin-left:10px;">Activo</span>';
						} else if (p.installed) {
							badge = '<span style="background:#fef3c7; color:#92400e; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; margin-left:10px;">Desactivado</span>';
							button = '<button type="button" class="button button-small wpat-activate-plugin-btn" data-slug="' + p.slug + '" style="margin-left:auto;">Activar</button>';
						} else {
							badge = '<span style="background:#fee2e2; color:#991b1b; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; margin-left:10px;">No instalado</span>';
							button = '<button type="button" class="button button-small button-primary wpat-install-plugin-btn" data-slug="' + p.slug + '" style="margin-left:auto;">Instalar y Activar</button>';
						}
					}
					
					pluginsHtml += '    <li style="display:flex; align-items:center; padding:8px 0; border-bottom:1px solid #f1f5f9; font-size:13px; margin:0;">';
					pluginsHtml += '      <strong>' + p.name + '</strong>' + badge;
					pluginsHtml += button;
					pluginsHtml += '    </li>';
				});
				
				pluginsHtml += '  </ul>';
				pluginsHtml += '</div>';
				
				$('#wpat_kit_plugins_container').html(pluginsHtml).show();
			} else {
				$('#wpat_kit_plugins_container').hide();
			}

			var gridHtml = '';
			kit.templates.forEach(function(tpl) {
				var thumb = tpl.thumbnail ? tpl.thumbnail : 'https://placehold.co/400x300/f1f5f9/94a3b8?text=Plantilla';
				var previewUrl = tpl.preview_url ? tpl.preview_url : thumb;
				var isGlobal = tpl.title.toLowerCase().indexOf('global') !== -1;
				
				gridHtml += '<div class="wpat-module-card wpat-tpl-card-admin" style="margin:0; padding:15px; display:flex; flex-direction:column; border: 1px solid var(--wpat-border);">';
				gridHtml += '  <div class="wpat-tpl-thumb" style="height:140px; background-size:cover; background-position:center; background-image:url(\'' + thumb + '\'); border-radius:6px; border:1px solid #e2e8f0; margin-bottom:12px; background-color:#f8fafc; background-repeat:no-repeat;"></div>';
				gridHtml += '  <h4 style="margin:0 0 4px 0; font-size:14px; font-weight:600;">' + tpl.title + '</h4>';
				gridHtml += '  <span style="font-size:10px; font-weight:700; color:#64748b; background:#f1f5f9; padding:2px 6px; border-radius:4px; align-self:flex-start; margin-bottom:12px;">' + tpl.type.toUpperCase() + '</span>';
				gridHtml += '  <div style="display:flex; gap:8px; margin-top:auto; width:100%;">';
				if (!isGlobal) {
					gridHtml += '    <a href="' + previewUrl + '" class="button wpat-preview-template-btn" data-kit="' + slug + '" data-id="' + tpl.id + '" data-title="' + tpl.title + '" data-thumb="' + thumb + '" style="flex:1; text-align:center; display:inline-flex; align-items:center; justify-content:center; gap:4px; font-size:12px; font-weight:500;"><span class="dashicons dashicons-visibility" style="font-size:15px; width:15px; height:15px; margin:0; line-height:1;"></span> Ver</a>';
				}
				gridHtml += '    <button type="button" class="button button-primary wpat-admin-import-template-btn" data-kit="' + slug + '" data-id="' + tpl.id + '" style="' + (isGlobal ? 'width:100%;' : 'flex:2;') + ' font-size:12px;">' + (isGlobal ? 'Aplicar Estilos Globales' : 'Importar a Elementor') + '</button>';
				gridHtml += '  </div>';
				gridHtml += '</div>';
			});

			$('#wpat_templates_grid_container').html(gridHtml);
			$('#wpat_kit_templates_detail_wrapper').fadeIn(200);
		}
	});

	// Cerrar detalle y volver al listado de kits
	$('#wpat_close_kit_detail_btn').on('click', function(e) {
		e.preventDefault();
		sessionStorage.removeItem('wpat_active_kit_slug');
		$('#wpat_kit_templates_detail_wrapper').hide();
		$('#wpat_installed_kits_container').fadeIn(200);
	});

	// Acción: Importar una plantilla a la biblioteca de Elementor desde el admin
	$(document).on('click', '.wpat-admin-import-template-btn', function(e) {
		e.preventDefault();
		
		// Cerrar el lightbox de vista previa si estuviera abierto
		$('.wpat-preview-lightbox-overlay').remove();
		
		var $btn = $(this);
		var kitSlug = $btn.data('kit');
		var tplId   = $btn.data('id');
		var nonce   = $('#wpat_envato_importer_nonce').val();

		$btn.prop('disabled', true).text('Importando...');

		var progress = 0;
		var progressHtml = 
			'<div class="wpat-editor-modal-loader-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; z-index:99999; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Oxygen,Ubuntu,Cantarell,Fira Sans,Droid Sans,Helvetica Neue,sans-serif;">' +
			'  <div class="wpat-editor-modal-loader-card" style="background:#fff; padding:30px; border-radius:12px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); text-align:center; width:350px; border: 1px solid #e2e8f0;">' +
			'    <h4 style="margin:0 0 15px 0; font-size:16px; font-weight:600; color:#1e293b;">Importando plantilla a Elementor...</h4>' +
			'    <div class="wpat-import-progress-container" style="background:#f1f5f9; border-radius:9999px; height:8px; width:100%; overflow:hidden; margin-bottom:12px; border:1px solid #e2e8f0;">' +
			'      <div class="wpat-import-progress-bar" style="width: 0%; height:100%; background:linear-gradient(90deg, #6366f1 0%, #4f46e5 100%); transition: width 0.3s ease; border-radius:9999px;"></div>' +
			'    </div>' +
			'    <p class="wpat-import-progress-text" style="margin:0; font-size:12px; color:#64748b; font-weight:500;">0% completado (descargando imágenes...)</p>' +
			'  </div>' +
			'</div>';
		$('body').append(progressHtml);

		var progressInterval = setInterval(function() {
			if (progress < 90) {
				if (progress < 40) {
					progress += Math.floor(Math.random() * 8) + 3;
				} else if (progress < 70) {
					progress += Math.floor(Math.random() * 4) + 1;
				} else {
					progress += Math.floor(Math.random() * 2) + 0.5;
				}
				if (progress > 90) progress = 90;
				
				$('.wpat-import-progress-bar').css('width', progress + '%');
				$('.wpat-import-progress-text').text(Math.round(progress) + '% completado (descargando imágenes...)');
			}
		}, 300);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_import_envato_template',
				security: nonce,
				kit_slug: kitSlug,
				template_id: tplId
			},
			success: function(response) {
				clearInterval(progressInterval);
				$('.wpat-import-progress-bar').css('width', '100%');
				$('.wpat-import-progress-text').text('100% completado');
				
				setTimeout(function() {
					$('.wpat-editor-modal-loader-overlay').remove();
					$btn.prop('disabled', false).text('Importar a Elementor');
					if (response.success) {
						showToast('Plantilla registrada en Elementor.', false);
						$btn.removeClass('button-primary').addClass('button-secondary').text('¡Importada!');
					} else {
						alert('Error al importar: ' + (response.data ? response.data.message : 'Error desconocido.'));
					}
				}, 600);
			},
			error: function() {
				clearInterval(progressInterval);
				$('.wpat-editor-modal-loader-overlay').remove();
				$btn.prop('disabled', false).text('Importar a Elementor');
				alert('Fallo de conexión al importar la plantilla.');
			}
		});
	});

	// Acción: Eliminar un kit completo
	$(document).on('click', '.wpat-delete-kit-btn', function(e) {
		e.preventDefault();
		e.stopPropagation();
		var $btn = $(this);
		var slug = $btn.data('slug');
		var nonce = $('#wpat_envato_importer_nonce').val();

		if (!confirm('¿Estás seguro de que deseas eliminar este kit de plantillas del servidor? Esta acción eliminará permanentemente la carpeta de archivos.')) {
			return;
		}

		$btn.prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_delete_envato_kit',
				security: nonce,
				kit_slug: slug
			},
			success: function(response) {
				if (response.success) {
					showToast('Kit eliminado correctamente.', false);
					
					// Eliminar de los datos en memoria
					if (typeof wpatEnvatoEditor !== 'undefined' && wpatEnvatoEditor.kits) {
						delete wpatEnvatoEditor.kits[slug];
					}
					
					$btn.closest('.wpat-kit-card').fadeOut(200, function() {
						$(this).remove();
						if ($('.wpat-kit-card').length === 0) {
							var $container = $('#wpat_installed_kits_container');
							$container.html(
								'<div class="wpat-empty-kits" style="background:#ffffff; border: 1px solid var(--wpat-border); padding: 40px; border-radius: 8px; text-align: center; color: #64748b;">' +
								'  <p style="margin: 0; font-size:14px;">No hay ningún kit de plantilla instalado.</p>' +
								'  <p style="margin: 5px 0 0 0; font-size:12px; color: #94a3b8;">Usa el cargador de arriba para subir tu primer kit comprimido en ZIP.</p>' +
								'</div>'
							);
						}
					});
				} else {
					$btn.prop('disabled', false);
					alert('Error al eliminar kit: ' + (response.data ? response.data.message : 'Error desconocido.'));
				}
			},
			error: function() {
				$btn.prop('disabled', false);
				alert('Fallo de conexión al eliminar el kit.');
			}
		});
	});

	// 9. Colapsar/Desplegar cuerpo del módulo de forma independiente
	$(document).on('click', '.wpat-collapse-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var $card = $btn.closest('.wpat-module-card');
		var $body = $card.find('.wpat-module-body');
		
		if ($body.length) {
			$body.slideToggle(200);
			$btn.toggleClass('collapsed');
		}
	});

	// 10. Mostrar/Ocultar campos de autenticación SMTP
	$(document).on('change', '#wpat_smtp_auth', function() {
		var $fields = $('.wpat-smtp-auth-fields');
		if ($(this).is(':checked')) {
			$fields.slideDown(250);
		} else {
			$fields.slideUp(250);
		}
	});

	// 11. Acción: Enviar correo de prueba SMTP
	$(document).on('click', '#wpat_smtp_send_test_btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var $input = $('#wpat_smtp_test_email');
		var $result = $('#wpat_smtp_test_result');
		var email = $input.val();
		var nonce = $('#wpat_smtp_test_nonce').val();

		if (!email) {
			alert('Introduce un correo electrónico para realizar la prueba.');
			return;
		}

		$btn.prop('disabled', true).text('Enviando...');
		$result.hide().html('');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_smtp_send_test',
				security: nonce,
				test_email: email
			},
			success: function(response) {
				$btn.prop('disabled', false).text('Enviar Prueba');
				$result.fadeIn(200);
				if (response.success) {
					$result.css({
						'background': '#e6f4ea',
						'color': '#137333',
						'border': '1px solid #c3e6cb'
					}).html(response.data.message);
				} else {
					var html = '<strong>' + response.data.message + '</strong>';
					if (response.data.debug) {
						html += '<div style="margin-top: 10px; border-top: 1px solid #f5c6cb; padding-top: 10px; font-size: 11px; text-align: left; max-height: 200px; overflow-y: auto; white-space: pre-wrap; font-family: monospace; line-height: 1.4;">' + response.data.debug + '</div>';
					}
					$result.css({
						'background': '#fce8e6',
						'color': '#c5221f',
						'border': '1px solid #f5c6cb'
					}).html(html);
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Enviar Prueba');
				$result.fadeIn(200).css({
					'background': '#fce8e6',
					'color': '#c5221f',
					'border': '1px solid #f5c6cb'
				}).html('Error de conexión o tiempo de espera agotado al conectar con el servidor.');
			}
		});
	});

	// 12. Auto-completar el puerto recomendable según el cifrado SMTP elegido
	$(document).on('change', '#wpat_smtp_secure', function() {
		var secureVal = $(this).val();
		var $portInput = $('#wpat_smtp_port');
		
		if (secureVal === 'ssl') {
			$portInput.val('465');
		} else if (secureVal === 'tls') {
			$portInput.val('587');
		} else if (secureVal === 'none') {
			$portInput.val('25');
		}
	});

	// 13. Acción: Activar un plugin requerido
	$(document).on('click', '.wpat-activate-plugin-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var slug = $btn.data('slug');
		var nonce = $('#wpat_envato_importer_nonce').val();
		
		$btn.prop('disabled', true).text('Activando...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_activate_required_plugin',
				security: nonce,
				plugin_slug: slug
			},
			success: function(response) {
				if (response.success) {
					showToast('Plugin activado con éxito.', false);
					
					// Actualizar estado en memoria
					var activeKitSlug = sessionStorage.getItem('wpat_active_kit_slug');
					if (activeKitSlug && typeof wpatEnvatoEditor !== 'undefined' && wpatEnvatoEditor.kits && wpatEnvatoEditor.kits[activeKitSlug]) {
						var plugins = wpatEnvatoEditor.kits[activeKitSlug].required_plugins || [];
						plugins.forEach(function(p) {
							if (p.slug === slug) {
								p.active = true;
								p.installed = true;
							}
						});
					}
					
					// Actualizar la interfaz (DOM)
					var $li = $btn.closest('li');
					$li.find('span.wpat-badge, span[style*="background"]').replaceWith(
						'<span style="background:#d1fae5; color:#065f46; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; margin-left:10px;">Activo</span>'
					);
					$btn.fadeOut(200, function() { $(this).remove(); });
				} else {
					$btn.prop('disabled', false).text('Activar');
					alert('Error al activar plugin: ' + response.data.message);
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Activar');
				alert('Fallo de conexión al activar el plugin.');
			}
		});
	});
 
	// 14. Acción: Instalar y Activar un plugin requerido
	$(document).on('click', '.wpat-install-plugin-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var slug = $btn.data('slug');
		var nonce = $('#wpat_envato_importer_nonce').val();
		
		$btn.prop('disabled', true).text('Instalando...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_install_required_plugin',
				security: nonce,
				plugin_slug: slug
			},
			success: function(response) {
				if (response.success) {
					showToast('Plugin instalado y activado con éxito.', false);
					
					// Actualizar estado en memoria
					var activeKitSlug = sessionStorage.getItem('wpat_active_kit_slug');
					if (activeKitSlug && typeof wpatEnvatoEditor !== 'undefined' && wpatEnvatoEditor.kits && wpatEnvatoEditor.kits[activeKitSlug]) {
						var plugins = wpatEnvatoEditor.kits[activeKitSlug].required_plugins || [];
						plugins.forEach(function(p) {
							if (p.slug === slug) {
								p.active = true;
								p.installed = true;
							}
						});
					}
					
					// Actualizar la interfaz (DOM)
					var $li = $btn.closest('li');
					$li.find('span.wpat-badge, span[style*="background"]').replaceWith(
						'<span style="background:#d1fae5; color:#065f46; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; margin-left:10px;">Activo</span>'
					);
					$btn.fadeOut(200, function() { $(this).remove(); });
				} else {
					$btn.prop('disabled', false).text('Instalar y Activar');
					alert('Error al instalar plugin: ' + response.data.message);
				}
			},
			error: function(xhr, status, error) {
				console.error("WPAT Plugin Install AJAX Error details:", status, error, xhr.responseText);
				$btn.prop('disabled', false).text('Instalar y Activar');
				alert('Fallo de conexión al instalar el plugin. Revisa la consola (F12) para más detalles.');
			}
		});
	});
 
	// 15. Acción: Configurar un ajuste de Elementor
	$(document).on('click', '.wpat-configure-setting-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var slug = $btn.data('slug');
		var nonce = $('#wpat_envato_importer_nonce').val();
		
		$btn.prop('disabled', true).text('Configurando...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_enable_elementor_setting',
				security: nonce,
				setting_slug: slug
			},
			success: function(response) {
				if (response.success) {
					showToast('Ajuste aplicado con éxito.', false);
					
					// Actualizar estado en memoria
					var activeKitSlug = sessionStorage.getItem('wpat_active_kit_slug');
					if (activeKitSlug && typeof wpatEnvatoEditor !== 'undefined' && wpatEnvatoEditor.kits && wpatEnvatoEditor.kits[activeKitSlug]) {
						var plugins = wpatEnvatoEditor.kits[activeKitSlug].required_plugins || [];
						plugins.forEach(function(p) {
							if (p.slug === slug) {
								p.active = true;
							}
						});
					}
					
					// Actualizar la interfaz (DOM)
					var $li = $btn.closest('li');
					$li.find('span.wpat-badge, span[style*="background"]').replaceWith(
						'<span style="background:#d1fae5; color:#065f46; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; margin-left:10px;">Aplicado</span>'
					);
					$btn.fadeOut(200, function() { $(this).remove(); });
				} else {
					$btn.prop('disabled', false).text('Configurar');
					alert('Error al aplicar ajuste: ' + response.data.message);
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Configurar');
				alert('Fallo de conexión al aplicar el ajuste.');
			}
		});
	});

	// Evento: Abrir vista previa en lightbox (modal de imagen vertical scrollable de 700px)
	$(document).on('click', '.wpat-preview-template-btn', function(e) {
		e.preventDefault();
		var $previewBtn = $(this);
		var previewUrl = $previewBtn.attr('href');
		var thumbUrl = $previewBtn.data('thumb') || '';
		var kitSlug = $previewBtn.data('kit') || '';
		var tplId = $previewBtn.data('id') || '';
		var tplTitle = $previewBtn.data('title') || 'Plantilla';
		
		if (!previewUrl || previewUrl.indexOf('placehold.co') !== -1) {
			alert('No hay una vista previa disponible para esta plantilla.');
			return;
		}
		
		// Botón de acción: Importar plantilla
		var actionButtonHtml = 
			'<button type="button" class="wpat-admin-import-template-btn" data-kit="' + kitSlug + '" data-id="' + tplId + '" style="background:#5cb85c; border:none; color:#fff; font-size:12px; font-weight:700; cursor:pointer; padding:6px 16px; border-radius:4px; outline:none; display:inline-flex; align-items:center; gap:6px; transition:background 0.2s; height:32px; line-height:1.2; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">' +
			'  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Importar Plantilla' +
			'</button>';
		
		// Crear el lightbox modal centrado (ancho máximo 700px, cabecera oscura y cuerpo scrollable con imagen de captura al 100%)
		var lightboxHtml = 
			'<div class="wpat-preview-lightbox-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); display:flex; align-items:center; justify-content:center; z-index:999999; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">' +
			'  <div class="wpat-preview-lightbox-container" style="background:#fff; width:95%; max-width:700px; height:90%; border-radius:8px; overflow:hidden; position:relative; display:flex; flex-direction:column; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">' +
			'    <div class="wpat-preview-lightbox-header" style="padding:10px 20px; border-bottom:1px solid #1a1a1a; display:flex; justify-content:space-between; align-items:center; background:#222; height:55px; box-sizing:border-box;">' +
			'      <span style="font-weight:600; color:#fff; font-size:14px; text-transform:capitalize;">' + tplTitle + '</span>' +
			'      <div style="display:flex; align-items:center; gap:15px;">' +
			         actionButtonHtml +
			'        <button type="button" class="wpat-close-lightbox-btn" onmouseover="this.style.color=\'#fff\'" onmouseout="this.style.color=\'#8a8a8a\'" style="background:none; border:none; color:#8a8a8a; font-size:24px; cursor:pointer; line-height:1; padding:0; margin:0 0 2px 0; outline:none; transition:color 0.2s;">&times;</button>' +
			'      </div>' +
			'    </div>' +
			'    <div class="wpat-preview-lightbox-body" style="flex:1; background:#eaeaea; position:relative; overflow-y:auto; overflow-x:hidden;">' +
			'      <img src="' + thumbUrl + '" style="width:100%; height:auto; display:block; margin:0;" />' +
			'    </div>' +
			'  </div>' +
			'</div>';
		
		$('body').append(lightboxHtml);
	});

	// Cerrar lightbox al hacer clic en cerrar o fuera del modal
	$(document).on('click', '.wpat-close-lightbox-btn, .wpat-preview-lightbox-overlay', function(e) {
		if (e.target === this || $(this).hasClass('wpat-close-lightbox-btn')) {
			$('.wpat-preview-lightbox-overlay').remove();
		}
	});

	// --- ACCIONES AJAX DEL BOT BLOCKER ---
	$(document).on('click', '.wpat-unblock-ip-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var ip = $btn.data('ip');
		var nonce = $('#wpat_bot_blocker_ajax_nonce').val();
		var $row = $btn.closest('tr');

		if (!confirm('¿Estás seguro de que deseas desbloquear la IP ' + ip + '?')) {
			return;
		}

		$btn.prop('disabled', true).text('Procesando...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_unblock_ip',
				security: nonce,
				ip: ip
			},
			success: function(response) {
				if (response.success) {
					$row.fadeOut(300, function() {
						$row.remove();
						var $tbody = $('.wpat-blocked-ips-table tbody');
						if ($tbody.find('tr').length === 0) {
							$tbody.append('<tr class="no-blocked-ips-row"><td colspan="5" style="text-align: center; color: #94a3b8; padding: 25px 0;">No hay direcciones IPs bloqueadas en este momento.</td></tr>');
						}
					});
					showToast('IP desbloqueada correctamente.', false);
				} else {
					$btn.prop('disabled', false).text('Desbloquear');
					alert('Error: ' + (response.data ? response.data.message : 'Error desconocido.'));
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Desbloquear');
				alert('Fallo de conexión al desbloquear la IP.');
			}
		});
	});

	$(document).on('click', '.wpat-whitelist-ip-btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var ip = $btn.data('ip');
		var nonce = $('#wpat_bot_blocker_ajax_nonce').val();
		var $row = $btn.closest('tr');

		if (!confirm('¿Estás seguro de que deseas desbloquear y añadir la IP ' + ip + ' a la lista blanca?')) {
			return;
		}

		$btn.prop('disabled', true).text('Procesando...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_whitelist_ip',
				security: nonce,
				ip: ip
			},
			success: function(response) {
				if (response.success) {
					$row.fadeOut(300, function() {
						$row.remove();
						var $tbody = $('.wpat-blocked-ips-table tbody');
						if ($tbody.find('tr').length === 0) {
							$tbody.append('<tr class="no-blocked-ips-row"><td colspan="5" style="text-align: center; color: #94a3b8; padding: 25px 0;">No hay direcciones IPs bloqueadas en este momento.</td></tr>');
						}
					});
					// Actualizar textarea de lista blanca
					$('#wpat_bot_blocker_whitelist').val(response.data.whitelist);
					showToast('IP añadida a la lista blanca.', false);
				} else {
					$btn.prop('disabled', false).text('Lista Blanca');
					alert('Error: ' + (response.data ? response.data.message : 'Error desconocido.'));
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Lista Blanca');
				alert('Fallo de conexión al añadir a la lista blanca.');
			}
		});
	});

	// --- ASISTENTE DE CONFIGURACIÓN INICIAL ---
	// Marcar/Desmarcar sub-páginas de forma coordinada
	$('#wpat_init_create_pages').on('change', function() {
		var isChecked = $(this).is(':checked');
		$('.wpat-init-page-checkbox').prop('checked', isChecked);
	});

	// Si se desmarca cualquier sub-página individual, desmarcar el padre
	$('.wpat-init-page-checkbox').on('change', function() {
		var totalCheckboxes = $('.wpat-init-page-checkbox').length;
		var checkedCheckboxes = $('.wpat-init-page-checkbox:checked').length;
		$('#wpat_init_create_pages').prop('checked', checkedCheckboxes > 0);
	});

	// Confirmación antes de ejecutar
	$('#wpat_run_initial_setup_btn').on('click', function(e) {
		var anyChecked = $('.wpat-init-action-checkbox:checked').length > 0;
		if (!anyChecked) {
			alert('Por favor, selecciona al menos una acción para ejecutar.');
			e.preventDefault();
			return;
		}
		
		var confirmed = confirm('¿Estás seguro de que deseas ejecutar la configuración inicial seleccionada?\n\nEsta acción descargará temas/plugins y modificará contenidos y ajustes del sitio.');
		if (!confirmed) {
			e.preventDefault();
		}
	});

	// 5. Módulo SEO: Pestañas, Previsualización y Análisis SEO/Legibilidad Reactivo
	
	// Selector de Pestañas Yoast Style
	$(document).on('click', '.wpat-seo-nav-tab', function(e) {
		e.preventDefault();
		$('.wpat-seo-nav-tab').removeClass('active').css({
			'background': 'none',
			'border-color': 'transparent',
			'color': '#64748b'
		});
		$(this).addClass('active').css({
			'background': '#fff',
			'border-color': '#cbd5e1',
			'color': '#1e293b'
		});
		$('.wpat-seo-tab-content').hide();
		var targetTab = $(this).data('wpat-tab');
		$('#' + targetTab).show();
	});

	// Cambiar modo de previsualización (móvil vs escritorio radio buttons)
	$(document).on('change', 'input[name="wpat_seo_preview_mode"]', function() {
		var mode = $(this).val();
		if (mode === 'mobile') {
			$('#wpat-seo-google-preview-mobile').show();
			$('#wpat-seo-google-preview-desktop').hide();
		} else {
			$('#wpat-seo-google-preview-mobile').hide();
			$('#wpat-seo-google-preview-desktop').show();
		}
	});

	// Cargador de medios para Open Graph
	$(document).on('click', '#wpat_seo_og_image_btn', function(e) {
		e.preventDefault();
		var custom_og_uploader = wp.media({
			title: 'Seleccionar Imagen para Redes Sociales',
			button: { text: 'Usar esta Imagen' },
			multiple: false
		}).on('select', function() {
			var attachment = custom_og_uploader.state().get('selection').first().toJSON();
			$('#wpat_seo_og_image_input').val(attachment.url);
		}).open();
	});

	// Barras de progreso de caracteres
	function updateSeoTitleBar(len) {
		var $bar = $('#wpat_seo_title_progress_bar');
		if (!$bar.length) return;
		var percent = Math.min((len / 60) * 100, 100);
		$bar.css('width', percent + '%');
		if (len === 0) {
			$bar.css('background', '#e2e8f0');
		} else if (len >= 50 && len <= 60) {
			$bar.css('background', '#10b981'); // Verde
		} else if (len > 60) {
			$bar.css('background', '#ef4444'); // Rojo (Largo)
		} else {
			$bar.css('background', '#eab308'); // Amarillo (Corto)
		}
	}

	function updateSeoDescBar(len) {
		var $bar = $('#wpat_seo_desc_progress_bar');
		if (!$bar.length) return;
		var percent = Math.min((len / 160) * 100, 100);
		$bar.css('width', percent + '%');
		if (len === 0) {
			$bar.css('background', '#e2e8f0');
		} else if (len >= 120 && len <= 160) {
			$bar.css('background', '#10b981'); // Verde
		} else if (len > 160) {
			$bar.css('background', '#ef4444'); // Rojo (Largo)
		} else {
			$bar.css('background', '#eab308'); // Amarillo (Corto)
		}
	}

	// Helper para eliminar acentos de un string (insensibilidad a tildes/acentos)
	function removeAccents(str) {
		if (!str) return "";
		return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
	}

	// Análisis SEO y Legibilidad interactivo en tiempo real (Estilo Yoast con soporte para múltiples frases clave)
	function runSeoAndReadabilityAnalysis() {
		if (!$('#wpat_seo_keyword_input').length) return;

		// Sincronizar título y slug de WordPress en tiempo real
		if (window.wp && wp.data && wp.data.select && wp.data.select('core/editor')) {
			try {
				var currentSlug = wp.data.select('core/editor').getEditedPostAttribute('slug');
				if (currentSlug && $('#wpat_seo_slug_input').val() !== currentSlug && !$('#wpat_seo_slug_input').is(':focus')) {
					$('#wpat_seo_slug_input').val(currentSlug);
				}
				
				var currentTitle = wp.data.select('core/editor').getEditedPostAttribute('title') || "";
				var placeholderAttr = $('#wpat_seo_title_input').attr('placeholder') || "";
				var parts = placeholderAttr.split(' - ');
				var siteName = parts.length > 1 ? parts.slice(1).join(' - ') : "";
				var formattedPlaceholder = currentTitle ? (currentTitle + (siteName ? ' - ' + siteName : '')) : "Título de la entrada";
				$('#wpat_seo_title_input').attr('placeholder', formattedPlaceholder);
			} catch (err) {}
		} else if ($('#title').length) {
			var currentTitle = $('#title').val() || "";
			var placeholderAttr = $('#wpat_seo_title_input').attr('placeholder') || "";
			var parts = placeholderAttr.split(' - ');
			var siteName = parts.length > 1 ? parts.slice(1).join(' - ') : "";
			var formattedPlaceholder = currentTitle ? (currentTitle + (siteName ? ' - ' + siteName : '')) : "Título de la entrada";
			$('#wpat_seo_title_input').attr('placeholder', formattedPlaceholder);
		}

		// 1. Obtener entradas de texto
		var keywordInput = $('#wpat_seo_keyword_input').val() ? $('#wpat_seo_keyword_input').val().trim() : "";
		var keywords = keywordInput.split(',').map(function(k) { return k.trim().toLowerCase(); }).filter(function(k) { return k.length > 0; });
		
		var title = $('#wpat_seo_title_input').val() ? $('#wpat_seo_title_input').val().trim() : $('#wpat_seo_title_input').attr('placeholder');
		var desc = $('#wpat_seo_desc_input').val() ? $('#wpat_seo_desc_input').val().trim() : "";
		
		var isCornerstone = $('#wpat_seo_cornerstone_input').is(':checked');
		var minWords = isCornerstone ? 900 : 300;

		// Actualizar previsualización de Google
		var displayTitle = title ? title : "Título del post";
		var displayDesc = desc ? desc : "Por favor, escribe una meta descripción atractiva para que este contenido capte visitas en los resultados de búsqueda de Google.";
		$('.wpat-seo-preview-title').text(displayTitle);
		$('.wpat-seo-preview-desc').text(displayDesc);
		$('#wpat_seo_title_len').text(displayTitle.length);
		$('#wpat_seo_desc_len').text(desc.length);
		updateSeoTitleBar(displayTitle.length);
		updateSeoDescBar(desc.length);

		// 2. Obtener contenido del editor (Gutenberg o Clásico)
		var content = "";
		if (window.wp && wp.data && wp.data.select && wp.data.select('core/editor')) {
			try {
				content = wp.data.select('core/editor').getEditedPostContent() || "";
			} catch (err) {}
		}
		if (!content && $('#content').length) {
			if (window.tinyMCE && tinyMCE.get('content')) {
				content = tinyMCE.get('content').getContent();
			} else {
				content = $('#content').val();
			}
		}

		var cleanText = content ? content.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : "";
		var words = cleanText ? cleanText.split(/\s+/).length : 0;

		// --- ANÁLISIS SEO ---
		var seoGood = [];
		var seoImprovements = [];
		var seoIssues = [];

		if (keywords.length === 0) {
			$('#wpat_seo_bullet_seo').css('background', '#94a3b8'); // Gris
			$('#wpat_seo_analysis_bullets').html('<div><span style="color:#94a3b8; font-size:16px; margin-right:6px;">⚪</span> Por favor, escribe una o más frases clave objetivo (separadas por comas) para habilitar los análisis automáticos de SEO.</div>');
		} else {
			
			// A. Palabra clave en el título SEO (insensible a acentos)
			var titleFound = [];
			var titleMissing = [];
			var normalizedTitle = removeAccents(title.toLowerCase());
			keywords.forEach(function(kw) {
				var normalizedKw = removeAccents(kw);
				if (normalizedTitle.indexOf(normalizedKw) !== -1) {
					titleFound.push(kw);
				} else {
					titleMissing.push(kw);
				}
			});
			if (titleMissing.length === 0) {
				seoGood.push('<strong>Frases clave en el título:</strong> ¡Perfecto! Todas tus frases clave objetivo aparecen en el título SEO.');
			} else if (titleFound.length > 0) {
				seoImprovements.push('<strong>Frases clave en el título:</strong> Se encontraron en el título las frases ("' + titleFound.join('", "') + '"), pero faltan ("' + titleMissing.join('", "') + '").');
			} else {
				seoIssues.push('<strong>Frases clave en el título:</strong> Ninguna de tus frases clave objetivo aparece en el título SEO.');
			}

			// B. Palabra clave en la meta descripción (insensible a acentos)
			var descFound = [];
			var descMissing = [];
			var normalizedDesc = removeAccents(desc.toLowerCase());
			keywords.forEach(function(kw) {
				var normalizedKw = removeAccents(kw);
				if (normalizedDesc.indexOf(normalizedKw) !== -1) {
					descFound.push(kw);
				} else {
					descMissing.push(kw);
				}
			});
			if (descMissing.length === 0) {
				seoGood.push('<strong>Frases clave en la descripción:</strong> Todas tus frases clave objetivo aparecen en la meta descripción.');
			} else if (descFound.length > 0) {
				seoImprovements.push('<strong>Frases clave en la descripción:</strong> Algunas frases clave no aparecen en la meta descripción ("' + descMissing.join('", "') + '").');
			} else {
				seoIssues.push('<strong>Frases clave en la descripción:</strong> Ninguna de tus frases clave objetivo aparece en la meta descripción.');
			}

			// C. Frase clave al inicio del texto (insensible a acentos)
			var introFound = [];
			var introMissing = [];
			var introWords = cleanText.toLowerCase().split(/\s+/).slice(0, 100).join(' ');
			var normalizedIntro = removeAccents(introWords);
			keywords.forEach(function(kw) {
				var normalizedKw = removeAccents(kw);
				if (normalizedIntro.indexOf(normalizedKw) !== -1) {
					introFound.push(kw);
				} else {
					introMissing.push(kw);
				}
			});
			if (introMissing.length === 0) {
				seoGood.push('<strong>Frases clave en la introducción:</strong> ¡Excelente! Todas tus frases clave aparecen en el primer párrafo.');
			} else if (introFound.length > 0) {
				seoImprovements.push('<strong>Frases clave en la introducción:</strong> Faltan algunas frases clave al inicio del texto ("' + introMissing.join('", "') + '").');
			} else {
				seoIssues.push('<strong>Frases clave en la introducción:</strong> Ninguna de tus frases clave aparece en el primer párrafo del texto.');
			}

			// D. Densidad de palabra clave (insensible a acentos con límites de palabra precisos)
			var normalizedCleanText = removeAccents(cleanText.toLowerCase());
			keywords.forEach(function(kw) {
				var normalizedKw = removeAccents(kw);
				var escapedKeyword = normalizedKw.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
				var regex = new RegExp('(?<=^|[^a-zA-Z0-9])' + escapedKeyword + '(?=$|[^a-zA-Z0-9])', 'gi');
				var matches = normalizedCleanText.match(regex);
				var occurrences = matches ? matches.length : 0;
				var density = words > 0 ? ((occurrences / words) * 100) : 0;

				if (occurrences === 0) {
					seoIssues.push('<strong>Frase clave "' + kw + '" en el texto:</strong> No se encontró esta frase clave en el contenido del artículo. Procura escribirla al menos un par de veces para que Google entienda la temática.');
				} else if (density >= 0.5 && density <= 2.5) {
					seoGood.push('<strong>Densidad de "' + kw + '":</strong> ¡Excelente! Aparece ' + occurrences + ' veces (densidad del ' + density.toFixed(2) + '%), lo cual es ideal.');
				} else if (density > 2.5) {
					seoIssues.push('<strong>Densidad de "' + kw + '":</strong> Aparece ' + occurrences + ' veces (densidad del ' + density.toFixed(2) + '%). ¡Es demasiado alta! Reduce su frecuencia para evitar el Keyword Stuffing.');
				} else {
					seoImprovements.push('<strong>Densidad de "' + kw + '":</strong> Aparece ' + occurrences + ' veces (densidad del ' + density.toFixed(2) + '%). Es un poco baja; se recomienda al menos un 0.50% de densidad.');
				}
			});

			// E. Longitud del título SEO
			var tLen = displayTitle.length;
			if (tLen >= 50 && tLen <= 60) {
				seoGood.push('<strong>Longitud del título SEO:</strong> ¡Excelente longitud!');
			} else if (tLen > 60) {
				seoIssues.push('<strong>Longitud del título SEO:</strong> Es demasiado largo (' + tLen + ' caracteres). Procura que no supere los 60.');
			} else {
				seoImprovements.push('<strong>Longitud del título SEO:</strong> Es corto (' + tLen + ' caracteres). Rellena más espacio usando palabras atractivas.');
			}

			// F. Longitud de la meta descripción
			var dLen = desc.length;
			if (dLen >= 120 && dLen <= 160) {
				seoGood.push('<strong>Longitud de la meta descripción:</strong> ¡Excelente longitud!');
			} else if (dLen > 160) {
				seoIssues.push('<strong>Longitud de la meta descripción:</strong> Supera los 160 caracteres. Intenta acortarla para que no se recorte en Google.');
			} else if (dLen > 0) {
				seoImprovements.push('<strong>Longitud de la meta descripción:</strong> Es corta (' + dLen + ' caracteres). Escribe más argumentos de venta.');
			} else {
				seoIssues.push('<strong>Meta descripción vacía:</strong> Escribe una meta descripción para capturar visitas.');
			}

			// Renderizar lista SEO
			var seoHTML = "";
			if (seoIssues.length > 0) {
				seoHTML += '<div style="margin-bottom:10px;"><strong style="color:#ef4444; font-size:12px; text-transform:uppercase;">Problemas (' + seoIssues.length + ')</strong><ul style="margin:5px 0 0 0; padding-left:15px; list-style-type:none;">';
				seoIssues.forEach(function(item) {
					seoHTML += '<li style="margin-bottom:4px; display:flex; align-items:start;"><span style="color:#ef4444; margin-right:8px; font-size:14px;">🔴</span> <span>' + item + '</span></li>';
				});
				seoHTML += '</ul></div>';
			}
			if (seoImprovements.length > 0) {
				seoHTML += '<div style="margin-bottom:10px;"><strong style="color:#eab308; font-size:12px; text-transform:uppercase;">Mejoras (' + seoImprovements.length + ')</strong><ul style="margin:5px 0 0 0; padding-left:15px; list-style-type:none;">';
				seoImprovements.forEach(function(item) {
					seoHTML += '<li style="margin-bottom:4px; display:flex; align-items:start;"><span style="color:#eab308; margin-right:8px; font-size:14px;">🟡</span> <span>' + item + '</span></li>';
				});
				seoHTML += '</ul></div>';
			}
			if (seoGood.length > 0) {
				seoHTML += '<div><strong style="color:#10b981; font-size:12px; text-transform:uppercase;">Buenos resultados (' + seoGood.length + ')</strong><ul style="margin:5px 0 0 0; padding-left:15px; list-style-type:none;">';
				seoGood.forEach(function(item) {
					seoHTML += '<li style="margin-bottom:4px; display:flex; align-items:start;"><span style="color:#10b981; margin-right:8px; font-size:14px;">🟢</span> <span>' + item + '</span></li>';
				});
				seoHTML += '</ul></div>';
			}
			$('#wpat_seo_analysis_bullets').html(seoHTML);

			// Actualizar color de la pestaña SEO
			if (seoIssues.length > 0) {
				$('#wpat_seo_bullet_seo').css('background', '#ef4444');
			} else if (seoImprovements.length > 0) {
				$('#wpat_seo_bullet_seo').css('background', '#eab308');
			} else {
				$('#wpat_seo_bullet_seo').css('background', '#10b981');
			}
		}

		// --- ANÁLISIS DE LEGIBILIDAD ---
		var readGood = [];
		var readImprovements = [];
		var readIssues = [];

		// A. Longitud del texto (Ajustable por contenido esencial)
		if (words >= minWords) {
			readGood.push('<strong>Longitud del texto:</strong> Contiene ' + words + ' palabras (óptimo para contenido ' + (isCornerstone ? 'esencial' : 'estándar') + ').');
		} else {
			readIssues.push('<strong>Longitud del texto:</strong> El contenido tiene solo ' + words + ' palabras. Se recomienda un mínimo de ' + minWords + ' palabras para contenidos de tipo ' + (isCornerstone ? 'esencial' : 'estándar') + '.');
		}

		// B. Distribución de subtítulos H2/H3
		if (words > 300) {
			var hasHeaders = (content.indexOf('<h2') !== -1 || content.indexOf('<h3') !== -1 || content.indexOf('<!-- wp:heading') !== -1);
			if (hasHeaders) {
				readGood.push('<strong>Distribución de subtítulos:</strong> Excelente variedad, usas encabezados de sección.');
			} else {
				readIssues.push('<strong>Distribución de subtítulos:</strong> Tu texto es largo y no contiene subtítulos (H2/H3). Agrégalos para facilitar la lectura.');
			}
		} else {
			readGood.push('<strong>Distribución de subtítulos:</strong> No se requieren subtítulos para un texto tan corto.');
		}

		// C. Longitud de los párrafos
		var paragraphs = content ? content.split(/<\/p>|<br\s*\/?>/i) : [];
		var tooLongParagraph = false;
		paragraphs.forEach(function(p) {
			var pWords = p.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().split(/\s+/).length;
			if (pWords > 150) {
				tooLongParagraph = true;
			}
		});

		if (!tooLongParagraph) {
			readGood.push('<strong>Longitud de los párrafos:</strong> Todos tus párrafos tienen una extensión adecuada.');
		} else {
			readIssues.push('<strong>Longitud de los párrafos:</strong> Tienes algún párrafo que supera las 150 palabras. Divídelo para que sea más legible.');
		}

		// D. Palabras de transición en español
		var SpanishTransitions = ['además', 'sin embargo', 'por lo tanto', 'también', 'pero', 'entonces', 'así que', 'por consiguiente', 'no obstante', 'dado que', 'debido a', 'así como', 'ya que', 'por ejemplo', 'por último', 'finalmente', 'en primer lugar', 'por otra parte', 'en consecuencia'];
		var sentences = cleanText.split(/[.!?]+/);
		var totalSentences = 0;
		var transitionSentences = 0;

		sentences.forEach(function(s) {
			var sentenceText = s.trim().toLowerCase();
			if (sentenceText.length > 5) {
				totalSentences++;
				var hasTransition = false;
				SpanishTransitions.forEach(function(t) {
					if (sentenceText.indexOf(t) !== -1) {
						hasTransition = true;
					}
				});
				if (hasTransition) {
					transitionSentences++;
				}
			}
		});

		var ratio = totalSentences > 0 ? (transitionSentences / totalSentences) * 100 : 0;
		if (ratio >= 20) {
			readGood.push('<strong>Palabras de transición:</strong> El ' + Math.round(ratio) + '% de las frases contienen palabras de transición. ¡Excelente!');
		} else {
			readImprovements.push('<strong>Palabras de transición:</strong> Solo el ' + Math.round(ratio) + '% de las frases usan palabras de transición. Intenta usar más nexos como "además", "sin embargo" o "por lo tanto".');
		}

		// E. Frases consecutivas que inician igual
		var consecutiveStart = false;
		var lastStartWord = "";
		var consecutiveCount = 1;
		sentences.forEach(function(s) {
			var wordsInSentence = s.trim().split(/\s+/);
			if (wordsInSentence.length > 0 && wordsInSentence[0].length > 2) {
				var startWord = wordsInSentence[0].toLowerCase();
				if (startWord === lastStartWord) {
					consecutiveCount++;
					if (consecutiveCount >= 3) {
						consecutiveStart = true;
					}
				} else {
					lastStartWord = startWord;
					consecutiveCount = 1;
				}
			}
		});

		if (!consecutiveStart) {
			readGood.push('<strong>Frases consecutivas:</strong> Excelente variedad en el inicio de las frases.');
		} else {
			readImprovements.push('<strong>Frases consecutivas:</strong> Tienes 3 o más frases seguidas que empiezan con la misma palabra. Varía los inicios.');
		}

		// Renderizar lista de Legibilidad
		var readHTML = "";
		if (readIssues.length > 0) {
			readHTML += '<div style="margin-bottom:10px;"><strong style="color:#ef4444; font-size:12px; text-transform:uppercase;">Problemas (' + readIssues.length + ')</strong><ul style="margin:5px 0 0 0; padding-left:15px; list-style-type:none;">';
			readIssues.forEach(function(item) {
				readHTML += '<li style="margin-bottom:4px; display:flex; align-items:start;"><span style="color:#ef4444; margin-right:8px; font-size:14px;">🔴</span> <span>' + item + '</span></li>';
			});
			readHTML += '</ul></div>';
		}
		if (readImprovements.length > 0) {
			readHTML += '<div style="margin-bottom:10px;"><strong style="color:#eab308; font-size:12px; text-transform:uppercase;">Mejoras (' + readImprovements.length + ')</strong><ul style="margin:5px 0 0 0; padding-left:15px; list-style-type:none;">';
			readImprovements.forEach(function(item) {
				readHTML += '<li style="margin-bottom:4px; display:flex; align-items:start;"><span style="color:#eab308; margin-right:8px; font-size:14px;">🟡</span> <span>' + item + '</span></li>';
			});
			readHTML += '</ul></div>';
		}
		if (readGood.length > 0) {
			readHTML += '<div><strong style="color:#10b981; font-size:12px; text-transform:uppercase;">Buenos resultados (' + readGood.length + ')</strong><ul style="margin:5px 0 0 0; padding-left:15px; list-style-type:none;">';
			readGood.forEach(function(item) {
				readHTML += '<li style="margin-bottom:4px; display:flex; align-items:start;"><span style="color:#10b981; margin-right:8px; font-size:14px;">🟢</span> <span>' + item + '</span></li>';
			});
			readHTML += '</ul></div>';
		}
		$('#wpat_readability_analysis_bullets').html(readHTML);

		// Actualizar color de la pestaña Legibilidad
		if (readIssues.length > 0) {
			$('#wpat_readability_bullet_readability').css('background', '#ef4444');
		} else if (readImprovements.length > 0) {
			$('#wpat_readability_bullet_readability').css('background', '#eab308');
		} else {
			$('#wpat_readability_bullet_readability').css('background', '#10b981');
		}
	}

	// Escuchar cambios de escritura en vivo para actualizar análisis
	$(document).on('input keyup change', '#wpat_seo_keyword_input, #wpat_seo_title_input, #wpat_seo_desc_input', function() {
		runSeoAndReadabilityAnalysis();
	});

	// Escuchar conmutador de contenido esencial (Cornerstone)
	$(document).on('change', '#wpat_seo_cornerstone_input', function() {
		runSeoAndReadabilityAnalysis();
	});

	// Escuchar cambios de slug en nuestra metabox para actualizar Gutenberg
	$(document).on('input change', '#wpat_seo_slug_input', function() {
		var newSlug = $(this).val();
		if (window.wp && wp.data && wp.data.dispatch && wp.data.dispatch('core/editor')) {
			try {
				wp.data.dispatch('core/editor').editPost({ slug: newSlug });
			} catch (err) {}
		}
	});

	// Suscribirse a cambios en Gutenberg (Contenido, Título y Slug)
	if (window.wp && wp.data && wp.data.subscribe) {
		var lastContent = "";
		var lastTitle = "";
		var lastSlug = "";
		wp.data.subscribe(function() {
			try {
				var newContent = wp.data.select('core/editor').getEditedPostContent() || "";
				var newTitle = wp.data.select('core/editor').getEditedPostAttribute('title') || "";
				var newSlug = wp.data.select('core/editor').getEditedPostAttribute('slug') || "";

				if (newContent !== lastContent || newTitle !== lastTitle || newSlug !== lastSlug) {
					lastContent = newContent;
					lastTitle = newTitle;
					lastSlug = newSlug;
					runSeoAndReadabilityAnalysis();
				}
			} catch (err) {}
		});
	}

	// Suscribirse al editor clásico (incluyendo TinyMCE)
	if ($('#content').length) {
		$('#content, #title').on('input change', function() {
			runSeoAndReadabilityAnalysis();
		});

		setTimeout(function() {
			if (window.tinyMCE && tinyMCE.get('content')) {
				tinyMCE.get('content').on('keyup change', function() {
					runSeoAndReadabilityAnalysis();
				});
			}
		}, 1500);
	}

	// Ejecutar primer análisis al cargar la página
	setTimeout(function() {
		runSeoAndReadabilityAnalysis();
	}, 1000);
	
	// Re-análisis preventivo al hacer clic en las pestañas
	$(document).on('click', '.wpat-seo-nav-tab', function() {
		runSeoAndReadabilityAnalysis();
	});

	// 6. Auditoría SEO del Sitio (AJAX Secuencial con Filtros e Historial)
	$('#wpat_seo_scan_btn').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var $status = $('#wpat_seo_scan_status');
		var $progressWrapper = $('#wpat_seo_scan_progress');
		var $progressBar = $('#wpat_seo_progress_bar');
		var $progressPercent = $('#wpat_seo_progress_percent');
		var $progressLabel = $('#wpat_seo_progress_label');
		var $resultsWrapper = $('#wpat_seo_audit_results');
		var $tableBody = $('#wpat_seo_audit_table_body');
		var $tableFilters = $('#wpat_seo_table_filters');

		// Resetear filtros previos
		$tableFilters.hide();
		$('#wpat_seo_table_search').val('');
		$('#wpat_seo_table_status_filter').val('all');
		seoAuditCurrentPage = 1;

		var postTypeFilter = $('#wpat_seo_filter_post_type').val() || 'all';

		$btn.prop('disabled', true).text('Escaneando...');
		$status.text('Obteniendo lista de páginas...');
		$progressWrapper.slideDown(200);
		$resultsWrapper.hide();
		$tableBody.empty();
		$progressBar.css('width', '0%').css('background', '#3b82f6');
		$progressPercent.text('0%');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_seo_get_pages_to_scan',
				post_type_filter: postTypeFilter
			},
			success: function(response) {
				if (response.success && response.data.ids && response.data.ids.length > 0) {
					var ids = response.data.ids;
					var total = ids.length;
					var processed = 0;
					var batchSize = 10;
					var batches = [];

					for (var i = 0; i < ids.length; i += batchSize) {
						batches.push(ids.slice(i, i + batchSize));
					}

					var currentBatchIndex = 0;

					function processNextBatch() {
						if (currentBatchIndex >= batches.length) {
							$btn.prop('disabled', false).text('Escanear Sitio Ahora');
							$status.text('¡Auditoría completada!');
							$progressLabel.text('Análisis completado al 100%');
							$progressBar.css('width', '100%').css('background', '#10b981');
							$progressPercent.text('100%');
							
							// Configurar y mostrar los filtros rápidos
							$tableFilters.css('display', 'flex');
							applySeoTableFilters();
							return;
						}

						var currentBatch = batches[currentBatchIndex];
						$progressLabel.text('Escaneando lote ' + (currentBatchIndex + 1) + ' de ' + batches.length + '...');

						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'wpat_seo_audit_page',
								post_ids: currentBatch
							},
							success: function(res) {
								if (res.success && res.data.results) {
									res.data.results.forEach(function(row) {
										var indexableBadge = row.indexable === 'index' 
											? '<span style="color:#047857; background:#d1fae5; padding:3px 8px; border-radius:12px; font-weight:700; font-size:11px;">🟢 Index</span>'
											: '<span style="color:#4b5563; background:#e5e7eb; padding:3px 8px; border-radius:12px; font-weight:700; font-size:11px;">⚪ noindex</span>';
										
										var keywordBadge = row.keyword 
											? '<span style="color:#1e293b; background:#f1f5f9; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600; display:inline-block; border:1px solid #cbd5e1;">' + row.keyword + '</span>'
											: '<span style="color:#b91c1c; font-weight:700; font-size:11px; background:#fee2fee; padding:2px 6px; border-radius:4px;">🔴 Vacío</span>';

										var titleBadge = '';
										if (row.title_status === 'empty') {
											titleBadge = '<span style="color:#b91c1c; font-weight:700; font-size:12px;">🔴 Vacío</span>';
										} else if (row.title_status === 'correct') {
											titleBadge = '<span style="color:#047857; font-weight:700; font-size:12px;">🟢 ' + row.title_length + ' carac.</span>';
										} else {
											titleBadge = '<span style="color:#b45309; font-weight:700; font-size:12px;">🟡 ' + row.title_length + ' carac. (Ajustar)</span>';
										}

										var descBadge = '';
										if (row.desc_status === 'empty') {
											descBadge = '<span style="color:#b91c1c; font-weight:700; font-size:12px;">🔴 Vacío</span>';
										} else if (row.desc_status === 'correct') {
											descBadge = '<span style="color:#047857; font-weight:700; font-size:12px;">🟢 ' + row.desc_length + ' carac.</span>';
										} else {
											descBadge = '<span style="color:#b45309; font-weight:700; font-size:12px;">🟡 ' + row.desc_length + ' carac. (Ajustar)</span>';
										}

										var editLink = row.edit_url 
											? '<a href="' + row.edit_url + '" class="row-title" style="font-weight:600; color:#3b82f6; text-decoration:none;" target="_blank">' + row.title + '</a>'
											: '<strong class="row-title">' + row.title + '</strong>';

										// Evaluar estado para los filtros de la tabla
										var hasIssues = (row.title_status !== 'correct' || row.desc_status !== 'correct' || row.title_status === 'empty' || row.desc_status === 'empty');
										var hasNoKeyword = (!row.keyword);
										var isOptimized = (row.title_status === 'correct' && row.desc_status === 'correct' && row.keyword);

										var rowHTML = '<tr class="wpat-seo-audit-row" ' +
											'data-title="' + row.title.toLowerCase() + '" ' +
											'data-issues="' + (hasIssues ? '1' : '0') + '" ' +
											'data-no-keyword="' + (hasNoKeyword ? '1' : '0') + '" ' +
											'data-optimized="' + (isOptimized ? '1' : '0') + '">' +
											'<td style="padding:10px; border-bottom: 1px solid #f1f5f9;">' + editLink + '<div style="font-size:11px; color:#64748b; margin-top:2px;">Tipo: ' + row.type + '</div></td>' +
											'<td style="padding:10px; border-bottom: 1px solid #f1f5f9; vertical-align:middle;">' + indexableBadge + '</td>' +
											'<td style="padding:10px; border-bottom: 1px solid #f1f5f9; vertical-align:middle;">' + keywordBadge + '</td>' +
											'<td style="padding:10px; border-bottom: 1px solid #f1f5f9; vertical-align:middle;">' + titleBadge + '</td>' +
											'<td style="padding:10px; border-bottom: 1px solid #f1f5f9; vertical-align:middle;">' + descBadge + '</td>' +
											'</tr>';
										
										$tableBody.append(rowHTML);
									});

									$resultsWrapper.show();
									
									processed += currentBatch.length;
									var percent = Math.round((processed / total) * 100);
									$progressBar.css('width', percent + '%');
									$progressPercent.text(percent + '%');
									
									currentBatchIndex++;
									processNextBatch();
								} else {
									alert('Error al escanear lote de páginas.');
									resetScanBtn();
								}
							},
							error: function() {
								alert('Error de conexión al analizar lote de páginas.');
								resetScanBtn();
							}
						});
					}

					processNextBatch();
				} else {
					$btn.prop('disabled', false).text('Escanear Sitio Ahora');
					$status.text('No se encontraron páginas públicas para analizar.');
					$progressWrapper.slideUp(200);
				}
			},
			error: function() {
				alert('Error de conexión al obtener páginas del sitio.');
				resetScanBtn();
			}
		});

		function resetScanBtn() {
			$btn.prop('disabled', false).text('Escanear Sitio Ahora');
			$status.text('Análisis interrumpido por un error.');
			$progressWrapper.slideUp(200);
		}
	});

	// Lógica de filtrado y paginación de la tabla de auditoría SEO
	var seoAuditCurrentPage = 1;
	var seoAuditPageSize = 15; // Mostrar 15 registros por página

	function updateSeoTableCounter() {
		var total = $('.wpat-seo-audit-row').length;
		var visible = $('.wpat-seo-audit-row:visible').length;
		$('#wpat_seo_total_count').text(total);
		$('#wpat_seo_filtered_count').text(visible);
	}

	function applySeoTableFilters() {
		var searchQuery = $('#wpat_seo_table_search').val().trim().toLowerCase();
		var statusFilter = $('#wpat_seo_table_status_filter').val();
		var $matchingRows = $();

		$('.wpat-seo-audit-row').each(function() {
			var $row = $(this);
			var title = $row.attr('data-title') || '';
			var matchesSearch = (title.indexOf(searchQuery) !== -1);
			var matchesStatus = false;

			if (statusFilter === 'all') {
				matchesStatus = true;
			} else if (statusFilter === 'issues') {
				matchesStatus = ($row.attr('data-issues') === '1');
			} else if (statusFilter === 'no-keyword') {
				matchesStatus = ($row.attr('data-no-keyword') === '1');
			} else if (statusFilter === 'optimized') {
				matchesStatus = ($row.attr('data-optimized') === '1');
			}

			if (matchesSearch && matchesStatus) {
				$matchingRows = $matchingRows.add($row);
			} else {
				$row.hide();
			}
		});

		var totalMatching = $matchingRows.length;
		var totalPages = Math.max(1, Math.ceil(totalMatching / seoAuditPageSize));

		// Forzar límites de página
		if (seoAuditCurrentPage > totalPages) {
			seoAuditCurrentPage = totalPages;
		}
		if (seoAuditCurrentPage < 1) {
			seoAuditCurrentPage = 1;
		}

		var startIndex = (seoAuditCurrentPage - 1) * seoAuditPageSize;
		var endIndex = startIndex + seoAuditPageSize;

		// Mostrar solo los elementos de la página actual
		$matchingRows.each(function(index) {
			if (index >= startIndex && index < endIndex) {
				$(this).show();
			} else {
				$(this).hide();
			}
		});

		// Actualizar estado de botones y números en la UI
		$('#wpat_seo_audit_page_num').text(seoAuditCurrentPage);
		$('#wpat_seo_audit_total_pages').text(totalPages);

		$('#wpat_seo_audit_prev_btn').prop('disabled', seoAuditCurrentPage <= 1);
		$('#wpat_seo_audit_next_btn').prop('disabled', seoAuditCurrentPage >= totalPages);

		updateSeoTableCounter();
	}

	$(document).on('input', '#wpat_seo_table_search', function() {
		seoAuditCurrentPage = 1;
		applySeoTableFilters();
	});

	$(document).on('change', '#wpat_seo_table_status_filter', function() {
		seoAuditCurrentPage = 1;
		applySeoTableFilters();
	});

	// Botones de navegación de la paginación
	$(document).on('click', '#wpat_seo_audit_prev_btn', function(e) {
		e.preventDefault();
		if (seoAuditCurrentPage > 1) {
			seoAuditCurrentPage--;
			applySeoTableFilters();
		}
	});

	$(document).on('click', '#wpat_seo_audit_next_btn', function(e) {
		e.preventDefault();
		var totalPages = parseInt($('#wpat_seo_audit_total_pages').text(), 10) || 1;
		if (seoAuditCurrentPage < totalPages) {
			seoAuditCurrentPage++;
			applySeoTableFilters();
		}
	});

	// === 7. PESTAÑA DE HERRAMIENTAS Y MANTENIMIENTO ===
	// Toggle de checkboxes del exportador
	$(document).on('change', '#wpat_export_all_checkbox', function() {
		if ($(this).is(':checked')) {
			$('#wpat_export_types_wrapper').slideUp(200);
		} else {
			$('#wpat_export_types_wrapper').slideDown(200).css('display', 'flex');
		}
	});

	// Trigger selector de archivo de importación
	$(document).on('click', '#wpat_select_import_file_btn', function(e) {
		e.preventDefault();
		$('#wpat_import_file_field').trigger('click');
	});

	// Feedback visual cuando seleccionan un archivo JSON
	$(document).on('change', '#wpat_import_file_field', function() {
		var file = this.files[0];
		if (file) {
			$('#wpat_import_feedback_name').text(file.name);
			$('#wpat_import_file_feedback').slideDown(200);
			$('#wpat_import_contents_submit_btn').prop('disabled', false);
			$('#wpat_import_file_label').text('Archivo JSON listo');
		} else {
			$('#wpat_import_feedback_name').text('ninguno');
			$('#wpat_import_file_feedback').slideUp(200);
			$('#wpat_import_contents_submit_btn').prop('disabled', true);
			$('#wpat_import_file_label').text('Selecciona tu archivo .json');
		}
	});

	// 7. Generador SEO en Masa (Auto-rellenado)
	$('#wpat_seo_bulk_fill_btn').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var $status = $('#wpat_seo_bulk_status');
		var $progressWrapper = $('#wpat_seo_bulk_progress');
		var $progressBar = $('#wpat_seo_bulk_progress_bar');
		var $progressPercent = $('#wpat_seo_bulk_progress_percent');
		var $progressLabel = $('#wpat_seo_bulk_progress_label');

		var postTypeFilter = $('#wpat_seo_bulk_post_type').val() || 'all';

		if (!confirm('¿Estás seguro de que deseas auto-rellenar los campos SEO vacíos? Esto escribirá en la base de datos títulos y descripciones por defecto para todos los contenidos que no los tengan configurados.')) {
			return;
		}

		$btn.prop('disabled', true).text('Procesando...');
		$status.text('Buscando contenidos sin SEO...');
		$progressWrapper.slideDown(200);
		$progressBar.css('width', '0%').css('background', '#10b981');
		$progressPercent.text('0%');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_seo_get_posts_to_fill',
				post_type_filter: postTypeFilter
			},
			success: function(response) {
				if (response.success && response.data.ids && response.data.ids.length > 0) {
					var ids = response.data.ids;
					var total = ids.length;
					var processed = 0;
					var batchSize = 25; // Procesamos 25 a la vez para máxima velocidad
					var batches = [];

					for (var i = 0; i < ids.length; i += batchSize) {
						batches.push(ids.slice(i, i + batchSize));
					}

					var currentBatchIndex = 0;

					function processNextBatch() {
						if (currentBatchIndex >= batches.length) {
							$btn.prop('disabled', false).text('Auto-rellenar Campos Vacíos');
							$status.text('¡Optimización en masa completada!');
							$progressLabel.text('Se han optimizado ' + total + ' contenidos.');
							$progressBar.css('width', '100%');
							$progressPercent.text('100%');

							alert('Se han auto-rellenado con éxito los campos SEO de ' + total + ' elementos. Se recomienda ejecutar la Auditoría SEO de nuevo para ver los nuevos resultados.');
							return;
						}

						var currentBatch = batches[currentBatchIndex];
						$progressLabel.text('Procesando lote ' + (currentBatchIndex + 1) + ' de ' + batches.length + '...');

						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'wpat_seo_fill_posts_batch',
								post_ids: currentBatch
							},
							success: function(res) {
								if (res.success) {
									processed += currentBatch.length;
									var pct = Math.round((processed / total) * 100);
									$progressBar.css('width', pct + '%');
									$progressPercent.text(pct + '%');
									$status.text('Optimizados ' + processed + ' de ' + total + '...');
									
									currentBatchIndex++;
									processNextBatch();
								} else {
									$btn.prop('disabled', false).text('Auto-rellenar Campos Vacíos');
									$status.text('Error al procesar lote.');
								}
							},
							error: function() {
								$btn.prop('disabled', false).text('Auto-rellenar Campos Vacíos');
								$status.text('Error de conexión.');
							}
						});
					}

					processNextBatch();
				} else {
					$btn.prop('disabled', false).text('Auto-rellenar Campos Vacíos');
					$status.text('¡No hay contenidos vacíos para optimizar!');
					$progressWrapper.slideUp(200);
					alert('¡Todos los contenidos ya tienen campos SEO configurados en el tipo seleccionado!');
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Auto-rellenar Campos Vacíos');
				$status.text('Error de conexión.');
			}
		});
	// 8. Comprobación de actualización por AJAX (sin recarga de página)
	$(document).on('click', '#wpat_force_update_check_btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var $statusText = $('#wpat_updater_widget_status');
		var $actionContainer = $('#wpat_updater_widget_action');
		
		$btn.prop('disabled', true).text('Comprobando...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpat_force_update_check'
			},
			success: function(response) {
				if (response.success) {
					var data = response.data;
					if (data.has_update) {
						$statusText.html(
							'Versión actual: <strong>v' + data.current_version + '</strong><br>' +
							'Última versión: <strong style="color: #ea580c;">v' + data.new_version + '</strong>'
						);
						$actionContainer.html(
							'<a href="' + data.update_url + '" class="button button-primary" style="width: 100%; text-align: center; background: #ea580c; border-color: #d97706; font-weight: 700; height: 28px; line-height: 26px; display: block; box-sizing: border-box;">' +
								'Actualizar ahora' +
							'</a>'
						);
					} else {
						$statusText.html(
							'Versión actual: <strong>v' + data.current_version + '</strong><br>' +
							'Estado: <span style="color: #10b981; font-weight: 600;">Actualizado 🟢</span>'
						);
						$btn.prop('disabled', false).text('Comprobar de nuevo');
						
						alert('¡Tu plugin WP Agency Toolkit ya está actualizado a la última versión disponible (v' + data.current_version + ')!');
					}
				} else {
					$btn.prop('disabled', false).text('Comprobar de nuevo');
					alert('Error al comprobar actualizaciones.');
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Comprobar de nuevo');
				alert('Error de conexión con el servidor.');
			}
		});
	});

});

