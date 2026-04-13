/**
 * Settings → TranslatePlus: load usage / connection check after first paint; refresh via AJAX.
 */
(function () {
	'use strict';

	var root = document.getElementById('translateplus-api-stats-root');
	var conn = document.getElementById('translateplus-connection-check-root');
	var btn = document.getElementById('translateplus-refresh-stats-button');
	var feedback = document.getElementById('translateplus-refresh-stats-feedback');
	var creditsEl = document.getElementById('translateplus-status-bar-credits');
	var updatedEl = document.getElementById('translateplus-status-bar-updated');
	var cfg = typeof window.translateplusSettingsSummary !== 'undefined' ? window.translateplusSettingsSummary : null;

	if (!root || !cfg || !cfg.ajaxUrl || !cfg.action || !cfg.nonce) {
		return;
	}

	function feedbackClear() {
		if (!feedback) {
			return;
		}
		feedback.textContent = '';
		feedback.hidden = true;
		feedback.classList.remove('is-success', 'is-error');
	}

	function feedbackShow(message, isError) {
		if (!feedback) {
			return;
		}
		feedback.textContent = message;
		feedback.hidden = false;
		feedback.classList.toggle('is-success', !isError);
		feedback.classList.toggle('is-error', !!isError);
	}

	function fetchSummary(refresh) {
		var params = new URLSearchParams();
		params.append('action', cfg.action);
		params.append('nonce', cfg.nonce);
		if (refresh) {
			params.append('refresh', '1');
		}

		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: params.toString(),
		}).then(function (response) {
			return response.json();
		});
	}

	function applySummaryPayload(res, refresh) {
		if (!res || !res.success || !res.data) {
			throw new Error('invalid');
		}

		if (creditsEl && res.data.status_bar_credits != null && res.data.status_bar_credits !== '') {
			creditsEl.textContent = res.data.status_bar_credits;
		}
		if (updatedEl && res.data.status_bar_updated != null && res.data.status_bar_updated !== '') {
			updatedEl.textContent = res.data.status_bar_updated;
		}

		if (conn) {
			if (res.data.connection_is_ok === true) {
				conn.innerHTML = '';
				conn.hidden = true;
			} else {
				conn.hidden = false;
				conn.innerHTML = res.data.connection_html || '';
			}
			conn.removeAttribute('aria-busy');
		}

		root.innerHTML = res.data.stats_html || '';
		root.classList.remove('translateplus-api-stats--loading');
		root.removeAttribute('aria-busy');

		if (refresh && cfg.strings) {
			var ok = res.data.refresh_ok === true;
			if (ok) {
				feedbackShow(cfg.strings.refreshOk || '', false);
			} else {
				feedbackShow(cfg.strings.refreshFail || '', true);
			}
		}
	}

	function showLoadError() {
		var errConn = cfg.strings && cfg.strings.connectionError ? cfg.strings.connectionError : '';
		var errStats = cfg.strings && cfg.strings.statsError ? cfg.strings.statsError : '';
		if (conn) {
			conn.hidden = false;
			conn.innerHTML =
				'<p class="translateplus-connection-check translateplus-connection-check--warn" role="alert">' +
				errConn +
				'</p>';
			conn.removeAttribute('aria-busy');
		}
		root.innerHTML =
			'<div class="translateplus-inline-notice translateplus-inline-notice--error" role="alert"><p>' +
			errStats +
			'</p></div>';
		root.classList.remove('translateplus-api-stats--loading');
		root.removeAttribute('aria-busy');
	}

	fetchSummary(false)
		.then(function (res) {
			applySummaryPayload(res, false);
		})
		.catch(function () {
			showLoadError();
		});

	if (btn) {
		btn.addEventListener('click', function () {
			if (btn.disabled) {
				return;
			}
			feedbackClear();
			btn.disabled = true;
			var prevLabel = btn.textContent;
			if (cfg.strings && cfg.strings.refreshing) {
				btn.textContent = cfg.strings.refreshing;
			}
			root.classList.add('translateplus-api-stats--loading');
			root.setAttribute('aria-busy', 'true');

			fetchSummary(true)
				.then(function (res) {
					applySummaryPayload(res, true);
				})
				.catch(function () {
					showLoadError();
					feedbackShow((cfg.strings && cfg.strings.refreshFail) || '', true);
				})
				.finally(function () {
					btn.disabled = false;
					btn.textContent = prevLabel;
				});
		});
	}
})();
