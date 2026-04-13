/**
 * Settings → TranslatePlus: tab panels (General / Content / Workflow / Account / About).
 * Repositions Settings API notices (from options-head.php) to sit below the brand topbar.
 */
(function () {
	'use strict';

	/**
	 * Core prints settings_errors() before our .wrap; move notices inside .wrap, directly under the topbar.
	 */
	function moveSettingsNoticesBelowTopbar() {
		var content = document.getElementById('wpbody-content');
		var wrap = document.querySelector('.translateplus-settings.wrap');
		if (!content || !wrap) {
			return;
		}
		var topbar = wrap.querySelector('.translateplus-settings-topbar');
		if (!topbar) {
			return;
		}
		var nodes = content.querySelectorAll(':scope > .notice.settings-error');
		if (!nodes.length) {
			return;
		}
		var anchor = topbar;
		Array.prototype.forEach.call(nodes, function (el) {
			anchor.insertAdjacentElement('afterend', el);
			anchor = el;
		});
	}

	function panelHidesSaveButton(panelId) {
		return (
			panelId === 'tab-about' ||
			panelId === 'translateplus-panel-account' ||
			panelId === 'translateplus-panel-account-disconnected'
		);
	}

	function initTabs(root) {
		var tabs = root.querySelectorAll('[role="tab"]');
		if (!tabs.length) {
			return;
		}

		var form = root.closest('form');
		var saveActions = form ? form.querySelector('.translateplus-form-actions') : null;

		function updateSaveButtonVisibility(activeTab) {
			if (!saveActions) {
				return;
			}
			var panelId = activeTab.getAttribute('aria-controls') || '';
			saveActions.hidden = panelHidesSaveButton(panelId);
		}

		if (form) {
			form.addEventListener('submit', function () {
				root.classList.add('translateplus-settings-tabs--expanded');
				root.querySelectorAll('[role="tabpanel"]').forEach(function (p) {
					p.removeAttribute('hidden');
				});
			});
		}

		function selectTab(activeTab) {
			root.classList.remove('translateplus-settings-tabs--expanded');

			var panelId = activeTab.getAttribute('aria-controls');
			if (!panelId) {
				return;
			}
			var panel = document.getElementById(panelId);
			if (!panel) {
				return;
			}

			tabs.forEach(function (t) {
				var isSel = t === activeTab;
				t.setAttribute('aria-selected', isSel ? 'true' : 'false');
				t.classList.toggle('is-active', isSel);
				if (isSel) {
					t.removeAttribute('tabindex');
				} else {
					t.setAttribute('tabindex', '-1');
				}
			});

			root.querySelectorAll('[role="tabpanel"]').forEach(function (p) {
				p.hidden = p !== panel;
			});

			updateSaveButtonVisibility(activeTab);
		}

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				selectTab(tab);
			});
		});

		var initial = root.querySelector('[role="tab"][aria-selected="true"]');
		if (initial) {
			updateSaveButtonVisibility(initial);
		}
	}

	moveSettingsNoticesBelowTopbar();
	document.querySelectorAll('[data-tp-settings-tabs]').forEach(initTabs);
})();
