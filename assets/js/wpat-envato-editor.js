/**
 * Script de integración del Importador de Kits para el Editor de Elementor - WP Agency Toolkit
 */
(function($) {
	
	// Función para obtener la configuración localized de forma segura en ambos contextos (parent e iframe)
	function getWpatData() {
		if (typeof wpatEnvatoEditor !== 'undefined') {
			return wpatEnvatoEditor;
		} else if (window.parent && typeof window.parent.wpatEnvatoEditor !== 'undefined') {
			return window.parent.wpatEnvatoEditor;
		}
		return { kits: {}, ajaxurl: '', security: '' };
	}

	// Función para inyectar el botón en caliente en las áreas de agregar sección de Elementor
	function injectWpatButton() {
		// 1. Intentar buscar en el documento actual (si se ejecuta dentro del iframe)
		var $areas = $('.elementor-add-section-area, .elementor-add-new-section');
		
		// 2. Si no hay áreas en este documento, buscar dentro del iframe (si se ejecuta en el padre)
		if ($areas.length === 0) {
			var $iframe = $('#elementor-preview-iframe');
			if ($iframe.length) {
				$areas = $iframe.contents().find('.elementor-add-section-area, .elementor-add-new-section');
			}
		}

		if ($areas.length === 0) {
			return;
		}

		$areas.each(function() {
			var $area = $(this);
			
			// Si ya se ha inyectado el botón, lo omitimos
			if ($area.find('.wpat-elementor-editor-btn').length) {
				return;
			}

			// Localizar el botón de la carpeta (añadir plantilla)
			var $addTemplateBtn = $area.find('.elementor-add-template-button');
			if ($addTemplateBtn.length) {
				// Crear nuestro botón con el icono de la llave inglesa (Toolkit)
				var $wpatBtn = $('<div class="elementor-add-section-area-button wpat-elementor-editor-btn" title="WP Agency Toolkit - Insertar plantilla de Kit">' +
					'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>' +
					'</div>');

				// Posicionarlo al lado del botón de la carpeta
				$addTemplateBtn.after($wpatBtn);

				// Acción al hacer clic
				$wpatBtn.on('click', function(e) {
					e.preventDefault();
					openWpatModal();
				});
			}
		});
	}

	// Ejecutar periódicamente la detección para inyectar en secciones dinámicas
	setInterval(injectWpatButton, 1000);

	/**
	 * Abre el modal de selección de plantillas de WP Agency Toolkit.
	 */
	function openWpatModal() {
		// Si ya existe el modal, lo removemos para evitar duplicados
		$('#wpat-editor-modal-overlay').remove();

		// Cargar datos localized de forma segura
		var wpatData = getWpatData();
		var kits = wpatData.kits || {};
		var kitsKeys = Object.keys(kits);

		// Construir HTML del modal
		var modalHtml = '<div id="wpat-editor-modal-overlay">';
		modalHtml += '  <div id="wpat-editor-modal-container">';
		modalHtml += '    <div class="wpat-modal-header">';
		modalHtml += '      <div class="wpat-modal-header-title">';
		modalHtml += '        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>';
		modalHtml += '        <span>Mis Kits de Plantillas</span>';
		modalHtml += '      </div>';
		modalHtml += '      <button type="button" class="wpat-modal-close-btn">&times;</button>';
		modalHtml += '    </div>';
		
		modalHtml += '    <div class="wpat-modal-body">';
		
		if (kitsKeys.length === 0) {
			modalHtml += '      <div class="wpat-modal-empty-state">';
			modalHtml += '        <p>No tienes ningún kit de plantilla instalado todavía.</p>';
			modalHtml += '        <p style="font-size: 13px; color: #94a3b8; margin-top: 5px;">Sube tus archivos ZIP en el menú WP Agency Toolkit -> Importador de Kits.</p>';
			modalHtml += '      </div>';
		} else {
			modalHtml += '      <div class="wpat-modal-sidebar">';
			modalHtml += '        <h4 style="margin: 0 0 10px 0; font-size:12px; text-transform:uppercase; color:#94a3b8; font-weight:600;">Selecciona un Kit</h4>';
			modalHtml += '        <ul class="wpat-modal-kits-list">';
			
			kitsKeys.forEach(function(key, index) {
				var kit = kits[key];
				var activeClass = index === 0 ? 'active' : '';
				modalHtml += '        <li class="' + activeClass + '" data-slug="' + kit.slug + '">' + kit.title + '</li>';
			});
			
			modalHtml += '        </ul>';
			modalHtml += '      </div>';
			
			modalHtml += '      <div class="wpat-modal-content">';
			modalHtml += '        <div class="wpat-modal-templates-grid">';
			// Las plantillas del primer kit se cargarán dinámicamente aquí
			modalHtml += '        </div>';
			modalHtml += '      </div>';
		}
		
		modalHtml += '    </div>';
		modalHtml += '  </div>';
		modalHtml += '</div>';

		$('body').append(modalHtml);

		// Renderizar plantillas del primer kit seleccionado
		if (kitsKeys.length > 0) {
			loadTemplates(kitsKeys[0]);
		}

		// Evento: Cambiar de Kit en el menú lateral
		$('.wpat-modal-kits-list').on('click', 'li', function() {
			$('.wpat-modal-kits-list li').removeClass('active');
			$(this).addClass('active');
			var slug = $(this).data('slug');
			loadTemplates(slug);
		});

		// Evento: Cerrar modal
		$('.wpat-modal-close-btn, #wpat-editor-modal-overlay').on('click', function(e) {
			if (e.target === this || $(this).hasClass('wpat-modal-close-btn')) {
				$('#wpat-editor-modal-overlay').fadeOut(200, function() {
					$(this).remove();
				});
			}
		});

		// Evento: Insertar plantilla en el lienzo (Delegado globalmente)
		$(document).on('click', '.wpat-insert-template-btn', function(e) {
			e.preventDefault();
			
			// Cerrar el lightbox de vista previa si estuviera abierto
			$('.wpat-preview-lightbox-overlay').remove();
			var $btn = $(this);
			var kitSlug = $btn.data('kit');
			var tplId   = $btn.data('id');

			// Obtener tipo y título de la plantilla desde la data localizada
			var wpatDataLocal = getWpatData();
			var kits = wpatDataLocal.kits || {};
			var kit = kits[kitSlug];
			var targetTpl = null;
			if (kit && kit.templates) {
				kit.templates.forEach(function(tpl) {
					if (tpl.id === tplId) {
						targetTpl = tpl;
					}
				});
			}
			var tplType = 'section';
			if (targetTpl && targetTpl.type === 'page') {
				tplType = 'page';
			}
			var tplTitle = targetTpl ? targetTpl.title : 'Template';

			$btn.prop('disabled', true).text('Insertando...');

			// Mostrar overlay de barra de progreso
			var loaderHtml = '<div class="wpat-editor-modal-loader-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.92); display: flex; flex-direction: column; justify-content: center; align-items: center; z-index: 10000; border-radius: 8px; font-family: sans-serif;">';
			loaderHtml += '  <div style="font-weight: 600; font-size: 16px; color: #4f46e5; margin-bottom: 12px;">Importando plantilla del kit...</div>';
			loaderHtml += '  <div style="width: 280px; height: 10px; background: #e2e8f0; border-radius: 5px; overflow: hidden; margin-bottom: 8px; position: relative;">';
			loaderHtml += '    <div class="wpat-import-progress-bar" style="width: 0%; height: 100%; background: #4f46e5; transition: width 0.3s ease;"></div>';
			loaderHtml += '  </div>';
			loaderHtml += '  <div class="wpat-import-progress-text" style="font-size: 13px; color: #64748b; font-weight: 500;">0% completado (sincronizando recursos)</div>';
			loaderHtml += '</div>';
			
			$('#wpat-editor-modal-container').css('position', 'relative').append(loaderHtml);

			// Simular progreso de descarga
			var progress = 0;
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
				url: wpatData.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpat_import_and_get_template_id',
					security: wpatData.security,
					kit_slug: kitSlug,
					template_id: tplId
				},
				success: function(response) {
					console.log("WPAT AJAX response:", response);
					
					if (response.success) {
						if (response.data.type === 'global_styles') {
							clearInterval(progressInterval);
							$('.wpat-import-progress-bar').css('width', '100%');
							$('.wpat-import-progress-text').text('100% completado');
							
							setTimeout(function() {
								$('.wpat-editor-modal-loader-overlay').remove();
								$btn.prop('disabled', false).text('Insertar');
								alert('Los estilos globales (colores y tipografías) han sido aplicados con éxito. La página se recargará para aplicarlos.');
								window.parent.location.reload();
							}, 600);
							return;
						}

						var templatePostId = response.data.template_id;
						
						// Inyectar en el lienzo de Elementor utilizando la API nativa de comandos oficial
						runInsertTemplateFallback(templatePostId, progressInterval, $btn, tplType, tplTitle);
					} else {
						clearInterval(progressInterval);
						$('.wpat-editor-modal-loader-overlay').remove();
						$btn.prop('disabled', false).text('Insertar');
						alert('Error al obtener la plantilla: ' + (response.data ? response.data.message : 'Error desconocido'));
						$('#wpat-editor-modal-overlay').remove();
					}
				},
				error: function(xhr, status, error) {
					clearInterval(progressInterval);
					$('.wpat-editor-modal-loader-overlay').remove();
					$btn.prop('disabled', false).text('Insertar');
					console.error("WPAT AJAX error details:", status, error, xhr.responseText);
					alert('Fallo de conexión al cargar la plantilla. Revisa la consola para más detalles.');
					$('#wpat-editor-modal-overlay').remove();
				}
			});
		});
	}

	// Inserción de plantilla usando el flujo híbrido ultra-robusto
	function runInsertTemplateFallback(templatePostId, progressInterval, $btn, tplType, tplTitle) {
		console.log("=== WPAT: INICIANDO INSERCIÓN HÍBRIDA ===");
		console.log("Template ID:", templatePostId);
		console.log("Tipo original:", tplType);
		console.log("Título original:", tplTitle);
		
		if (progressInterval) clearInterval(progressInterval);
		
		var parentWindow = window.parent || window;
		var elObject = parentWindow.$e || window.$e;
		var elementorObj = parentWindow.elementor || window.elementor;
		var elCommon = parentWindow.elementorCommon || window.parent.elementorCommon;
		
		console.log("¿Existe parentWindow.$e / $e?:", !!elObject);
		console.log("¿Existe parentWindow.elementor / elementor?:", !!elementorObj);
		console.log("¿Existe parentWindow.elementorCommon / elCommon?:", !!elCommon);
		
		// Intentar primero la inyección directa de elementos por AJAX (método limpio sin parpadeos y libre de UI)
		if (elCommon && typeof elCommon.ajax === 'object' && typeof elCommon.ajax.addRequest === 'function') {
			console.log("WPAT: Intentando inyección directa vía get_template_data...");
			
			$('.wpat-import-progress-text').text('Sincronizando maquetación con el lienzo...');
			
			elCommon.ajax.addRequest('get_template_data', {
				data: {
					source: 'local',
					template_id: templatePostId
				},
				success: function(response) {
					console.log("WPAT: Datos de plantilla recibidos:", response);
					$('.wpat-import-progress-bar').css('width', '100%');
					$('.wpat-import-progress-text').text('100% completado (insertando...)');
					
					setTimeout(function() {
						$('.wpat-editor-modal-loader-overlay').remove();
						$btn.prop('disabled', false).text('Insertar');
						
						// Cerrar modal
						$('#wpat-editor-modal-overlay').remove();
						
						try {
							var content = null;
							if (response && response.data && response.data.content) {
								content = response.data.content;
							} else if (response && response.content) {
								content = response.content;
							} else if (response && response.data) {
								content = response.data;
							} else {
								content = response;
							}

							if (!Array.isArray(content) && typeof content === 'object') {
								content = [content];
							}
							
							var previewView = elementorObj ? elementorObj.getPreviewView() : null;
							console.log("WPAT: Obteniendo previewView:", previewView);
							
							var successInjected = false;
							
							// Helper dinámico para buscar recursivamente el objeto que expone addChildModel
							function findAddChildModelObject(root, maxDepth, visited) {
								if (!root || maxDepth <= 0) return null;
								if (typeof root !== 'object') return null;
								if (visited.indexOf(root) !== -1) return null;
								visited.push(root);
								
								if (typeof root.addChildModel === 'function') {
									return root;
								}
								
								for (var key in root) {
									if (key === 'parentWindow' || key === 'window' || key === 'top' || key === 'parent' || key === 'children') continue;
									try {
										var val = root[key];
										if (val && typeof val === 'object') {
											var found = findAddChildModelObject(val, maxDepth - 1, visited);
											if (found) return found;
										}
									} catch (e) {}
								}
								return null;
							}

							// Método 1: Intentar document/elements/create (Método nativo de Elementor para crear/insertar elementos en caliente)
							if (elObject) {
								try {
									var docContainer = (elementorObj.documents && elementorObj.documents.getCurrent) ? elementorObj.documents.getCurrent().container : null;
									console.log("WPAT: Intentando document/elements/create con docContainer:", docContainer);
									
									content.forEach(function(section) {
										elObject.run('document/elements/create', {
											container: docContainer,
											model: section
										});
									});
									
									console.log("WPAT: Inyectado con éxito vía document/elements/create!");
									successInjected = true;
								} catch (createErr) {
									console.warn("WPAT: document/elements/create falló. Intentando addChildModel...", createErr);
								}
							}
							
							// Método 2: Buscar addChildModel dinámicamente y utilizarlo
							if (!successInjected) {
								try {
									var modelObj = null;
									var visited = [];
									
									// Buscar primero en la colección del previewView (parent, owner, options)
									if (previewView && previewView.collection) {
										var coll = previewView.collection;
										if (coll.parent && typeof coll.parent.addChildModel === 'function') modelObj = coll.parent;
										else if (coll.owner && typeof coll.owner.addChildModel === 'function') modelObj = coll.owner;
										else if (coll.element && typeof coll.element.addChildModel === 'function') modelObj = coll.element;
										else if (coll.options && coll.options.parent && typeof coll.options.parent.addChildModel === 'function') modelObj = coll.options.parent;
									}
									
									// Buscar recursivamente en el previewView
									if (!modelObj && previewView) {
										modelObj = findAddChildModelObject(previewView, 3, visited);
									}
									
									// Buscar recursivamente en el documento actual
									if (!modelObj && elementorObj && elementorObj.documents && typeof elementorObj.documents.getCurrent === 'function') {
										var currentDoc = elementorObj.documents.getCurrent();
										if (currentDoc) modelObj = findAddChildModelObject(currentDoc, 3, visited);
									}
									
									console.log("WPAT: Objeto addChildModel encontrado dinámicamente:", modelObj);
									
									if (modelObj) {
										console.log("WPAT: Inyectando vía addChildModel...");
										content.forEach(function(section) {
											modelObj.addChildModel(section);
										});
										console.log("WPAT: Inyectado con éxito vía addChildModel!");
										successInjected = true;
									}
								} catch (addChildErr) {
									console.warn("WPAT: addChildModel falló. Intentando collection.add...", addChildErr);
								}
							}
							
							// Método 3: Fallback clásico de Backbone (último recurso directo a colección)
							if (!successInjected) {
								if (previewView && previewView.collection) {
									console.log("WPAT: Inyectando vía previewView.collection.add...");
									previewView.collection.add(content);
									console.log("WPAT: Inyectado vía collection.add!");
									successInjected = true;
								}
							}
							
							// Método 4: Fallback de importación comando (último recurso extremo)
							if (!successInjected && elObject) {
								console.log("WPAT: Todos los métodos fallaron. Intentando document/elements/import...");
								var currentDocId = null;
								if (elementorObj) {
									if (elementorObj.documents && typeof elementorObj.documents.getCurrent === 'function') {
										var currentDoc = elementorObj.documents.getCurrent();
										if (currentDoc) currentDocId = currentDoc.id;
									}
									if (!currentDocId && elementorObj.config && elementorObj.config.document) {
										currentDocId = elementorObj.config.document.id;
									}
								}
								elObject.run('document/elements/import', {
									id: currentDocId,
									data: content
								});
								console.log("WPAT: Inyectado vía document/elements/import!");
								successInjected = true;
							}
							
							if (!successInjected) {
								throw new Error("No se pudo obtener ningún canal de comunicación válido con el editor.");
							}
						} catch (importErr) {
							console.error("WPAT: Error al importar directamente al lienzo:", importErr);
							alert("No se pudo inyectar automáticamente. Puedes incrustarla desde la carpeta de Elementor > Mis Plantillas.");
						}
					}, 500);
				},
				error: function(err) {
					console.warn("WPAT: get_template_data falló.", err);
					alert("No se pudo inyectar automáticamente. Puedes incrustarla desde la carpeta de Elementor > Mis Plantillas.");
				}
			});
		} else {
			console.warn("WPAT: elCommon.ajax no disponible.");
			alert("No se pudo inyectar automáticamente. Puedes incrustarla desde la carpeta de Elementor > Mis Plantillas.");
		}
	}



	/**
	 * Carga y pinta las plantillas de un kit en la cuadrícula del modal.
	 */
	function loadTemplates(slug) {
		var wpatData = getWpatData();
		var kits = wpatData.kits || {};
		var kit = kits[slug];
		var $grid = $('.wpat-modal-templates-grid');

		if (!kit || !kit.templates || kit.templates.length === 0) {
			$grid.html('<p style="color:#94a3b8; text-align:center; padding: 40px 0; width:100%;">Este kit no tiene plantillas indexadas.</p>');
			return;
		}

		var html = '';
		kit.templates.forEach(function(tpl) {
			var thumb = tpl.thumbnail ? tpl.thumbnail : 'https://placehold.co/400x300/f1f5f9/94a3b8?text=Plantilla';
			var previewUrl = tpl.preview_url ? tpl.preview_url : thumb;
			
			html += '<div class="wpat-tpl-card">';
			html += '  <div class="wpat-tpl-thumb" style="background-image:url(\'' + thumb + '\');"></div>';
			html += '  <div class="wpat-tpl-info">';
			html += '    <h5>' + tpl.title + '</h5>';
			html += '    <span class="wpat-tpl-type">' + tpl.type.toUpperCase() + '</span>';
			html += '    <div style="display:flex; gap:8px; margin-top:auto; width:100%;">';
			html += '      <a href="' + previewUrl + '" class="wpat-preview-template-btn" data-thumb="' + thumb + '" style="flex:1; text-decoration:none; background:#64748b; color:#fff; text-align:center; display:flex; align-items:center; justify-content:center; padding: 8px 0; border-radius:4px; font-size:12px; font-weight:600; line-height:1.2;">Ver</a>';
			html += '      <button type="button" class="wpat-insert-template-btn" data-kit="' + slug + '" data-id="' + tpl.id + '" style="flex:2; margin:0;">Insertar</button>';
			html += '    </div>';
			html += '  </div>';
			html += '</div>';
		});

		$grid.html(html);
	}

	// Evento: Abrir vista previa en lightbox (modal de imagen vertical scrollable de 700px) dentro del editor
	$(document).on('click', '.wpat-preview-template-btn', function(e) {
		e.preventDefault();
		var $previewBtn = $(this);
		var previewUrl = $previewBtn.attr('href');
		var thumbUrl = $previewBtn.data('thumb') || '';
		var kitSlug = $previewBtn.data('kit') || '';
		var tplId = $previewBtn.data('id') || '';
		var tplTitle = $previewBtn.data('title') || 'Plantilla';
		var tplType = $previewBtn.data('type') || 'page';
		
		if (!previewUrl || previewUrl.indexOf('placehold.co') !== -1) {
			alert('No hay una vista previa disponible para esta plantilla.');
			return;
		}
		
		// Botón de acción: Insertar plantilla en Elementor
		var actionButtonHtml = 
			'<button type="button" class="wpat-insert-template-btn" data-kit="' + kitSlug + '" data-id="' + tplId + '" data-type="' + tplType + '" data-title="' + tplTitle + '" style="background:#5cb85c; border:none; color:#fff; font-size:12px; font-weight:700; cursor:pointer; padding:6px 16px; border-radius:4px; outline:none; display:inline-flex; align-items:center; gap:6px; transition:background 0.2s; height:32px; line-height:1.2; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">' +
			'  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Importar Plantilla' +
			'</button>';
		
		// Crear el lightbox modal centrado (ancho máximo 700px, cabecera oscura y cuerpo scrollable con la imagen de captura al 100%)
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

	// Cerrar lightbox
	$(document).on('click', '.wpat-close-lightbox-btn, .wpat-preview-lightbox-overlay', function(e) {
		if (e.target === this || $(this).hasClass('wpat-close-lightbox-btn')) {
			$('.wpat-preview-lightbox-overlay').remove();
		}
	});

})(jQuery);
