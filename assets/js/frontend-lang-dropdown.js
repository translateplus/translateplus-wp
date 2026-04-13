/**
 * TranslatePlus language switcher: preferred language in localStorage, dropdown toggle, outside click, Escape.
 */
(function () {
	"use strict";

	var STORAGE_KEY = "translateplus_preferred_lang";

	function persistLanguageFromLink(anchor) {
		if (!anchor || !anchor.getAttribute) {
			return;
		}
		var code = anchor.getAttribute("hreflang");
		if (!code || typeof code !== "string") {
			return;
		}
		code = code.trim().toLowerCase();
		if (!code) {
			return;
		}
		try {
			localStorage.setItem(STORAGE_KEY, code);
		} catch (e) {
			/* private mode / quota */
		}
	}

	document.addEventListener("click", function (e) {
		var t = e.target;
		if (!t || !t.closest) {
			return;
		}
		var a = t.closest("a[hreflang]");
		if (!a) {
			return;
		}
		var root = a.closest(".translateplus-lang-dd, .translateplus-lang-switcher");
		if (!root) {
			return;
		}
		persistLanguageFromLink(a);
	});

	function closeAll(exceptRoot) {
		document.querySelectorAll(".translateplus-lang-dd.is-open").forEach(function (root) {
			if (exceptRoot && root === exceptRoot) {
				return;
			}
			setOpen(root, false);
		});
	}

	function setOpen(root, open) {
		var btn = root.querySelector(".translateplus-lang-dd__toggle");
		var list = root.querySelector(".translateplus-lang-dd__list");
		if (!btn || !list) {
			return;
		}
		if (open) {
			root.classList.add("is-open");
			list.removeAttribute("hidden");
			btn.setAttribute("aria-expanded", "true");
		} else {
			root.classList.remove("is-open");
			list.setAttribute("hidden", "");
			btn.setAttribute("aria-expanded", "false");
		}
	}

	function initRoot(root) {
		var btn = root.querySelector(".translateplus-lang-dd__toggle");
		var list = root.querySelector(".translateplus-lang-dd__list");
		if (!btn || !list || root.getAttribute("data-translateplus-dd-init") === "1") {
			return;
		}
		root.setAttribute("data-translateplus-dd-init", "1");

		btn.addEventListener("click", function (e) {
			e.stopPropagation();
			var willOpen = !root.classList.contains("is-open");
			closeAll(willOpen ? null : root);
			if (willOpen) {
				setOpen(root, true);
			} else {
				setOpen(root, false);
			}
		});

		list.addEventListener("click", function (e) {
			if (e.target && e.target.closest && e.target.closest("a")) {
				closeAll();
			}
		});
	}

	function onDocClick(e) {
		if (e.target && e.target.closest && e.target.closest(".translateplus-lang-dd")) {
			return;
		}
		closeAll();
	}

	function onKey(e) {
		if (e.key === "Escape") {
			closeAll();
		}
	}

	document.addEventListener("click", onDocClick);
	document.addEventListener("keydown", onKey);

	document.querySelectorAll("[data-translateplus-lang-dd]").forEach(initRoot);
})();
