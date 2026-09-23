/**
 * Script Frontend: Banner de Cookies & RGPD - WP Agency Toolkit
 * Ultra-ligero, sin dependencias, con soporte nativo de Google Consent Mode v2
 */

(function() {
	'use strict';

	var COOKIE_NAME = 'wpat_cookie_consent';
	var COOKIE_EXPIRY_DAYS = 180;

	// Configuración inyectada desde PHP
	var config = window.wpatCookieConfig || {
		consentMode: true,
		revokeBadge: true,
		policyVersion: '1.0'
	};

	// Helper para leer cookies
	function getCookie(name) {
		var nameEQ = name + "=";
		var ca = document.cookie.split(';');
		for (var i = 0; i < ca.length; i++) {
			var c = ca[i];
			while (c.charAt(0) === ' ') c = c.substring(1, c.length);
			if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
		}
		try {
			return localStorage.getItem(name);
		} catch (e) {
			return null;
		}
	}

	// Helper para guardar cookies y localStorage
	function setConsent(consentData) {
		var json = JSON.stringify(consentData);
		var date = new Date();
		date.setTime(date.getTime() + (COOKIE_EXPIRY_DAYS * 24 * 60 * 60 * 1000));
		var expires = "; expires=" + date.toUTCString();
		document.cookie = COOKIE_NAME + "=" + encodeURIComponent(json) + expires + "; path=/; SameSite=Lax";
		try {
			localStorage.setItem(COOKIE_NAME, json);
		} catch (e) {}
	}

	// Helper para actualizar Google Consent Mode v2
	function updateGoogleConsent(consent) {
		if (!config.consentMode) return;
		if (typeof window.gtag === 'function') {
			window.gtag('consent', 'update', {
				'analytics_storage': consent.analytics ? 'granted' : 'denied',
				'ad_storage': consent.marketing ? 'granted' : 'denied',
				'ad_user_data': consent.marketing ? 'granted' : 'denied',
				'ad_personalization': consent.marketing ? 'granted' : 'denied',
				'functionality_storage': consent.preferences ? 'granted' : 'denied',
				'personalization_storage': consent.preferences ? 'granted' : 'denied'
			});
		}
		// Disparar evento personalizado en dataLayer
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({
			'event': 'wpat_cookie_consent_updated',
			'wpat_consent': consent
		});
	}

	// Helper para desbloquear scripts marcados según categoría
	function unblockScripts(consent) {
		var scripts = document.querySelectorAll('script[type="text/plain"][data-wpat-cookie-category]');
		scripts.forEach(function(script) {
			var cat = script.getAttribute('data-wpat-cookie-category');
			if (consent[cat] === true) {
				var newScript = document.createElement('script');
				if (script.src) {
					newScript.src = script.src;
				} else {
					newScript.innerHTML = script.innerHTML;
				}
				// Copiar atributos
				Array.from(script.attributes).forEach(function(attr) {
					if (attr.name !== 'type' && attr.name !== 'data-wpat-cookie-category') {
						newScript.setAttribute(attr.name, attr.value);
					}
				});
				script.parentNode.replaceChild(newScript, script);
			}
		});
	}

	function showBanner() {
		var banner = document.getElementById('wpat_cookie_banner');
		var backdrop = document.getElementById('wpat_cookie_backdrop');
		if (banner) banner.classList.add('wpat-visible');
		if (backdrop && banner && banner.classList.contains('layout-modal')) {
			backdrop.classList.add('wpat-visible');
		}
	}

	function hideBanner() {
		var banner = document.getElementById('wpat_cookie_banner');
		var backdrop = document.getElementById('wpat_cookie_backdrop');
		if (banner) banner.classList.remove('wpat-visible');
		if (backdrop) backdrop.classList.remove('wpat-visible');
	}

	function showModal() {
		var modal = document.getElementById('wpat_cookie_modal');
		if (modal) modal.classList.add('wpat-visible');
	}

	function hideModal() {
		var modal = document.getElementById('wpat_cookie_modal');
		if (modal) modal.classList.remove('wpat-visible');
	}

	function showRevokeBadge() {
		var badge = document.getElementById('wpat_cookie_revoke_badge');
		if (badge && config.revokeBadge) {
			badge.style.display = 'inline-flex';
		}
	}

	function hideRevokeBadge() {
		var badge = document.getElementById('wpat_cookie_revoke_badge');
		if (badge) badge.style.display = 'none';
	}

	// Inicialización en DOMContentLoaded
	document.addEventListener('DOMContentLoaded', function() {
		var storedConsentRaw = getCookie(COOKIE_NAME);
		var currentConsent = null;

		if (storedConsentRaw) {
			try {
				currentConsent = JSON.parse(decodeURIComponent(storedConsentRaw));
			} catch (e) {}
		}

		if (currentConsent && currentConsent.timestamp && (!currentConsent.version || currentConsent.version === config.policyVersion)) {
			// El usuario ya tiene consentimiento previo
			updateGoogleConsent(currentConsent);
			unblockScripts(currentConsent);
			showRevokeBadge();
		} else {
			// Mostrar banner de consentimiento
			showBanner();
			hideRevokeBadge();
		}

		// Eventos de los Botones
		// 1. Aceptar Todas
		document.querySelectorAll('.wpat-cookie-btn-accept').forEach(function(btn) {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				var consent = {
					necessary: true,
					analytics: true,
					marketing: true,
					preferences: true,
					timestamp: new Date().toISOString(),
					version: config.policyVersion
				};
				setConsent(consent);
				updateGoogleConsent(consent);
				unblockScripts(consent);
				hideBanner();
				hideModal();
				showRevokeBadge();
			});
		});

		// 2. Rechazar Todas
		document.querySelectorAll('.wpat-cookie-btn-reject').forEach(function(btn) {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				var consent = {
					necessary: true,
					analytics: false,
					marketing: false,
					preferences: false,
					timestamp: new Date().toISOString(),
					version: config.policyVersion
				};
				setConsent(consent);
				updateGoogleConsent(consent);
				hideBanner();
				hideModal();
				showRevokeBadge();
			});
		});

		// 3. Abrir Modal de Configuración
		document.querySelectorAll('.wpat-cookie-btn-settings').forEach(function(btn) {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				// Sincronizar checkboxes con estado actual si existe
				if (currentConsent) {
					var cbAnalytics = document.getElementById('wpat_cookie_cat_analytics');
					var cbMarketing = document.getElementById('wpat_cookie_cat_marketing');
					var cbPreferences = document.getElementById('wpat_cookie_cat_preferences');
					if (cbAnalytics) cbAnalytics.checked = !!currentConsent.analytics;
					if (cbMarketing) cbMarketing.checked = !!currentConsent.marketing;
					if (cbPreferences) cbPreferences.checked = !!currentConsent.preferences;
				}
				showModal();
			});
		});

		// 4. Cerrar Modal
		document.querySelectorAll('.wpat-cookie-modal-close, .wpat-close-cookie-modal').forEach(function(el) {
			el.addEventListener('click', function(e) {
				e.preventDefault();
				hideModal();
			});
		});

		// 5. Guardar Selección desde el Modal
		var saveSelectionBtn = document.getElementById('wpat_cookie_save_selection_btn');
		if (saveSelectionBtn) {
			saveSelectionBtn.addEventListener('click', function(e) {
				e.preventDefault();
				var cbAnalytics = document.getElementById('wpat_cookie_cat_analytics');
				var cbMarketing = document.getElementById('wpat_cookie_cat_marketing');
				var cbPreferences = document.getElementById('wpat_cookie_cat_preferences');

				var consent = {
					necessary: true,
					analytics: cbAnalytics ? cbAnalytics.checked : false,
					marketing: cbMarketing ? cbMarketing.checked : false,
					preferences: cbPreferences ? cbPreferences.checked : false,
					timestamp: new Date().toISOString(),
					version: config.policyVersion
				};

				setConsent(consent);
				updateGoogleConsent(consent);
				unblockScripts(consent);
				hideModal();
				hideBanner();
				showRevokeBadge();
			});
		}

		// 6. Botón flotante para revocar / modificar consentimiento
		var revokeBadge = document.getElementById('wpat_cookie_revoke_badge');
		if (revokeBadge) {
			revokeBadge.addEventListener('click', function(e) {
				e.preventDefault();
				showModal();
			});
		}
	});

})();
