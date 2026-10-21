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
		   4. ADMIN: Constructor de Campos (Añadir / Eliminar Campos en Formulario)
		   ========================================================================== */

		// Eliminar campo
		$(document).on('click', '.wpat-reg-remove-field-btn', function(e) {
			e.preventDefault();
			if (confirm('¿Eliminar este campo del formulario?')) {
				$(this).closest('.wpat-reg-field-row').slideUp(200, function() {
					$(this).remove();
				});
			}
		});

		// Desplegar/plegar opciones avanzadas de campo
		$(document).on('click', '.wpat-reg-toggle-field-btn', function(e) {
			e.preventDefault();
			var $row = $(this).closest('.wpat-reg-field-row');
			$row.find('.wpat-reg-field-options').slideToggle(200);
		});

	});

})(jQuery);
