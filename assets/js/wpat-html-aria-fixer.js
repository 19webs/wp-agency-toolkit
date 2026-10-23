/**
 * WP Agency Toolkit - Corrector Técnico de Código HTML y ARIA (Silencioso)
 * Saneamiento dinámico del DOM para cumplimiento WCAG 2.1 y Google Lighthouse 100/100.
 */
(function() {
	'use strict';

	function sanitizeARIA() {
		var config = window.wpatAriaConfig || {
			fix_roles: true,
			fix_hidden: true,
			fix_empty_buttons: true,
			fix_empty_links: true,
			fix_inputs: true,
			fix_decorative_imgs: true
		};

		// 1. Saneamiento de Roles ARIA conflictivos según estándar W3C ARIA in HTML
		if (config.fix_roles) {
			var invalidRoleSelectors = [
				'article[role="listitem"]', 'article[role="button"]', 'article[role="link"]', 'article[role="navigation"]', 'article[role="banner"]',
				'section[role="listitem"]',
				'header[role="navigation"]', 'header[role="button"]', 'header[role="listitem"]',
				'nav[role="banner"]', 'nav[role="button"]', 'nav[role="listitem"]',
				'footer[role="navigation"]', 'footer[role="button"]', 'footer[role="listitem"]',
				'main[role="listitem"]', 'aside[role="listitem"]',
				'ul[role="navigation"]', 'ol[role="navigation"]',
				'a[href][role="button"]',
				'img[alt][role="presentation"]', 'img[alt][role="none"]'
			];

			invalidRoleSelectors.forEach(function(sel) {
				try {
					var els = document.querySelectorAll(sel);
					for (var i = 0; i < els.length; i++) {
						var el = els[i];
						if (el.tagName.toLowerCase() === 'img') {
							var altVal = el.getAttribute('alt');
							if (altVal && altVal.trim().length > 0) {
								el.removeAttribute('role');
							}
						} else {
							el.removeAttribute('role');
						}
					}
				} catch (e) {}
			});

			// 1.b. Contenedores con role="list" que contienen <article> (Fallo de Elementor: aria-required-children)
			try {
				var listContainers = document.querySelectorAll('[role="list"]');
				for (var lc = 0; lc < listContainers.length; lc++) {
					var cont = listContainers[lc];
					if (cont.querySelector('article') || cont.classList.contains('elementor-posts-container') || cont.classList.contains('elementor-posts')) {
						cont.removeAttribute('role');
					}
				}
			} catch (e) {}

			// 1.c. Eliminar role="listitem" huérfanos o en <span> (ej. iconos sociales de Elementor: aria-required-parent)
			try {
				var listitems = document.querySelectorAll('[role="listitem"]');
				for (var li = 0; li < listitems.length; li++) {
					var item = listitems[li];
					if (item.tagName.toLowerCase() === 'span' || !item.closest('[role="list"], [role="group"], ul, ol')) {
						item.removeAttribute('role');
					}
				}
			} catch (e) {}

			// Eliminar roles no estándar o en desuso
			try {
				var obsoleteRoleEls = document.querySelectorAll('[role="text"]');
				for (var r = 0; r < obsoleteRoleEls.length; r++) {
					obsoleteRoleEls[r].removeAttribute('role');
				}
			} catch (e) {}
		}

		// 2. Trampas de teclado en contenedores con aria-hidden="true"
		if (config.fix_hidden) {
			try {
				var hiddenContainers = document.querySelectorAll('[aria-hidden="true"]');
				for (var h = 0; h < hiddenContainers.length; h++) {
					var cont = hiddenContainers[h];
					var focusables = cont.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
					for (var f = 0; f < focusables.length; f++) {
						var foc = focusables[f];
						if (!foc.hasAttribute('data-wpat-orig-tabindex')) {
							foc.setAttribute('data-wpat-orig-tabindex', foc.getAttribute('tabindex') || '0');
							foc.setAttribute('tabindex', '-1');
						}
					}
				}
			} catch (e) {}
		}

		// 3. Botones con solo icono (SVG / i / span) sin nombre accesible
		if (config.fix_empty_buttons) {
			try {
				var buttons = document.querySelectorAll('button:not([aria-label]):not([aria-labelledby]), [role="button"]:not([aria-label]):not([aria-labelledby])');
				for (var b = 0; b < buttons.length; b++) {
					var btn = buttons[b];
					var text = (btn.textContent || '').trim();
					if (text.length === 0) {
						var label = btn.getAttribute('title');
						if (!label) {
							var cls = (btn.className || '').toLowerCase();
							if (cls.indexOf('menu') !== -1 || cls.indexOf('nav') !== -1 || cls.indexOf('hamburger') !== -1) {
								label = 'Abrir o cerrar menú';
							} else if (cls.indexOf('search') !== -1) {
								label = 'Buscar';
							} else if (cls.indexOf('cart') !== -1) {
								label = 'Carrito de compras';
							} else if (cls.indexOf('close') !== -1 || cls.indexOf('dismiss') !== -1) {
								label = 'Cerrar';
							} else if (cls.indexOf('next') !== -1) {
								label = 'Siguiente';
							} else if (cls.indexOf('prev') !== -1) {
								label = 'Anterior';
							} else {
								label = 'Botón de acción';
							}
						}
						if (label) {
							btn.setAttribute('aria-label', label);
						}
					}
				}
			} catch (e) {}
		}

		// 4. Enlaces de redes sociales o solo icono sin texto accesible (WCAG y Lighthouse)
		if (config.fix_empty_links) {
			// 4.a. Limpiar cualquier etiqueta genérica repetida previa que provoque el error "enlaces idénticos"
			try {
				var badNavLinks = document.querySelectorAll('a[aria-label="Enlace de navegación"]');
				for (var bnl = 0; bnl < badNavLinks.length; bnl++) {
					badNavLinks[bnl].removeAttribute('aria-label');
				}
			} catch (e) {}

			// 4.b. Enlaces de miniaturas de posts con tabindex="-1": ocultar de lectores de pantalla para evitar duplicados del título
			try {
				var postThumbs = document.querySelectorAll('a.elementor-post__thumbnail__link');
				for (var pt = 0; pt < postThumbs.length; pt++) {
					var thumb = postThumbs[pt];
					thumb.removeAttribute('aria-label');
					if (thumb.getAttribute('tabindex') === '-1' || !thumb.hasAttribute('tabindex')) {
						thumb.setAttribute('tabindex', '-1');
						thumb.setAttribute('aria-hidden', 'true');
					}
				}
			} catch (e) {}

			try {
				var links = document.querySelectorAll('a[href]:not([aria-label]):not([aria-labelledby]):not([aria-hidden="true"])');
				for (var l = 0; l < links.length; l++) {
					var a = links[l];

					// Si contiene texto visible, no es un enlace vacío
					var linkText = (a.textContent || '').trim();
					if (linkText.length > 0) {
						continue;
					}

					// Si contiene una imagen con atributo alt, ese alt ES el nombre accesible según el estándar W3C
					var imgWithAlt = a.querySelector('img[alt]');
					if (imgWithAlt && (imgWithAlt.getAttribute('alt') || '').trim().length > 0) {
						continue;
					}

					// Si contiene SVG con <title>, ya tiene nombre accesible
					var svgTitle = a.querySelector('svg title');
					if (svgTitle && (svgTitle.textContent || '').trim().length > 0) {
						continue;
					}

					// Si es un enlace de miniatura con tabindex="-1", ocultarlo para que el lector lea directamente el título del post
					if (a.classList.contains('elementor-post__thumbnail__link') || a.getAttribute('tabindex') === '-1') {
						a.setAttribute('aria-hidden', 'true');
						continue;
					}

					var linkLabel = a.getAttribute('title');
					if (!linkLabel) {
						var href = (a.getAttribute('href') || '').toLowerCase();
						if (href.indexOf('facebook.com') !== -1) linkLabel = 'Facebook';
						else if (href.indexOf('instagram.com') !== -1) linkLabel = 'Instagram';
						else if (href.indexOf('twitter.com') !== -1 || href.indexOf('x.com') !== -1) linkLabel = 'X (Twitter)';
						else if (href.indexOf('linkedin.com') !== -1) linkLabel = 'LinkedIn';
						else if (href.indexOf('youtube.com') !== -1) linkLabel = 'YouTube';
						else if (href.indexOf('tiktok.com') !== -1) linkLabel = 'TikTok';
						else if (href.indexOf('wa.me') !== -1 || href.indexOf('whatsapp.com') !== -1) linkLabel = 'WhatsApp';
						else if (href.indexOf('mailto:') !== -1) linkLabel = 'Enviar correo electrónico';
						else if (href.indexOf('tel:') !== -1) linkLabel = 'Llamar por teléfono';
						else if (a.classList.contains('custom-logo-link') || a.classList.contains('navbar-brand')) {
							linkLabel = 'Ir a la página de inicio';
						} else {
							var linkCls = (a.className || '').toLowerCase();
							if (linkCls.indexOf('cart') !== -1) linkLabel = 'Ver carrito';
							else if (linkCls.indexOf('search') !== -1) linkLabel = 'Buscar';
							// NUNCA asignar un nombre genérico repetido como 'Enlace de navegación'
						}
					}

					if (linkLabel) {
						a.setAttribute('aria-label', linkLabel);
					}
				}
			} catch (e) {}
		}

		// 5. Inputs y buscadores sin <label> accesible
		if (config.fix_inputs) {
			try {
				var inputs = document.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="image"]):not([aria-label]):not([aria-labelledby]), select:not([aria-label]):not([aria-labelledby]), textarea:not([aria-label]):not([aria-labelledby])');
				for (var inp = 0; inp < inputs.length; inp++) {
					var inputEl = inputs[inp];
					var inputId = inputEl.getAttribute('id');
					var labelEl = null;

					if (inputId) {
						labelEl = document.querySelector('label[for="' + inputId + '"]');
					}
					if (!labelEl) {
						labelEl = inputEl.closest('label');
					}

					var labelText = labelEl ? (labelEl.textContent || '').trim() : '';

					// Si no tiene <label> o el <label> carece de texto visible (común en switches, toggles o iconos)
					if (!labelEl || labelText.length === 0) {
						var accessibleName = '';

						var ph = inputEl.getAttribute('placeholder');
						if (ph && ph.trim().length > 0) {
							accessibleName = ph.trim();
						} else if (inputEl.getAttribute('type') === 'search' || (inputEl.getAttribute('name') || '').indexOf('s') === 0) {
							accessibleName = 'Buscar';
						} else {
							var parentItem = inputEl.closest('.wpat-cookie-cat-item, .form-group, .form-row, .field-wrap, tr, li, div');
							if (parentItem) {
								var titleEl = parentItem.querySelector('h1, h2, h3, h4, h5, h6, .wpat-cookie-cat-title, .title, strong');
								if (titleEl && (titleEl.textContent || '').trim().length > 0) {
									accessibleName = titleEl.textContent.trim();
								}
							}
							if (!accessibleName && inputId) {
								accessibleName = inputId.replace(/[-_]/g, ' ');
							}
						}

						if (accessibleName) {
							inputEl.setAttribute('aria-label', accessibleName);
						}
					}
				}
			} catch (e) {}
		}

		// 6. Imágenes decorativas que carecen por completo de atributo alt
		if (config.fix_decorative_imgs) {
			try {
				var imgs = document.querySelectorAll('img:not([alt])');
				for (var m = 0; m < imgs.length; m++) {
					imgs[m].setAttribute('alt', '');
				}
			} catch (e) {}
		}

		// 7. Enlaces idénticos con diferente destino (Lighthouse: identical-links-same-purpose)
		// Detecta enlaces residuales de plugins (como Complianz) que apuntan a wp-admin/post.php y sincroniza su URL pública
		try {
			var adminLinks = document.querySelectorAll('a[href*="wp-admin/post.php?post="]');
			for (var al = 0; al < adminLinks.length; al++) {
				var aEl = adminLinks[al];
				var aText = (aEl.textContent || '').trim().toLowerCase();
				var publicLinks = document.querySelectorAll('a[href]:not([href*="wp-admin"])');
				var matchedHref = '';
				for (var pl = 0; pl < publicLinks.length; pl++) {
					var pEl = publicLinks[pl];
					if ((pEl.textContent || '').trim().toLowerCase() === aText) {
						matchedHref = pEl.getAttribute('href');
						break;
					}
				}
				if (matchedHref) {
					aEl.setAttribute('href', matchedHref);
				} else if (!aEl.hasAttribute('aria-label')) {
					aEl.setAttribute('aria-label', 'Editar ' + (aEl.textContent || '').trim());
				}
			}
		} catch (e) {}
	}

	function init() {
		sanitizeARIA();

		if (window.MutationObserver && document.body) {
			var debounceTimer = null;
			var observer = new MutationObserver(function() {
				if (debounceTimer) clearTimeout(debounceTimer);
				debounceTimer = setTimeout(sanitizeARIA, 150);
			});
			observer.observe(document.body, { childList: true, subtree: true });
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
