/**
 * Target languages: search filter + selection count (Settings → TranslatePlus).
 */
(function () {
  "use strict";

  function formatCount(n) {
    var i = window.translateplusTargetLangPicker || {};
    if (n === 0) {
      return i.countNone || "";
    }
    if (n === 1) {
      return i.countOne || "";
    }
    var t = i.countMany || "%d languages selected";
    return t.replace(/%d/g, String(n));
  }

  function initRoot(root) {
    var search = root.querySelector(".translateplus-lang-picker__search");
    var items = root.querySelectorAll(".translateplus-lang-picker__item");
    var countEl = root.querySelector(".translateplus-lang-picker__count");
    if (!search || !countEl) {
      return;
    }

    function updateCount() {
      var n = root.querySelectorAll(
        '.translateplus-lang-picker__item input[type="checkbox"]:checked'
      ).length;
      countEl.textContent = formatCount(n);
    }

    function updateSelectedStates() {
      for (var i = 0; i < items.length; i++) {
        var item = items[i];
        var cb = item.querySelector('input[type="checkbox"]');
        item.classList.toggle("is-selected", !!(cb && cb.checked));
      }
    }

    function haystackMatches(hay, q) {
      if (!q) {
        return true;
      }
      if (hay.indexOf(q) !== -1) {
        return true;
      }
      var tokens = q.split(/\s+/).filter(Boolean);
      if (tokens.length <= 1) {
        return false;
      }
      for (var t = 0; t < tokens.length; t++) {
        if (hay.indexOf(tokens[t]) === -1) {
          return false;
        }
      }
      return true;
    }

    function filter() {
      var q = (search.value || "").toLowerCase().replace(/\s+/g, " ").trim();
      for (var i = 0; i < items.length; i++) {
        var item = items[i];
        var hay = (item.getAttribute("data-search-text") || "").toLowerCase();
        var match = haystackMatches(hay, q);
        item.classList.toggle("is-filtered-out", !match);
      }
    }

    search.addEventListener("input", filter);
    search.addEventListener("search", filter);
    root.addEventListener("change", function (ev) {
      if (ev.target && ev.target.matches('input[type="checkbox"]')) {
        updateCount();
        updateSelectedStates();
      }
    });

    filter();
    updateCount();
    updateSelectedStates();
  }

  document
    .querySelectorAll("[data-translateplus-lang-picker]")
    .forEach(initRoot);
})();

(function () {
  "use strict";

  function syncPostTypeSelected() {
    document
      .querySelectorAll(".translateplus-post-type-grid__item")
      .forEach(function (row) {
        var cb = row.querySelector('input[type="checkbox"]');
        row.classList.toggle("is-selected", !!(cb && cb.checked));
      });
  }

  document.addEventListener("change", function (ev) {
    if (
      ev.target &&
      ev.target.matches(".translateplus-post-type-grid__item input[type=checkbox]")
    ) {
      syncPostTypeSelected();
    }
  });

  syncPostTypeSelected();
})();
