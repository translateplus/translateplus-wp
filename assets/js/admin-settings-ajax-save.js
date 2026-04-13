/**
 * Settings → TranslatePlus: save via admin-ajax (no full page reload).
 */
(function () {
	'use strict';

	var cfg = typeof window.translateplusAjaxSave !== 'undefined' ? window.translateplusAjaxSave : null;
	if (!cfg || !cfg.ajaxUrl) {
		return;
	}

	var form = document.getElementById('translateplus-options-form');
	if (!form) {
		return;
	}

	function unhideAllTabPanels() {
		var tabsRoot = form.querySelector('[data-tp-settings-tabs]');
		if (!tabsRoot) {
			return;
		}
		tabsRoot.classList.add('translateplus-settings-tabs--expanded');
		tabsRoot.querySelectorAll('[role="tabpanel"]').forEach(function (p) {
			p.removeAttribute('hidden');
		});
	}

	function removeAjaxNotice() {
		var n = document.getElementById('translateplus-ajax-settings-notice');
		if (n && n.parentNode) {
			n.parentNode.removeChild(n);
		}
	}

	function showNotice(isError, message) {
		var wrap = document.querySelector('.translateplus-settings.wrap');
		if (!wrap) {
			return;
		}
		var topbar = wrap.querySelector('.translateplus-settings-topbar');
		if (!topbar) {
			return;
		}
		removeAjaxNotice();
		var div = document.createElement('div');
		div.id = 'translateplus-ajax-settings-notice';
		div.setAttribute('role', 'alert');
		div.className = 'notice notice-' + (isError ? 'error' : 'success') + ' is-dismissible';
		var p = document.createElement('p');
		var strong = document.createElement('strong');
		strong.textContent = message;
		p.appendChild(strong);
		div.appendChild(p);
		topbar.insertAdjacentElement('afterend', div);
		if (typeof window.wp !== 'undefined' && window.wp.a11y && typeof window.wp.a11y.speak === 'function') {
			window.wp.a11y.speak(message);
		}
	}

	function getSaveButton() {
		return form.querySelector(
			'.translateplus-form-actions input[type="submit"], .translateplus-form-actions button[type="submit"]'
		);
	}

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		unhideAllTabPanels();

		var fd = new FormData(form);
		fd.set('action', 'translateplus_save_settings');

		var btn = getSaveButton();
		var orig = '';
		if (btn) {
			orig = btn.tagName === 'INPUT' ? btn.value : btn.textContent;
			btn.disabled = true;
			form.setAttribute('aria-busy', 'true');
			if (btn.tagName === 'INPUT') {
				btn.value = cfg.saving || 'Saving…';
			} else {
				btn.textContent = cfg.saving || 'Saving…';
			}
		}

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			body: fd,
			credentials: 'same-origin',
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (res) {
				if (res && res.success) {
					var msg =
						res.data && res.data.message
							? res.data.message
							: 'Settings saved.';
					showNotice(false, msg);
				} else {
					var err =
						res && res.data && res.data.message
							? res.data.message
							: cfg.error || 'Error';
					showNotice(true, err);
				}
			})
			.catch(function () {
				showNotice(true, cfg.error || 'Error');
			})
			.finally(function () {
				form.removeAttribute('aria-busy');
				if (btn) {
					btn.disabled = false;
					if (btn.tagName === 'INPUT') {
						btn.value = orig;
					} else {
						btn.textContent = orig;
					}
				}
			});
	});
})();
