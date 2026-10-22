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
				'header[role="navigation"]', 'header[role="button"]',
				'nav[role="banner"]', 'nav[role="button"]',
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

		// 4. Enlaces de redes sociales o solo icono sin texto accesible
		if (config.fix_empty_links) {
			try {
				var links = document.querySelectorAll('a[href]:not([aria-label]):not([aria-labelledby])');
				for (var l = 0; l < links.length; l++) {
					var a = links[l];
					var linkText = (a.textContent || '').trim();
					if (linkText.length === 0) {
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
							else {
								var linkCls = (a.className || '').toLowerCase();
								if (linkCls.indexOf('cart') !== -1) linkLabel = 'Ver carrito';
								else if (linkCls.indexOf('search') !== -1) linkLabel = 'Buscar';
								else linkLabel = 'Enlace de navegación';
							}
						}
						if (linkLabel) {
							a.setAttribute('aria-label', linkLabel);
						}
					}
				}
			} catch (e) {}
		}

		// 5. Inputs y buscadores sin <label>
		if (config.fix_inputs) {
			try {
				var inputs = document.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="image"]):not([aria-label]):not([aria-labelledby])');
				for (var inp = 0; inp < inputs.length; inp++) {
					var inputEl = inputs[inp];
					var inputId = inputEl.getAttribute('id');
					var hasLabel = false;
					if (inputId) {
						hasLabel = document.querySelector('label[for="' + inputId + '"]') !== null;
					}
					if (!hasLabel && !inputEl.closest('label')) {
						var ph = inputEl.getAttribute('placeholder');
						if (ph && ph.trim().length > 0) {
							inputEl.setAttribute('aria-label', ph.trim());
						} else if (inputEl.getAttribute('type') === 'search' || (inputEl.getAttribute('name') || '').indexOf('s') === 0) {
							inputEl.setAttribute('aria-label', 'Buscar');
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
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', sanitizeARIA);
	} else {
		sanitizeARIA();
	}
})();
