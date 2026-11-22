/* Initialize littlefoot with options from the plugin configuration. */
(function () {
  'use strict';

  // Grav emits external files before inline scripts. Wait until the document
  // has been parsed so the configuration object is available.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }

  function start() {
    // The UMD bundle exposes named exports; the function is usually
    // available as `window.littlefoot.default`.
    var lf = window.littlefoot;
    if (lf && typeof lf !== 'function') {
      lf = lf.default || lf.littlefoot;
    }
    if (typeof lf !== 'function') {
      console.warn('[footnotes] littlefoot was not found');
      return;
    }

    var o = window.footnotesOptions || {};
    var footnoteLabel = o.footnoteLabel || 'Footnote';


    // Hover only makes sense with a fine pointer; on touch screens it would
    // activate on the first touch.
    var finePointer = window.matchMedia
      ? window.matchMedia('(hover: hover) and (pointer: fine)').matches
      : false;

    // A bibliography inserted with [references] deliberately keeps its source
    // list in the document. Littlefoot otherwise marks every now-empty ancestor
    // of that list as print-only; CSS restores that explicitly marked list.

    lf({
      activateOnHover:  Boolean(o.activateOnHover) && finePointer,
      hoverDelay:       Number(o.hoverDelay) || 0,
      dismissOnUnhover: Boolean(o.dismissOnUnhover) && finePointer,
      dismissDelay:     Number(o.dismissDelay) || 0,
      allowMultiple:    Boolean(o.allowMultiple),
      buttonTemplate:
        '<button aria-label="' + footnoteLabel + ' <% number %>" class="littlefoot__button" ' +
        'title="' + footnoteLabel + ' <% number %>" id="<% reference %>" data-footnote-id="<% id %>">' +
        '<% number %></button>'
    });
  }
})();
