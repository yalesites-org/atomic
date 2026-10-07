/**
 * @file
 * Accessibility fixes for the Klaro consent UI, which Klaro itself lacks:
 * focus trap and Escape for the preferences dialog, and bottom padding so the
 * fixed notice bar never covers the page footer.
 */

Drupal.behaviors.klaroA11y = {
  attach(context, settings) {
    // Klaro adds settings.klaro only when consent is on for this site.
    if (!settings.klaro || Drupal.behaviors.klaroA11y.ready) {
      return;
    }
    Drupal.behaviors.klaroA11y.ready = true;

    // Last element focused or clicked outside the dialog: Klaro moves focus into
    // the dialog as it mounts, so activeElement at open time is never the opener.
    let opener = null;
    let wasOpen = false;
    let closedByEscape = false;
    let observedBar = null;
    let klaro = null;
    const modalSelector = '.cm-modal[aria-modal="true"]';
    const bar = () => klaro.querySelector('.cookie-notice:not(.cookie-modal-notice)');

    // klaro.css pads .site-footer by this. Body padding does not work here:
    // the page overflows a fixed-height layout container.
    function syncPadding() {
      const el = observedBar;
      const value = el ? `${Math.ceil(el.getBoundingClientRect().height)}px` : '0px';
      const style = document.documentElement.style;
      if (style.getPropertyValue('--yds-klaro-bar-height') !== value) {
        style.setProperty('--yds-klaro-bar-height', value);
      }
    }

    const resizer = new ResizeObserver(syncPadding);

    function focusables(modal) {
      return [...modal.querySelectorAll('a[href], button, input, select, textarea, [tabindex]')]
        .filter((el) => !el.disabled && el.tabIndex >= 0 && el.getClientRects().length > 0);
    }

    // Klaro focuses into .cm-modal before it sets aria-modal, so test the
    // element. Not while an Escape close is pending: Klaro refocuses its notice first.
    function track(el) {
      if (el && !closedByEscape && !el.closest('.cm-modal')) {
        opener = el;
      }
    }

    function restoreFocus() {
      closedByEscape = false;
      const target = [opener, klaro.querySelector('.cn-learn-more'), document.getElementById('klaro_toggle_dialog')]
        .find((el) => el && el.isConnected);
      if (target) {
        target.focus();
      }
    }

    // Klaro re-renders #klaro, so react to the DOM rather than to one event.
    function onKlaroChange() {
      const el = bar();
      if (el !== observedBar) {
        resizer.disconnect();
        observedBar = el;
        if (el) {
          // The observer's initial callback does the sync.
          resizer.observe(el);
        }
        else {
          syncPadding();
        }
      }
      const open = !!klaro.querySelector(modalSelector);
      if (wasOpen && !open && closedByEscape) {
        // ponytail: Klaro's own klaro.drupal.js observer refocuses the notice
        // after re-render, so go after it; patch klaro.drupal.js upstream instead.
        setTimeout(restoreFocus, 100);
      }
      wasOpen = open;
    }

    // #klaro is created by Klaro, possibly after attach: watch body (no subtree)
    // only until it exists, then watch just #klaro.
    function watchKlaro() {
      klaro = document.getElementById('klaro');
      if (!klaro) {
        return false;
      }
      new MutationObserver(onKlaroChange).observe(klaro, { childList: true, subtree: true });
      onKlaroChange();
      return true;
    }

    if (!watchKlaro()) {
      const bodyWatcher = new MutationObserver(() => {
        if (watchKlaro()) {
          bodyWatcher.disconnect();
        }
      });
      bodyWatcher.observe(document.body, { childList: true });
    }

    document.addEventListener('keydown', (event) => {
      if (!wasOpen) {
        return;
      }
      const modal = klaro.querySelector(modalSelector);
      if (!modal) {
        return;
      }
      if (event.key === 'Escape') {
        event.preventDefault();
        modal.parentElement.querySelector('.cm-bg').click();
        closedByEscape = true;
        return;
      }
      if (event.key !== 'Tab') {
        return;
      }
      const items = focusables(modal);
      if (!items.length) {
        return;
      }
      const first = items[0];
      const last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      }
      else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });

    // Click covers browsers that do not focus a clicked button (Safari).
    document.addEventListener('click', (event) => {
      if (!closedByEscape) {
        track(event.target.closest('a[href], button, input, select, textarea, [tabindex]'));
      }
    }, true);

    // Focus that lands outside the open dialog (click, script) is pulled back in.
    document.addEventListener('focusin', (event) => {
      const modal = klaro?.querySelector(modalSelector);
      if (!modal) {
        track(event.target);
      }
      else if (!modal.contains(event.target)) {
        const items = focusables(modal);
        if (items.length) {
          items[0].focus();
        }
      }
    });
  },
};
