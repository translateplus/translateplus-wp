/**
 * Block editor: "Translate Now" in the document sidebar (no behavior yet).
 */
(function (wp) {
  var registerPlugin = wp.plugins.registerPlugin;
  var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
  var Button = wp.components.Button;
  var el = wp.element.createElement;
  var useSelect = wp.data.useSelect;
  var __ = wp.i18n.__;

  function isSupportedPostType(type) {
    var cfg = window.translateplusEditor;
    if (
      !cfg ||
      !Array.isArray(cfg.postTypes) ||
      cfg.postTypes.length === 0
    ) {
      return type === "post" || type === "page";
    }
    return cfg.postTypes.indexOf(type) !== -1;
  }

  function TranslatePlusDocumentPanel() {
    var postType = useSelect(function (select) {
      return select("core/editor").getCurrentPostType();
    }, []);

    if (!postType || !isSupportedPostType(postType)) {
      return null;
    }

    return el(
      PluginDocumentSettingPanel,
      {
        name: "translateplus-translate-now",
        title: __("TranslatePlus", "translateplus"),
        className: "translateplus-document-panel",
      },
      el(Button, {
        variant: "secondary",
        className: "translateplus-translate-now-button",
      }, __("Translate Now", "translateplus"))
    );
  }

  registerPlugin("translateplus-translate-now", {
    render: TranslatePlusDocumentPanel,
  });
})(window.wp);
