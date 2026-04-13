/**
 * Block editor: language switcher above the canvas (buttons + + Add).
 */
(function () {
  var cfg = window.translateplusTranslationPostTabs;
  if (!cfg || !cfg.items || !cfg.items.length || !cfg.toolbarId) {
    return;
  }

  window.wp.domReady(function () {
    if (document.getElementById(cfg.toolbarId)) {
      return;
    }

    var wrap = document.createElement("div");
    wrap.id = cfg.toolbarId;
    wrap.className =
      "translateplus-lang-switcher translateplus-lang-actions-root";
    wrap.setAttribute("role", "toolbar");
    wrap.setAttribute(
      "aria-label",
      (cfg.i18n && cfg.i18n.toolbar) || "Post languages"
    );
    wrap.style.cssText =
      "display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:0 0 16px;padding:10px 12px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;";

    var label = document.createElement("span");
    label.className = "translateplus-lang-switcher__label";
    label.style.cssText = "font-weight:600;margin-right:4px;";
    label.textContent = (cfg.i18n && cfg.i18n.toolbar) || "";
    wrap.appendChild(label);

    var inner = document.createElement("span");
    inner.className = "translateplus-lang-switcher__buttons";
    inner.style.cssText =
      "display:flex;flex-wrap:wrap;gap:6px;align-items:center;";

    cfg.items.forEach(function (item) {
      if (item.current) {
        var cur = document.createElement("button");
        cur.type = "button";
        cur.className = "button button-primary";
        cur.disabled = true;
        cur.setAttribute("aria-current", "true");
        cur.textContent = item.label;
        if (item.title) {
          cur.title = item.title;
        }
        inner.appendChild(cur);
        return;
      }
      if (item.url) {
        var a = document.createElement("a");
        a.className = "button";
        a.href = item.url;
        a.textContent = item.label;
        if (item.title) {
          a.title = item.title;
        }
        inner.appendChild(a);
        return;
      }
      var miss = document.createElement("button");
      miss.type = "button";
      miss.className = "button translateplus-lang-missing";
      miss.setAttribute("data-tp-pick-lang", item.code);
      miss.textContent = item.label;
      if (item.missing_aria) {
        miss.title = item.missing_aria;
        miss.setAttribute("aria-label", item.missing_aria);
      } else if (item.title) {
        miss.title = item.title;
      }
      inner.appendChild(miss);
    });

    var addBtn = document.createElement("button");
    addBtn.type = "button";
    addBtn.className = "button button-secondary translateplus-add-translation";
    addBtn.textContent =
      (cfg.i18n && cfg.i18n.add) || "+ Add";
    if (cfg.i18n && cfg.i18n.addHelp) {
      addBtn.title = cfg.i18n.addHelp;
    }
    inner.appendChild(addBtn);
    wrap.appendChild(inner);

    var area = document.querySelector(
      ".edit-post-visual-editor__content-area"
    );
    if (area && area.firstChild) {
      area.insertBefore(wrap, area.firstChild);
      return;
    }

    var sk = document.querySelector(".interface-interface-skeleton__content");
    if (sk && sk.firstChild) {
      sk.insertBefore(wrap, sk.firstChild);
    }
  });
})();
