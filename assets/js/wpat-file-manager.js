/**
 * Script: Explorador de Archivos Web con 2FA - WP Agency Toolkit
 */

jQuery(document).ready(function($) {
	'use strict';

	var currentPath = '';
	var sessionTimerInterval = null;
	var remainingSeconds = 0;

	var getNonce = function() {
		return (typeof wpat_object !== 'undefined' && wpat_object.file_manager_nonce) ? wpat_object.file_manager_nonce : ($('#wpat_fm_nonce').val() || '');
	};

	var getAjaxUrl = function() {
		return (typeof ajaxurl !== 'undefined' && ajaxurl) ? ajaxurl : ((typeof wpat_object !== 'undefined' && wpat_object.ajax_url) ? wpat_object.ajax_url : '/wp-admin/admin-ajax.php');
	};

	// Helper para mostrar avisos toast si window.showToast existe
	var toast = function(msg, isError) {
		if (typeof window.showToast === 'function') {
			window.showToast(msg, isError ? 'error' : false);
		} else {
			alert(msg);
		}
	};

	// Formatear segundos en MM:SS
	var formatTime = function(seconds) {
		var mins = Math.floor(seconds / 60);
		var secs = seconds % 60;
		return (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;
	};

	// Iniciar contador de caducidad de sesión
	var startSessionTimer = function(seconds) {
		clearInterval(sessionTimerInterval);
		remainingSeconds = seconds || 1200; // 20 minutos por defecto
		$('#wpat_fm_timer').text(formatTime(remainingSeconds));

		sessionTimerInterval = setInterval(function() {
			remainingSeconds--;
			if (remainingSeconds <= 0) {
				clearInterval(sessionTimerInterval);
				$('#wpat_fm_timer').text('00:00');
				toast('La sesión de seguridad ha expirado.', true);
				lockSession(false);
			} else {
				$('#wpat_fm_timer').text(formatTime(remainingSeconds));
			}
		}, 1000);
	};

	// =========================================================================
	// 1. GESTIÓN DE AUTENTICACIÓN 2FA
	// =========================================================================

	// Iniciar Asistente de Configuración 2FA
	$(document).on('click', '#wpat_fm_start_setup_btn, #wpat_fm_reconfig_btn', function(e) {
		e.preventDefault();
		var $btn = $(this);
		$btn.prop('disabled', true).text('Generando QR...');

		$.ajax({
			url: getAjaxUrl(),
			type: 'POST',
			data: {
				action: 'wpat_fm_setup_2fa',
				nonce: getNonce()
			},
			success: function(resp) {
				$btn.prop('disabled', false).text('Configurar 2FA');
				if (resp.success && resp.data) {
					$('#wpat_fm_lock_screen').hide();
					$('#wpat_fm_setup_screen').show();
					$('#wpat_fm_secret_display').text(resp.data.secret);
					$('#wpat_fm_otp_uri').val(resp.data.otp_uri);

					// Renderizar QR Code con la librería local
					var qrContainer = document.getElementById('wpat_fm_qrcode_box');
					if (qrContainer) {
						qrContainer.innerHTML = '';
						if (typeof QRCode !== 'undefined') {
							try {
								new QRCode(qrContainer, {
									text: resp.data.otp_uri,
									width: 180,
									height: 180,
									colorDark: '#0f172a',
									colorLight: '#ffffff',
									correctLevel: QRCode.CorrectLevel.M
								});
							} catch (qrErr) {
								console.warn('Fallo render local de QR, usando fallback:', qrErr);
								var safeUri = encodeURIComponent(resp.data.otp_uri);
								qrContainer.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + safeUri + '" alt="QR 2FA" style="width:180px;height:180px;display:block;margin:0 auto;" />';
							}
						}
					}
					$('#wpat_fm_setup_code').focus();
				} else {
					toast(resp.data ? resp.data.message : 'Error al iniciar configuración 2FA.', true);
				}
			},
			error: function() {
				$btn.prop('disabled', false).text('Configurar 2FA');
				toast('Error de conexión con el servidor.', true);
			}
		});
	});

	// Copiar clave secreta al portapapeles
	$(document).on('click', '#wpat_fm_copy_secret_btn', function(e) {
		e.preventDefault();
		var secret = $('#wpat_fm_secret_display').text().trim();
		if (navigator.clipboard) {
			navigator.clipboard.writeText(secret).then(function() {
				toast('Clave secreta copiada al portapapeles.');
			});
		}
	});

	// Confirmar y Activar 2FA
	var submitSetupCode = function() {
		var code = $('#wpat_fm_setup_code').val().trim();
		if (!code) return;

		var $btn = $('#wpat_fm_setup_submit_btn');
		$btn.prop('disabled', true).text('Verificando...');

		$.ajax({
			url: getAjaxUrl(),
			type: 'POST',
			data: {
				action: 'wpat_fm_verify_2fa',
				code: code,
				is_setup: '1',
				nonce: getNonce()
			},
			success: function(resp) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> Confirmar y Desbloquear');
				if (resp.success && resp.data) {
					toast('¡2FA configurado y activado con éxito!');
					$('#wpat_fm_setup_screen').hide();

					// Si hay códigos de rescate, mostrarlos
					if (resp.data.recovery_codes && resp.data.recovery_codes.length > 0) {
						var $codesContainer = $('#wpat_fm_recovery_codes_list');
						$codesContainer.empty();
						resp.data.recovery_codes.forEach(function(c) {
							$codesContainer.append('<span class="wpat-fm-recovery-code-badge">' + c + '</span>');
						});
						$('#wpat_fm_recovery_modal').show();
					} else {
						showFileManager(resp.data.timeout_secs);
					}
				} else {
					toast(resp.data ? resp.data.message : 'Código incorrecto.', true);
				}
			},
			error: function(xhr) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> Confirmar y Desbloquear');
				var err = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : 'Error al verificar el código.';
				toast(err, true);
			}
		});
	};

	$(document).on('click', '#wpat_fm_setup_submit_btn', function(e) {
		e.preventDefault();
		submitSetupCode();
	});

	$(document).on('keydown', '#wpat_fm_setup_code', function(e) {
		if (e.which === 13 || e.keyCode === 13) {
			e.preventDefault();
			e.stopPropagation();
			submitSetupCode();
		}
	});

	// Aceptar y cerrar modal de códigos de rescate
	$(document).on('click', '#wpat_fm_confirm_recovery_btn', function(e) {
		e.preventDefault();
		$('#wpat_fm_recovery_modal').hide();
		showFileManager(20 * 60);
	});

	// Desbloquear sesión existente con 2FA
	var submitUnlockCode = function() {
		var code = $('#wpat_fm_unlock_code').val().trim();
		if (!code) return;

		var $btn = $('#wpat_fm_unlock_submit_btn');
		$btn.prop('disabled', true).text('Comprobando...');

		$.ajax({
			url: getAjaxUrl(),
			type: 'POST',
			data: {
				action: 'wpat_fm_verify_2fa',
				code: code,
				nonce: getNonce()
			},
			success: function(resp) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-unlock"></span> Desbloquear Explorador');
				if (resp.success && resp.data) {
					toast(resp.data.message || 'Sesión desbloqueada con éxito.');
					showFileManager(resp.data.timeout_secs);
				} else {
					toast(resp.data ? resp.data.message : 'Código inválido.', true);
					$('#wpat_fm_unlock_code').val('').focus();
				}
			},
			error: function(xhr) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-unlock"></span> Desbloquear Explorador');
				var err = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : 'Error al verificar.';
				toast(err, true);
			}
		});
	};

	$(document).on('click', '#wpat_fm_unlock_submit_btn', function(e) {
		e.preventDefault();
		submitUnlockCode();
	});

	$(document).on('keydown', '#wpat_fm_unlock_code', function(e) {
		if (e.which === 13 || e.keyCode === 13) {
			e.preventDefault();
			e.stopPropagation();
			submitUnlockCode();
		}
	});

	// Bloquear sesión activa
	var lockSession = function(callAjax) {
		clearInterval(sessionTimerInterval);
		$('#wpat_fm_main_view').hide();
		$('#wpat_fm_setup_screen').hide();
		$('#wpat_fm_lock_screen').show();
		$('#wpat_fm_unlock_code').val('');

		if (callAjax !== false) {
			$.post(getAjaxUrl(), {
				action: 'wpat_fm_lock_session',
				nonce: getNonce()
			}, function() {
				toast('Sesión de explorador bloqueada.');
			});
		}
	};

	$(document).on('click', '#wpat_fm_lock_btn', function(e) {
		e.preventDefault();
		lockSession(true);
	});

	// Mostrar interfaz del explorador tras superar 2FA
	var showFileManager = function(timeoutSecs) {
		$('#wpat_fm_lock_screen').hide();
		$('#wpat_fm_setup_screen').hide();
		$('#wpat_fm_main_view').show();
		startSessionTimer(timeoutSecs || 1200);
		loadDirectory('');
	};

	// =========================================================================
	// 2. NAVEGACIÓN Y CARGA DE DIRECTORIOS (LAZY LOADING)
	// =========================================================================

	var getFileIcon = function(item) {
		if (item.is_dir) {
			return '📁';
		}
		var ext = item.extension;
		if (ext === 'php') return '🐘';
		if (ext === 'js' || ext === 'json') return '⚡';
		if (ext === 'css' || ext === 'scss') return '🎨';
		if (ext === 'html' || ext === 'htm') return '🌐';
		if (ext === 'png' || ext === 'jpg' || ext === 'jpeg' || ext === 'svg' || ext === 'webp' || ext === 'gif') return '🖼️';
		if (ext === 'zip' || ext === 'gz' || ext === 'tar') return '📦';
		if (item.name === 'wp-config.php' || item.name === '.htaccess') return '🔒';
		return '📄';
	};

	var loadDirectory = function(path) {
		currentPath = path || '';
		var $tbody = $('#wpat_fm_tbody');
		$tbody.html('<tr><td colspan="6" style="text-align:center; padding:30px; color:#64748b;">Cargando archivos del directorio...</td></tr>');
		$('#wpat_fm_select_all').prop('checked', false);
		$('#wpat_fm_bulk_actions_bar').hide();

		$.ajax({
			url: getAjaxUrl(),
			type: 'POST',
			data: {
				action: 'wpat_fm_list_dir',
				path: currentPath,
				nonce: getNonce()
			},
			success: function(resp) {
				if (resp.success && resp.data) {
					renderDirectory(resp.data);
				} else {
					if (resp.data && resp.data.locked) {
						lockSession(false);
					}
					var err = (resp.data && resp.data.message) ? resp.data.message : 'Error al listar directorio.';
					toast(err, true);
				}
			},
			error: function(xhr) {
				var err = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Error de conexión al cargar directorio.';
				toast(err, true);
			}
		});
	};

	var renderDirectory = function(data) {
		// 1. Renderizar Migas de Pan (Breadcrumbs)
		var $bc = $('#wpat_fm_breadcrumbs');
		$bc.empty();
		if (data.breadcrumbs && data.breadcrumbs.length > 0) {
			data.breadcrumbs.forEach(function(b, idx) {
				if (idx > 0) {
					$bc.append('<span class="wpat-fm-breadcrumb-sep">/</span>');
				}
				$bc.append('<a class="wpat-fm-breadcrumb-item" data-path="' + b.path + '">' + b.name + '</a>');
			});
		}

		// 2. Renderizar Filas de Tabla
		var $tbody = $('#wpat_fm_tbody');
		$tbody.empty();

		// Fila para subir de nivel si no estamos en la raíz
		if (currentPath !== '') {
			var parentPath = currentPath.indexOf('/') !== -1 ? currentPath.substring(0, currentPath.lastIndexOf('/')) : '';
			$tbody.append('<tr class="wpat-fm-row" data-name="..">' +
				'<td></td>' +
				'<td><a class="wpat-fm-item-name is-dir" data-path="' + parentPath + '"><span class="wpat-fm-icon">⤴️</span> .. (Subir un nivel)</a></td>' +
				'<td>—</td><td>—</td><td>—</td><td></td>' +
			'</tr>');
		}

		var allItems = (data.directories || []).concat(data.files || []);

		if (allItems.length === 0) {
			$tbody.append('<tr><td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">Carpeta vacía</td></tr>');
		} else {
			allItems.forEach(function(item) {
				var icon = getFileIcon(item);
				var actionsHtml = '<div class="wpat-fm-actions-cell">';

				// 1. Ver código (solo lectura) con icono de ojo
				if (!item.is_dir && item.is_editable) {
					actionsHtml += '<button type="button" class="wpat-fm-action-btn view" data-path="' + item.rel_path + '" title="Ver código (Solo Lectura)"><span class="dashicons dashicons-visibility"></span></button>';
					actionsHtml += '<button type="button" class="wpat-fm-action-btn edit" data-path="' + item.rel_path + '" title="Editar archivo"><span class="dashicons dashicons-edit"></span></button>';
				}

				// 2. Cambiar permisos (CHMOD)
				actionsHtml += '<button type="button" class="wpat-fm-action-btn chmod" data-path="' + item.rel_path + '" data-name="' + item.name + '" data-perms="' + item.perms + '" data-is-dir="' + (item.is_dir ? '1' : '0') + '" title="Cambiar permisos (CHMOD)"><span class="dashicons dashicons-admin-network"></span></button>';

				// 3. Descargar
				actionsHtml += '<button type="button" class="wpat-fm-action-btn download" data-path="' + item.rel_path + '" data-is-dir="' + (item.is_dir ? '1' : '0') + '" title="Descargar"><span class="dashicons dashicons-download"></span></button>';

				// 4. Renombrar
				actionsHtml += '<button type="button" class="wpat-fm-action-btn rename" data-path="' + item.rel_path + '" data-name="' + item.name + '" title="Renombrar"><span class="dashicons dashicons-tag"></span></button>';

				// 5. Eliminar
				actionsHtml += '<button type="button" class="wpat-fm-action-btn delete" data-path="' + item.rel_path + '" data-name="' + item.name + '" title="Eliminar"><span class="dashicons dashicons-trash"></span></button>';

				actionsHtml += '</div>';

				var clickClass = item.is_dir ? 'is-dir' : (item.is_editable ? 'is-editable' : '');

				var rowHtml = '<tr class="wpat-fm-row" data-name="' + item.name.toLowerCase() + '">' +
					'<td style="text-align:center;"><input type="checkbox" class="wpat-fm-row-checkbox" data-path="' + item.rel_path + '" data-name="' + item.name + '" data-perms="' + item.perms + '" data-is-dir="' + (item.is_dir ? '1' : '0') + '" /></td>' +
					'<td><a class="wpat-fm-item-name ' + clickClass + '" data-path="' + item.rel_path + '">' +
						'<span class="wpat-fm-icon">' + icon + '</span> <span>' + item.name + '</span>' +
					'</a></td>' +
					'<td style="color:#64748b; font-size:12px;">' + item.size_fmt + '</td>' +
					'<td style="color:#64748b; font-size:12px; font-family:monospace;">' + item.perms + '</td>' +
					'<td style="color:#64748b; font-size:12px;">' + item.mtime + '</td>' +
					'<td>' + actionsHtml + '</td>' +
				'</tr>';

				$tbody.append(rowHtml);
			});
		}

		// 3. Resumen en pie
		$('#wpat_fm_footer_count').text((data.directories ? data.directories.length : 0) + ' carpetas, ' + (data.files ? data.files.length : 0) + ' archivos');
		$('#wpat_fm_footer_path').text('Ruta: /' + currentPath);
	};

	// Navegar mediante clic en carpeta
	$(document).on('click', '.wpat-fm-item-name.is-dir', function(e) {
		e.preventDefault();
		var targetPath = $(this).attr('data-path');
		loadDirectory(targetPath);
	});

	// Navegar mediante Breadcrumbs
	$(document).on('click', '.wpat-fm-breadcrumb-item', function(e) {
		e.preventDefault();
		var targetPath = $(this).attr('data-path');
		loadDirectory(targetPath);
	});

	// Recargar directorio actual
	$(document).on('click', '#wpat_fm_refresh_btn', function(e) {
		e.preventDefault();
		loadDirectory(currentPath);
	});

	// Filtrar archivos en tiempo real con buscador
	$(document).on('keyup input search', '#wpat_fm_search', function() {
		var query = $(this).val().toLowerCase().trim();
		$('#wpat_fm_tbody tr.wpat-fm-row').each(function() {
			var name = $(this).attr('data-name') || '';
			if (name === '..' || !query || name.indexOf(query) !== -1) {
				$(this).show();
			} else {
				$(this).hide();
			}
		});
	});

	// =========================================================================
	// 3. SELECCIÓN MÚLTIPLE Y ACCIONES EN LOTE (BULK ACTIONS)
	// =========================================================================

	var getSelectedPaths = function() {
		var paths = [];
		$('.wpat-fm-row-checkbox:checked').each(function() {
			var p = $(this).attr('data-path');
			if (typeof p !== 'undefined') {
				paths.push(p);
			}
		});
		return paths;
	};

	var updateBulkActionsBar = function() {
		var selected = getSelectedPaths();
		if (selected.length > 0) {
			$('#wpat_fm_bulk_count').text(selected.length + (selected.length === 1 ? ' elemento seleccionado' : ' elementos seleccionados'));
			$('#wpat_fm_bulk_actions_bar').slideDown(150);
		} else {
			$('#wpat_fm_bulk_actions_bar').slideUp(150);
		}
	};

	$(document).on('change', '#wpat_fm_select_all', function() {
		var checked = $(this).is(':checked');
		$('.wpat-fm-row-checkbox').prop('checked', checked);
		updateBulkActionsBar();
	});

	$(document).on('change', '.wpat-fm-row-checkbox', function() {
		var total = $('.wpat-fm-row-checkbox').length;
		var checkedCount = $('.wpat-fm-row-checkbox:checked').length;
		$('#wpat_fm_select_all').prop('checked', total > 0 && total === checkedCount);
		updateBulkActionsBar();
	});

	$(document).on('click', '#wpat_fm_bulk_clear_btn', function(e) {
		e.preventDefault();
		$('.wpat-fm-row-checkbox, #wpat_fm_select_all').prop('checked', false);
		updateBulkActionsBar();
	});

	// Descarga en lote de selección
	$(document).on('click', '#wpat_fm_bulk_download_btn', function(e) {
		e.preventDefault();
		var paths = getSelectedPaths();
		if (paths.length === 0) return;

		var $btn = $(this);
		$btn.prop('disabled', true).text('Empaquetando ZIP...');

		$.ajax({
			url: getAjaxUrl(),
			type: 'POST',
			data: {
				action: 'wpat_fm_download_batch',
				paths: paths,
				nonce: getNonce()
			},
			success: function(resp) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Descargar ZIP');
				if (resp.success && resp.data && resp.data.download_url) {
					window.location.href = resp.data.download_url;
					toast('Descarga de ' + resp.data.count + ' elemento(s) iniciada.');
				} else {
					var err = (resp.data && resp.data.message) ? resp.data.message : 'Error al generar descarga en lote.';
					toast(err, true);
				}
			},
			error: function(xhr) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Descargar ZIP');
				var err = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Error al procesar descarga en lote.';
				toast(err, true);
			}
		});
	});

	// Eliminación en lote de selección
	$(document).on('click', '#wpat_fm_bulk_delete_btn', function(e) {
		e.preventDefault();
		var paths = getSelectedPaths();
		if (paths.length === 0) return;

		if (!confirm('¿Seguro que deseas eliminar los ' + paths.length + ' elementos seleccionados?\nEsta acción no se puede deshacer.')) {
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true).text('Eliminando...');

		$.ajax({
			url: getAjaxUrl(),
			type: 'POST',
			data: {
				action: 'wpat_fm_delete_batch',
				paths: paths,
				nonce: getNonce()
			},
			success: function(resp) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Eliminar');
				if (resp.success) {
					toast(resp.data ? resp.data.message : 'Elementos eliminados con éxito.');
					loadDirectory(currentPath);
				} else {
					var err = (resp.data && resp.data.message) ? resp.data.message : 'Error al eliminar selección.';
					toast(err, true);
				}
			},
			error: function(xhr) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Eliminar');
				var err = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Error al eliminar selección.';
				toast(err, true);
			}
		});
	});

	// =========================================================================
	// 4. GESTIÓN DE PERMISOS (CHMOD INDIVIDUAL Y EN LOTE)
	// =========================================================================

	var currentChmodTargets = [];

	var syncCheckboxesFromOctal = function(octalStr) {
		var clean = (octalStr || '').replace(/^0+/, '');
		while (clean.length < 3) clean = '0' + clean;
		if (clean.length > 3) clean = clean.substring(clean.length - 3);

		var u = parseInt(clean.charAt(0), 10) || 0;
		var g = parseInt(clean.charAt(1), 10) || 0;
		var o = parseInt(clean.charAt(2), 10) || 0;

		$('.wpat-fm-perm-check[data-scope="u"]').each(function() {
			var bit = parseInt($(this).attr('data-bit'), 10);
			$(this).prop('checked', (u & bit) !== 0);
		});
		$('.wpat-fm-perm-check[data-scope="g"]').each(function() {
			var bit = parseInt($(this).attr('data-bit'), 10);
			$(this).prop('checked', (g & bit) !== 0);
		});
		$('.wpat-fm-perm-check[data-scope="o"]').each(function() {
			var bit = parseInt($(this).attr('data-bit'), 10);
			$(this).prop('checked', (o & bit) !== 0);
		});
	};

	var syncOctalFromCheckboxes = function() {
		var calcScope = function(scope) {
			var val = 0;
			$('.wpat-fm-perm-check[data-scope="' + scope + '"]:checked').each(function() {
				val += parseInt($(this).attr('data-bit'), 10);
			});
			return val;
		};

		var oct = '0' + calcScope('u') + calcScope('g') + calcScope('o');
		$('#wpat_fm_chmod_octal').val(oct);
	};

	$(document).on('change', '.wpat-fm-perm-check', function() {
		syncOctalFromCheckboxes();
	});

	$(document).on('input keyup', '#wpat_fm_chmod_octal', function() {
		syncCheckboxesFromOctal($(this).val().trim());
	});

	// Abrir modal CHMOD para un solo elemento
	$(document).on('click', '.wpat-fm-action-btn.chmod', function(e) {
		e.preventDefault();
		var path = $(this).attr('data-path');
		var name = $(this).attr('data-name');
		var perms = $(this).attr('data-perms') || '0644';
		var isDir = $(this).attr('data-is-dir') === '1';

		currentChmodTargets = [path];
		$('#wpat_fm_chmod_title').text('Cambiar Permisos (CHMOD)');
		$('#wpat_fm_chmod_target_desc').html('Modificando: <strong>' + name + '</strong>');

		if (isDir) {
			$('#wpat_fm_chmod_recursive_wrap').show();
			$('#wpat_fm_chmod_recursive').prop('checked', false);
		} else {
			$('#wpat_fm_chmod_recursive_wrap').hide();
		}

		$('#wpat_fm_chmod_octal').val(perms);
		syncCheckboxesFromOctal(perms);
		$('#wpat_fm_chmod_modal').show();
	});

	// Abrir modal CHMOD para selección múltiple
	$(document).on('click', '#wpat_fm_bulk_chmod_btn', function(e) {
		e.preventDefault();
		var paths = getSelectedPaths();
		if (paths.length === 0) return;

		currentChmodTargets = paths;
		$('#wpat_fm_chmod_title').text('Cambiar Permisos en Lote');
		$('#wpat_fm_chmod_target_desc').html('Aplicando permisos a <strong>' + paths.length + ' elementos seleccionados</strong>');

		var hasDir = false;
		$('.wpat-fm-row-checkbox:checked').each(function() {
			if ($(this).attr('data-is-dir') === '1') {
				hasDir = true;
			}
		});

		if (hasDir) {
			$('#wpat_fm_chmod_recursive_wrap').show();
			$('#wpat_fm_chmod_recursive').prop('checked', false);
		} else {
			$('#wpat_fm_chmod_recursive_wrap').hide();
		}

		$('#wpat_fm_chmod_octal').val('0755');
		syncCheckboxesFromOctal('0755');
		$('#wpat_fm_chmod_modal').show();
	});

	// Guardar permisos CHMOD
	$(document).on('click', '#wpat_fm_save_chmod_btn', function(e) {
		e.preventDefault();
		if (currentChmodTargets.length === 0) return;

		var mode = $('#wpat_fm_chmod_octal').val().trim();
		var recursive = $('#wpat_fm_chmod_recursive').is(':checked') ? '1' : '0';

		var $btn = $(this);
		$btn.prop('disabled', true).text('Guardando...');

		$.ajax({
			url: getAjaxUrl(),
			type: 'POST',
			data: {
				action: 'wpat_fm_chmod_item',
				paths: currentChmodTargets,
				mode: mode,
				recursive: recursive,
				nonce: getNonce()
			},
			success: function(resp) {
				$btn.prop('disabled', false).text('Guardar Permisos');
				if (resp.success) {
					toast(resp.data ? resp.data.message : 'Permisos actualizados con éxito.');
					$('#wpat_fm_chmod_modal').hide();
					loadDirectory(currentPath);
				} else {
					var err = (resp.data && resp.data.message) ? resp.data.message : 'Error al modificar permisos.';
					toast(err, true);
				}
			},
			error: function(xhr) {
				$btn.prop('disabled', false).text('Guardar Permisos');
				var err = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Error al modificar permisos.';
				toast(err, true);
			}
		});
	});

	$(document).on('click', '#wpat_fm_close_chmod_btn, #wpat_fm_cancel_chmod_btn', function(e) {
		e.preventDefault();
		$('#wpat_fm_chmod_modal').hide();
	});

	// =========================================================================
	// 5. OPERACIONES DE ARCHIVOS (CREAR, RENOMBRAR, ELIMINAR, SUBIR, DESCARGAR)
	// =========================================================================

	// Nuevo Archivo o Nueva Carpeta
	$(document).on('click', '#wpat_fm_new_file_btn, #wpat_fm_new_dir_btn', function(e) {
		e.preventDefault();
		var isDir = $(this).attr('id') === 'wpat_fm_new_dir_btn';
		var promptMsg = isDir ? 'Introduce el nombre de la nueva carpeta:' : 'Introduce el nombre del nuevo archivo (ej. robots.txt):';
		var name = prompt(promptMsg);
		if (!name || !name.trim()) return;

		$.ajax({
			url: getAjaxUrl(),
			type: 'POST',
			data: {
				action: 'wpat_fm_create_item',
				parent_path: currentPath,
				name: name.trim(),
				type: isDir ? 'dir' : 'file',
				nonce: getNonce()
			},
			success: function(resp) {
				if (resp.success) {
					toast(resp.data ? resp.data.message : 'Elemento creado con éxito.');
					loadDirectory(currentPath);
				} else {
					var err = (resp.data && resp.data.message) ? resp.data.message : 'Error al crear elemento.';
					toast(err, true);
				}
			},
			error: function(xhr) {
				var err = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Error de conexión al crear.';
				toast(err, true);
			}
		});
	});

	// Renombrar
	$(document).on('click', '.wpat-fm-action-btn.rename', function(e) {
		e.preventDefault();
		var path = $(this).attr('data-path');
		var oldName = $(this).attr('data-name');
		var newName = prompt('Introduce el nuevo nombre:', oldName);
		if (!newName || !newName.trim() || newName.trim() === oldName) return;

		$.ajax({
			url: getAjaxUrl(),
			type: 'POST',
			data: {
				action: 'wpat_fm_rename_item',
				old_path: path,
				new_name: newName.trim(),
				nonce: getNonce()
			},
			success: function(resp) {
				if (resp.success) {
					toast(resp.data ? resp.data.message : 'Renombrado con éxito.');
					loadDirectory(currentPath);
				} else {
					var err = (resp.data && resp.data.message) ? resp.data.message : 'Error al renombrar.';
					toast(err, true);
				}
			},
			error: function(xhr) {
				var err = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Error de conexión al renombrar.';
				toast(err, true);
			}
		});
	});

	// Eliminar
	$(document).on('click', '.wpat-fm-action-btn.delete', function(e) {
		e.preventDefault();
		var path = $(this).attr('data-path');
		var name = $(this).attr('data-name');
		if (!confirm('¿Seguro que deseas eliminar "' + name + '"?\nEsta acción no se puede deshacer.')) return;

		$.ajax({
			url: getAjaxUrl(),
			type: 'POST',
			data: {
				action: 'wpat_fm_delete_item',
				path: path,
				nonce: getNonce()
			},
			success: function(resp) {
				if (resp.success) {
					toast(resp.data ? resp.data.message : 'Eliminado correctamente.');
					loadDirectory(currentPath);
				} else {
					var err = (resp.data && resp.data.message) ? resp.data.message : 'Error al eliminar.';
					toast(err, true);
				}
			},
			error: function(xhr) {
				var err = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Error de conexión al eliminar.';
				toast(err, true);
			}
		});
	});

	// Subir Archivo
	$(document).on('click', '#wpat_fm_upload_btn', function(e) {
		e.preventDefault();
		$('#wpat_fm_file_input').trigger('click');
	});

	$(document).on('change', '#wpat_fm_file_input', function() {
		var file = this.files[0];
		if (!file) return;

		var formData = new FormData();
		formData.append('action', 'wpat_fm_upload_file');
		formData.append('target_path', currentPath);
		formData.append('nonce', getNonce());
		formData.append('file', file);

		var $uploadBtn = $('#wpat_fm_upload_btn');
		$uploadBtn.text('Subiendo...').prop('disabled', true);

		$.ajax({
			url: getAjaxUrl(),
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(resp) {
				$uploadBtn.html('<span class="dashicons dashicons-upload"></span> Subir Archivo').prop('disabled', false);
				$('#wpat_fm_file_input').val('');
				if (resp.success) {
					toast(resp.data ? resp.data.message : 'Archivo subido.');
					loadDirectory(currentPath);
				} else {
					var err = (resp.data && resp.data.message) ? resp.data.message : 'Error al subir archivo.';
					toast(err, true);
				}
			},
			error: function(xhr) {
				$uploadBtn.html('<span class="dashicons dashicons-upload"></span> Subir Archivo').prop('disabled', false);
				$('#wpat_fm_file_input').val('');
				var err = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Error de conexión durante la subida.';
				toast(err, true);
			}
		});
	});

	// Descargar elemento individual o carpeta actual
	$(document).on('click', '.wpat-fm-action-btn.download, #wpat_fm_download_zip_btn', function(e) {
		e.preventDefault();
		var path = $(this).attr('data-path');
		if (typeof path === 'undefined') {
			path = currentPath; // Descargar carpeta actual si se usa el botón de toolbar
		}

		var $btn = $(this);
		$btn.prop('disabled', true);

		$.ajax({
			url: getAjaxUrl(),
			type: 'POST',
			data: {
				action: 'wpat_fm_download_item',
				path: path,
				nonce: getNonce()
			},
			success: function(resp) {
				$btn.prop('disabled', false);
				if (resp.success && resp.data && resp.data.download_url) {
					window.location.href = resp.data.download_url;
				} else {
					var err = (resp.data && resp.data.message) ? resp.data.message : 'No se pudo preparar la descarga.';
					toast(err, true);
				}
			},
			error: function(xhr) {
				$btn.prop('disabled', false);
				var err = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Error al descargar elemento.';
				toast(err, true);
			}
		});
	});

	// =========================================================================
	// 6. EDITOR Y VISUALIZADOR DE CÓDIGO (CON COMPROBACIÓN DE SINTAXIS PHP)
	// =========================================================================

	var currentEditingPath = '';

	var openCodeEditor = function(path, isReadOnly) {
		currentEditingPath = path;
		$('#wpat_fm_syntax_alert').hide();
		$('#wpat_fm_editor_filename').text('Cargando...');
		$('#wpat_fm_editor_textarea').val('Cargando contenido...');

		if (isReadOnly) {
			$('#wpat_fm_viewonly_badge').show();
			$('#wpat_fm_editor_textarea').prop('readonly', true).css('background', '#0b1120');
			$('#wpat_fm_save_file_btn').hide();
		} else {
			$('#wpat_fm_viewonly_badge').hide();
			$('#wpat_fm_editor_textarea').prop('readonly', false).css('background', '');
			$('#wpat_fm_save_file_btn').show();
		}

		$('#wpat_fm_editor_modal').show();

		$.ajax({
			url: getAjaxUrl(),
			type: 'POST',
			data: {
				action: 'wpat_fm_get_file',
				path: path,
				nonce: getNonce()
			},
			success: function(resp) {
				if (resp.success && resp.data) {
					$('#wpat_fm_editor_filename').text(resp.data.filename);
					$('#wpat_fm_editor_path_info').text('Ruta: /' + resp.data.path + ' (' + resp.data.size_fmt + ')');
					$('#wpat_fm_editor_textarea').val(resp.data.content);

					if (resp.data.is_critical) {
						$('#wpat_fm_critical_badge').show();
					} else {
						$('#wpat_fm_critical_badge').hide();
					}

					if (!isReadOnly) {
						if (!resp.data.is_writable) {
							$('#wpat_fm_save_file_btn').prop('disabled', true).text('Solo Lectura');
						} else {
							$('#wpat_fm_save_file_btn').prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Guardar con Auto-Backup');
						}
					}
				} else {
					var err = (resp.data && resp.data.message) ? resp.data.message : 'Error al abrir archivo.';
					toast(err, true);
					$('#wpat_fm_editor_modal').hide();
				}
			},
			error: function(xhr) {
				var err = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Error al abrir archivo.';
				toast(err, true);
				$('#wpat_fm_editor_modal').hide();
			}
		});
	};

	// Clic en ver código (solo lectura con icono de ojo)
	$(document).on('click', '.wpat-fm-action-btn.view', function(e) {
		e.preventDefault();
		var path = $(this).attr('data-path');
		openCodeEditor(path, true);
	});

	// Clic en editar
	$(document).on('click', '.wpat-fm-action-btn.edit', function(e) {
		e.preventDefault();
		var path = $(this).attr('data-path');
		openCodeEditor(path, false);
	});

	// Clic en nombre de archivo editable (abre editor)
	$(document).on('click', '.wpat-fm-item-name.is-editable', function(e) {
		e.preventDefault();
		var path = $(this).attr('data-path');
		openCodeEditor(path, false);
	});

	// Soporte de tabulación en textarea
	$(document).on('keydown', '#wpat_fm_editor_textarea', function(e) {
		if (e.key === 'Tab') {
			e.preventDefault();
			var start = this.selectionStart;
			var end = this.selectionEnd;
			this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
			this.selectionStart = this.selectionEnd = start + 4;
		}
	});

	// Guardar archivo editado
	$(document).on('click', '#wpat_fm_save_file_btn', function(e) {
		e.preventDefault();
		if (!currentEditingPath) return;

		var $btn = $(this);
		$btn.prop('disabled', true).text('Verificando y Guardando...');
		$('#wpat_fm_syntax_alert').hide();

		var content = $('#wpat_fm_editor_textarea').val();

		$.ajax({
			url: getAjaxUrl(),
			type: 'POST',
			data: {
				action: 'wpat_fm_save_file',
				path: currentEditingPath,
				content: content,
				nonce: getNonce()
			},
			success: function(resp) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Guardar con Auto-Backup');
				if (resp.success) {
					toast(resp.data ? resp.data.message : 'Guardado con éxito.');
					$('#wpat_fm_editor_modal').hide();
					loadDirectory(currentPath);
				} else {
					if (resp.data && resp.data.syntax_error) {
						$('#wpat_fm_syntax_alert').text('⚠️ ' + resp.data.message).show();
					} else {
						var err = (resp.data && resp.data.message) ? resp.data.message : 'Error al guardar.';
						toast(err, true);
					}
				}
			},
			error: function(xhr) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Guardar con Auto-Backup');
				var err = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Error al guardar archivo.';
				toast(err, true);
			}
		});
	});

	// Cerrar editor
	$(document).on('click', '#wpat_fm_close_editor_btn, #wpat_fm_cancel_editor_btn', function(e) {
		e.preventDefault();
		$('#wpat_fm_editor_modal').hide();
	});

	// Función global de inicialización (usable tanto en carga normal como vía AJAX/SPA)
	window.wpatInitFileManager = function() {
		if ($('#wpat_fm_main_view').is(':visible')) {
			showFileManager(1200);
		} else if ($('#wpat_fm_setup_screen').is(':visible')) {
			if ($('#wpat_fm_qrcode_box').is(':empty')) {
				$('#wpat_fm_reconfig_btn').trigger('click');
			}
		}
	};

	window.wpatInitFileManager();

});


