/**
 * Scripts interactivos para Formularios de Registro Personalizados - WP Agency Toolkit
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		/* ==========================================================================
		   1. FRONTEND: Envío y feedback de formularios de registro
		   ========================================================================== */

		$('.wpat-reg-form').on('submit', function() {
			var $form = $(this);
			var $btn  = $form.find('.wpat-reg-submit-btn');

			// Deshabilitar botón para evitar envíos dobles
			$btn.prop('disabled', true);
			var originalText = $btn.html();
			$btn.attr('data-orig-text', originalText);
			$btn.html('<span class="dashicons dashicons-update spin" style="font-size:16px; width:16px; height:16px; vertical-align:middle; animation: rotation 1s infinite linear;"></span> Procesando registro...');
		});

		// Manejo de input de ficheros para mostrar nombre de archivo seleccionado
		$('.wpat-reg-file-input').on('change', function() {
			var fileName = $(this).val().split('\\').pop();
			var $hint    = $(this).siblings('.wpat-reg-file-name-hint');
			if (fileName) {
				if (!$hint.length) {
					$hint = $('<span class="wpat-reg-file-name-hint wpat-reg-hint" style="color: #16a34a; font-weight: 600; display: block; margin-top: 4px;"></span>');
					$(this).after($hint);
				}
				$hint.text('Archivo seleccionado: ' + fileName);
			} else if ($hint.length) {
				$hint.remove();
			}
		});

		/* ==========================================================================
		   2. ADMIN: Pestañas de Navegación del Panel del Módulo
		   ========================================================================== */

		$('.wpat-reg-admin-tab-btn').on('click', function(e) {
			e.preventDefault();
			var targetTab = $(this).data('tab');

			$('.wpat-reg-admin-tab-btn').removeClass('active');
			$(this).addClass('active');

			$('.wpat-reg-tab-panel').removeClass('active');
			$('#' + targetTab).addClass('active');
		});

		// Copiar shortcode al portapapeles
		$(document).on('click', '.wpat-copy-shortcode', function() {
			var code = $(this).text().trim();
			var $el  = $(this);

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(code).then(function() {
					var orig = $el.text();
					$el.text('¡Copiado!');
					setTimeout(function() {
						$el.text(orig);
					}, 1800);
				});
			} else {
				// Fallback
				var temp = $('<input>');
				$('body').append(temp);
				temp.val(code).select();
				document.execCommand('copy');
				temp.remove();
				var orig = $el.text();
				$el.text('¡Copiado!');
				setTimeout(function() {
					$el.text(orig);
				}, 1800);
			}
		});

		/* ==========================================================================
		   3. ADMIN: Aprobación y Rechazo Rápido de Usuarios vía AJAX
		   ========================================================================== */

		$(document).on('click', '.wpat-user-status-btn', function(e) {
			e.preventDefault();
			var $btn     = $(this);
			var userId   = $btn.data('user-id');
			var newStatus = $btn.data('status');
			var nonce    = $btn.data('nonce') || (window.wpatRegForms ? window.wpatRegForms.nonce : '');

			if (newStatus === 'rejected' && !confirm('¿Estás seguro de que deseas rechazar esta solicitud de registro?')) {
				return;
			}

			$btn.prop('disabled', true).css('opacity', '0.5');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wpat_update_user_reg_status',
					user_id: userId,
					status: newStatus,
					security: nonce
				},
				success: function(response) {
					if (response.success) {
						var $row = $btn.closest('tr');
						var $badge = $row.find('.wpat-reg-badge');

						if (newStatus === 'active') {
							$badge.removeClass('pending rejected').addClass('active').text('Activo');
							$btn.closest('.wpat-user-actions-cell').html('<span style="color:#16a34a; font-weight:600; font-size:12px;">✓ Aprobado</span>');
						} else if (newStatus === 'rejected') {
							$badge.removeClass('pending active').addClass('rejected').text('Rechazado');
							$btn.closest('.wpat-user-actions-cell').html('<span style="color:#dc2626; font-weight:600; font-size:12px;">✗ Rechazado</span>');
						}
					} else {
						alert(response.data && response.data.message ? response.data.message : 'Error al actualizar el estado.');
						$btn.prop('disabled', false).css('opacity', '1');
					}
				},
				error: function() {
					alert('Error de conexión en el servidor.');
					$btn.prop('disabled', false).css('opacity', '1');
				}
			});
		});

		/* ==========================================================================
		   4. ADMIN: Constructor de Campos (Añadir / Eliminar / Reordenar con Drag & Drop)
		   ========================================================================== */

		// Reindexar campos para guardar siempre en el orden visual exacto
		function reindexFields() {
			$('.wpat-reg-fields-list .wpat-reg-field-row').each(function(idx) {
				$(this).find('input, select, textarea').each(function() {
					var name = $(this).attr('name');
					if (name && name.indexOf('fields_data[') === 0) {
						var updatedName = name.replace(/^fields_data\[[^\]]+\]/, 'fields_data[' + idx + ']');
						$(this).attr('name', updatedName);
					}
				});
			});
			$('#wpat_fields_count').text($('.wpat-reg-fields-list .wpat-reg-field-row').length);
		}

		// Inicializar reordenación Drag & Drop con jQuery UI Sortable
		function initFieldsSortable() {
			var $list = $('.wpat-reg-fields-list');
			if ($list.length && $.fn.sortable) {
				$list.sortable({
					handle: '.wpat-reg-field-drag-handle',
					items: '> .wpat-reg-field-row',
					placeholder: 'wpat-reg-field-placeholder',
					axis: 'y',
					opacity: 0.85,
					cursor: 'grabbing',
					tolerance: 'pointer',
					forcePlaceholderSize: true,
					start: function(e, ui) {
						ui.placeholder.height(ui.item.outerHeight());
					},
					stop: function(e, ui) {
						reindexFields();
					}
				});
			}
		}

		initFieldsSortable();

		// Asegurar reindexado correcto antes de enviar el formulario
		$('form').on('submit', function() {
			if ($('.wpat-reg-fields-list').length) {
				reindexFields();
			}
		});

		// Eliminar campo
		$(document).on('click', '.wpat-reg-remove-field-btn', function(e) {
			e.preventDefault();
			if (confirm('¿Eliminar este campo del formulario?')) {
				var $row = $(this).closest('.wpat-reg-field-row');
				$row.slideUp(200, function() {
					$row.remove();
					reindexFields();
					if ($('.wpat-reg-fields-list').data('ui-sortable')) {
						$('.wpat-reg-fields-list').sortable('refresh');
					}
				});
			}
		});

		// Desplegar/plegar opciones avanzadas de campo
		$(document).on('click', '.wpat-reg-toggle-field-btn', function(e) {
			e.preventDefault();
			var $row = $(this).closest('.wpat-reg-field-row');
			$row.find('.wpat-reg-field-options').slideToggle(200);
		});

		// Modal para Añadir Nuevo Campo
		$(document).on('click', '#wpat_reg_open_add_field_btn', function(e) {
			e.preventDefault();
			$('#wpat_reg_add_field_modal').fadeIn(200);
		});

		$(document).on('click', '#wpat_reg_close_field_modal, .wpat-reg-modal-backdrop', function(e) {
			e.preventDefault();
			$('#wpat_reg_add_field_modal').fadeOut(200);
		});

		// Alternar visibilidad de campos de asignación condicional de rol
		$(document).on('change', '.wpat-check-cond-role', function() {
			var isChecked = $(this).is(':checked');
			$(this).closest('.wpat-reg-cond-box').find('.wpat-reg-cond-fields').css('display', isChecked ? 'grid' : 'none');
		});

		// Confirmar añadir campo seleccionado
		$(document).on('click', '#wpat_reg_confirm_add_field_btn', function(e) {
			e.preventDefault();
			var fieldType = $('#wpat_reg_new_field_type').val();
			if (!fieldType) return;

			var $opt = $('#wpat_reg_new_field_type option:selected');
			var fieldLabel = $opt.data('label') || 'Nuevo Campo';
			var isNative   = $opt.data('native') ? '1' : '0';
			var inputType  = $opt.data('input-type') || 'text';
			var fieldId    = $opt.data('id') || ('custom_' + Date.now().toString(36));

			var newIdx = $('.wpat-reg-field-row').length + Math.floor(Math.random() * 1000);
			var safeLabel = $('<div>').text(fieldLabel).html();

			var rowHtml = '<div class="wpat-reg-field-row" data-type="' + inputType + '" style="background:#f0fdf4; border-color:#86efac;">' +
				'<div class="wpat-reg-field-row-info">' +
					'<span class="dashicons dashicons-menu wpat-reg-field-drag-handle"></span>' +
					'<div>' +
						'<strong style="font-size: 13.5px; color: #1e293b;" class="wpat-row-title-lbl">' + safeLabel + '</strong>' +
						'<div style="font-size: 11px; color: #64748b; font-family: monospace;">ID: ' + fieldId + '</div>' +
					'</div>';

			if (inputType === 'heading') {
				rowHtml += '<span class="wpat-reg-field-badge-type" style="background:#e0e7ff; color:#4338ca;">TÍTULO / SECCIÓN</span>' +
					'<span style="color: #6366f1; font-size: 11px; font-weight: 600;">Separador Visual</span>';
			} else {
				var badgeStyle = (inputType === 'radio') ? 'background:#fef3c7; color:#92400e;' : '';
				rowHtml += '<span class="wpat-reg-field-badge-type" style="' + badgeStyle + '">' + inputType.toUpperCase() + '</span>' +
					'<span style="color: #94a3b8; font-size: 11px;" class="wpat-row-req-lbl">Opcional</span>' +
					'<span style="font-size: 11px; color: #64748b;" class="wpat-row-width-lbl">100% (Ancho completo)</span>';
			}

			rowHtml += '</div>' +
				'<div style="display: flex; align-items: center; gap: 8px;">' +
					'<button type="button" class="button button-small wpat-reg-toggle-field-btn">Editar</button>' +
					'<button type="button" class="button button-small button-link-delete wpat-reg-remove-field-btn" style="color: #dc2626;">Eliminar</button>' +
				'</div>' +
				'<div class="wpat-reg-field-options" style="display: block; width: 100%; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;">';

			if (inputType === 'heading') {
				rowHtml += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">' +
					'<div>' +
						'<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 4px;">Título de la Sección / Separador:</label>' +
						'<input type="text" name="fields_data[' + newIdx + '][label]" value="' + safeLabel + '" class="wpat-input-field-label" placeholder="Ej: LOS DATOS DE TU TIENDA" style="width: 100%; font-weight: 600;" />' +
					'</div>' +
					'<div>' +
						'<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 4px;">Subtítulo o descripción explicativa (opcional):</label>' +
						'<input type="text" name="fields_data[' + newIdx + '][subtitle]" value="" placeholder="Texto de ayuda o instrucción opcional" style="width: 100%;" />' +
					'</div>' +
				'</div>' +
				'<input type="hidden" name="fields_data[' + newIdx + '][placeholder]" value="" />' +
				'<input type="hidden" name="fields_data[' + newIdx + '][width]" value="full" />' +
				'<input type="hidden" name="fields_data[' + newIdx + '][required]" value="0" />';
			} else if (inputType === 'radio' || inputType === 'select') {
				var rolesOptionsHtml = '';
				var rolesObj = (window.wpatRegForms && window.wpatRegForms.roles) ? window.wpatRegForms.roles : { 'customer': 'Cliente' };
				for (var rSlug in rolesObj) {
					rolesOptionsHtml += '<option value="' + rSlug + '">' + rolesObj[rSlug] + ' (' + rSlug + ')</option>';
				}

				var defaultOpts = (inputType === 'radio') ? 'Sí, No' : 'Opción 1, Opción 2, Opción 3';
				var defaultPlh  = (inputType === 'select') ? 'Selecciona una opción...' : '';

				rowHtml += '<div style="display: grid; grid-template-columns: 2fr 2fr ' + (inputType === 'select' ? '1.5fr ' : '') + '1fr auto; gap: 12px; align-items: flex-end;">' +
					'<div>' +
						'<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 4px;">Pregunta / Etiqueta:</label>' +
						'<input type="text" name="fields_data[' + newIdx + '][label]" value="' + safeLabel + '" class="wpat-input-field-label" style="width: 100%;" />' +
					'</div>' +
					'<div>' +
						'<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 4px;">Opciones (separadas por comas):</label>' +
						'<input type="text" name="fields_data[' + newIdx + '][options_str]" value="' + defaultOpts + '" placeholder="' + defaultOpts + '" style="width: 100%;" />' +
					'</div>';

				if (inputType === 'select') {
					rowHtml += '<div>' +
						'<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 4px;">Placeholder (opcional):</label>' +
						'<input type="text" name="fields_data[' + newIdx + '][placeholder]" value="' + defaultPlh + '" placeholder="Selecciona una opción..." style="width: 100%;" />' +
					'</div>';
				} else {
					rowHtml += '<input type="hidden" name="fields_data[' + newIdx + '][placeholder]" value="" />';
				}

				rowHtml += '<div>' +
						'<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 4px;">Ancho:</label>' +
						'<select name="fields_data[' + newIdx + '][width]" class="wpat-select-field-width" style="width: 100%;">' +
							'<option value="full" selected>100%</option>' +
							'<option value="half">50%</option>' +
						'</select>' +
					'</div>' +
					'<div style="padding-bottom: 8px;">' +
						'<label style="font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">' +
							'<input type="checkbox" name="fields_data[' + newIdx + '][required]" value="1" class="wpat-check-field-req" />' +
							'<span>Obligatorio</span>' +
						'</label>' +
					'</div>' +
				'</div>' +
				'<div class="wpat-reg-cond-box" style="margin-top: 14px; padding: 12px 14px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px;">' +
					'<div style="display: flex; align-items: center; justify-content: space-between;">' +
						'<strong style="font-size: 12px; color: #1e293b; display: flex; align-items: center; gap: 6px;">' +
							'<span class="dashicons dashicons-admin-users" style="font-size: 16px; width: 16px; height: 16px; color: #2563eb;"></span>' +
							'Asignación Condicional de Rol y Metadato (Recargo de Equivalencia / B2B)' +
						'</strong>' +
						'<label style="font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">' +
							'<input type="checkbox" name="fields_data[' + newIdx + '][conditional_role_enabled]" value="1" class="wpat-check-cond-role" />' +
							'<span>Activar asignación de rol condicional</span>' +
						'</label>' +
					'</div>' +
					'<div class="wpat-reg-cond-fields" style="display: none; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-top: 10px;">' +
						'<div>' +
							'<label style="font-size: 11px; font-weight: 600; display: block; margin-bottom: 4px;">Si el usuario elige el valor:</label>' +
							'<input type="text" name="fields_data[' + newIdx + '][conditional_value]" value="' + (inputType === 'radio' ? 'Sí' : 'Opción 1') + '" placeholder="Ej: Sí" style="width: 100%;" />' +
						'</div>' +
						'<div>' +
							'<label style="font-size: 11px; font-weight: 600; display: block; margin-bottom: 4px;">Asignar este Rol al usuario:</label>' +
							'<select name="fields_data[' + newIdx + '][conditional_role]" style="width: 100%;">' +
								rolesOptionsHtml +
							'</select>' +
						'</div>' +
						'<div>' +
							'<label style="font-size: 11px; font-weight: 600; display: block; margin-bottom: 4px;">Guardar en User Meta (Key):</label>' +
							'<input type="text" name="fields_data[' + newIdx + '][meta_key]" value="billing_recargo_equivalencia" placeholder="billing_recargo_equivalencia" style="width: 100%; font-family: monospace; font-size: 12px;" />' +
						'</div>' +
					'</div>' +
				'</div>';
			} else {
				rowHtml += '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px;">' +
					'<div>' +
						'<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 4px;">Etiqueta (Label):</label>' +
						'<input type="text" name="fields_data[' + newIdx + '][label]" value="' + safeLabel + '" class="wpat-input-field-label" style="width: 100%;" />' +
					'</div>' +
					'<div>' +
						'<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 4px;">Placeholder:</label>' +
						'<input type="text" name="fields_data[' + newIdx + '][placeholder]" value="" style="width: 100%;" />' +
					'</div>' +
					'<div>' +
						'<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 4px;">Ancho:</label>' +
						'<select name="fields_data[' + newIdx + '][width]" class="wpat-select-field-width" style="width: 100%;">' +
							'<option value="full" selected>100% (Ancho completo)</option>' +
							'<option value="half">50% (Media columna)</option>' +
						'</select>' +
					'</div>' +
					'<div style="display: flex; align-items: center; padding-top: 18px;">' +
						'<label style="font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">' +
							'<input type="checkbox" name="fields_data[' + newIdx + '][required]" value="1" class="wpat-check-field-req" />' +
							'<span>Campo obligatorio</span>' +
						'</label>' +
					'</div>' +
				'</div>';
			}

			rowHtml += '<input type="hidden" name="fields_data[' + newIdx + '][id]" value="' + fieldId + '" />' +
					'<input type="hidden" name="fields_data[' + newIdx + '][type]" value="' + inputType + '" />' +
					'<input type="hidden" name="fields_data[' + newIdx + '][is_native]" value="' + isNative + '" />' +
				'</div>' +
			'</div>';

			$('.wpat-reg-fields-list').append(rowHtml);
			reindexFields();
			if ($('.wpat-reg-fields-list').data('ui-sortable')) {
				$('.wpat-reg-fields-list').sortable('refresh');
			}
			$('#wpat_reg_add_field_modal').fadeOut(200);

			// Notificar cambio
			if (window.showToast) {
				window.showToast('Campo "' + fieldLabel + '" añadido al formulario', false);
			}
		});

		// Actualizar títulos en vivo al cambiar etiqueta
		$(document).on('input', '.wpat-input-field-label', function() {
			var val = $(this).val();
			$(this).closest('.wpat-reg-field-row').find('.wpat-row-title-lbl').text(val || 'Sin etiqueta');
		});

		// Actualizar badge de obligatorio en vivo
		$(document).on('change', '.wpat-check-field-req', function() {
			var isReq = $(this).is(':checked');
			var $lbl  = $(this).closest('.wpat-reg-field-row').find('.wpat-row-req-lbl');
			if (isReq) {
				$lbl.css({ color: '#ef4444', fontWeight: '700' }).text('Obligatorio');
			} else {
				$lbl.css({ color: '#94a3b8', fontWeight: 'normal' }).text('Opcional');
			}
		});

		// Actualizar indicador de ancho en vivo
		$(document).on('change', '.wpat-select-field-width', function() {
			var w = $(this).val();
			var $lbl = $(this).closest('.wpat-reg-field-row').find('.wpat-row-width-lbl');
			$lbl.text(w === 'half' ? '50% (Media col)' : '100% (Ancho completo)');
		});

		/* ==========================================================================
		   5. ADMIN: Modal de Vista Previa Interactiva del Formulario
		   ========================================================================== */

		// Abrir Modal de Vista Previa
		$(document).on('click', '.wpat-open-preview-btn', function(e) {
			e.preventDefault();
			var $btn      = $(this);
			var formId    = $btn.data('form-id') || '';
			var isEditor  = $btn.data('is-editor') ? 1 : 0;
			var nonce     = (window.wpatRegForms ? window.wpatRegForms.nonce : '');
			var ajaxEndpoint = (window.wpatRegForms ? window.wpatRegForms.ajaxUrl : (window.ajaxurl || ''));

			// Resetear vista del modal
			$('#wpat_preview_loading').show();
			$('#wpat_preview_body_rendered').hide().empty();
			$('.wpat-reg-device-btn').removeClass('active');
			$('.wpat-reg-device-btn[data-width="100%"]').addClass('active');
			$('#wpat_preview_container').css('max-width', '100%');
			$('#wpat_reg_preview_modal').fadeIn(200);

			var payload = {
				action: 'wpat_preview_registration_form',
				form_id: formId,
				security: nonce
			};

			// Si estamos en el editor de formulario, recopilar datos en vivo para previsualizar cambios sin guardar
			if (isEditor) {
				var allowedRoles = [];
				$('input[name="form_allowed_roles[]"]:checked').each(function() {
					allowedRoles.push($(this).val());
				});

				var fields = [];
				$('.wpat-reg-field-row').each(function() {
					var $row = $(this);
					fields.push({
						id: $row.find('input[name*="[id]"]').val() || '',
						type: $row.find('input[name*="[type]"]').val() || 'text',
						label: $row.find('.wpat-input-field-label').val() || '',
						placeholder: $row.find('input[name*="[placeholder]"]').val() || '',
						subtitle: $row.find('input[name*="[subtitle]"]').val() || '',
						options_str: $row.find('input[name*="[options_str]"]').val() || '',
						width: $row.find('.wpat-select-field-width').val() || 'full',
						required: $row.find('.wpat-check-field-req').is(':checked') ? '1' : '0',
						is_native: $row.find('input[name*="[is_native]"]').val() || '0'
					});
				});

				payload.live_data = {
					title: $('#form_title').val() || 'Formulario de Registro',
					desc: $('#form_desc').val() || '',
					button_text: $('#form_button_text').val() || 'Completar Registro',
					role_assignment: $('input[name="form_role_assignment"]:checked').val() || 'fixed',
					default_role: $('#form_default_role').val() || 'customer',
					allowed_roles: allowedRoles,
					approval_required: $('#form_approval_required').is(':checked') ? '1' : '0',
					fields: fields
				};
			}

			$.ajax({
				url: ajaxEndpoint,
				type: 'POST',
				data: payload,
				success: function(response) {
					if (response.success && response.data) {
						$('#wpat_preview_modal_title').text('Vista Previa: ' + (response.data.title || 'Formulario'));
						$('#wpat_preview_shortcode_tag').text(response.data.shortcode || ('[wpat_registration_form id="' + formId + '"]'));
						$('#wpat_preview_body_rendered').html(response.data.html);
						$('#wpat_preview_loading').hide();
						$('#wpat_preview_body_rendered').fadeIn(150);
					} else {
						$('#wpat_preview_loading').hide();
						$('#wpat_preview_body_rendered').html('<div class="wpat-reg-notice wpat-reg-notice-error">' + (response.data && response.data.message ? response.data.message : 'Error al cargar la vista previa.') + '</div>').show();
					}
				},
				error: function() {
					$('#wpat_preview_loading').hide();
					$('#wpat_preview_body_rendered').html('<div class="wpat-reg-notice wpat-reg-notice-error">Error de comunicación con el servidor al generar la vista previa.</div>').show();
				}
			});
		});

		// Selector de dispositivos (Escritorio, Tablet, Móvil)
		$(document).on('click', '.wpat-reg-device-btn', function(e) {
			e.preventDefault();
			var width = $(this).data('width') || '100%';
			$('.wpat-reg-device-btn').removeClass('active');
			$(this).addClass('active');
			$('#wpat_preview_container').css('max-width', width);
		});

		// Cerrar Modal de Vista Previa
		$(document).on('click', '#wpat_reg_close_preview_btn, #wpat_reg_close_preview_backdrop, #wpat_reg_close_preview_bottom_btn', function(e) {
			e.preventDefault();
			$('#wpat_reg_preview_modal').fadeOut(200);
		});

		// Cerrar con tecla Escape
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $('#wpat_reg_preview_modal').is(':visible')) {
				$('#wpat_reg_preview_modal').fadeOut(200);
			}
		});

	});

})(jQuery);
