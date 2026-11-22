/* Make bibliography return links target the interactive in-text footnote button. */
(function () {
  'use strict';

  function updateBacklinks() {
    document.querySelectorAll('.footnotes--references .footnote-backref').forEach(function (link) {
      var target = (link.getAttribute('href') || '').replace(/^#/, '');
      if (!target) {
        return;
      }

      var interactiveCall = document.getElementById('lf-' + target);
      if (interactiveCall) {
        link.setAttribute('href', '#' + interactiveCall.id);
      }
    });
  }

  function scheduleUpdate() {
    // littlefoot replaces the original calls during its own DOMContentLoaded
    // handler. Run in the next task so its visible buttons already exist.
    window.setTimeout(updateBacklinks, 0);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleUpdate);
  } else {
    scheduleUpdate();
  }
})();
