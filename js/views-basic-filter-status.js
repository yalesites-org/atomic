/**
 * @file
 * Announces the updated result count of a YS Views Basic listing after an
 * exposed-filter auto-submit, so screen reader users know results changed
 * without re-navigating the page (WCAG 2.1 SC 4.1.3).
 *
 * The count is provided per view DOM id in drupalSettings.ysViewsBasicFilter by
 * atomic_preprocess_views_view(). Because that render (and these settings) are
 * refreshed on every Views AJAX update, the initial page load is recorded
 * without announcing; only subsequent, filter-driven updates are announced.
 *
 * A pager click is an AJAX update too, and it replaces the pager the reader
 * just activated, which drops keyboard focus to the page body. When the update
 * came from a pager link, focus moves to the new current-page item instead (so
 * the reader stays at the pager) and the announcement names the page.
 */
((Drupal, drupalSettings, once) => {
  const recorded = {};
  const pagerClicked = {};

  // The listing a pager link belongs to, by view DOM id, when it is one of the
  // listings registered in ysViewsBasicFilter.
  const listingDomId = (element) => {
    const counts = drupalSettings.ysViewsBasicFilter || {};
    return Object.keys(counts).find((domId) =>
      element.closest(`.js-view-dom-id-${domId}`)
    );
  };

  // Where focus goes after a pager update: the current page number, or, on a
  // narrow screen where the numbered items are hidden, the next (else the
  // previous) arrow, else the pager itself.
  const pagerFocusTarget = (pager) => {
    const visible = (el) => el && el.getClientRects().length > 0;
    const target = [
      ".is-active .pager__link",
      ".pager__link--next",
      ".pager__link--previous",
    ]
      .map((selector) => pager.querySelector(selector))
      .find(visible);
    if (target) {
      return target;
    }
    pager.setAttribute("tabindex", "-1");
    return pager;
  };

  Drupal.behaviors.ysViewsBasicFilterStatus = {
    attach(context) {
      // One delegated listener survives every AJAX swap of the pager markup.
      // It listens in the capture phase because Drupal.ajax stops the click
      // from bubbling once it has started the request.
      once("ys-pager-click", "html", context).forEach((html) => {
        html.addEventListener(
          "click",
          (event) => {
            const link =
              event.target.closest && event.target.closest(".pager a[href]");
            const domId = link && listingDomId(link);
            if (domId) {
              pagerClicked[domId] = true;
            }
          },
          true
        );
        // A filter change after a pager click whose request never came back
        // must not inherit that click: it keeps focus where the editor is
        // typing and announces the count, not a page.
        ["input", "change"].forEach((type) => {
          html.addEventListener(
            type,
            (event) => {
              const form =
                event.target.closest &&
                event.target.closest("form.views-exposed-form");
              const domId =
                form && form.getAttribute("data-drupal-target-view");
              if (domId) {
                delete pagerClicked[domId];
              }
            },
            true
          );
        });
      });

      const counts = drupalSettings.ysViewsBasicFilter || {};
      Object.keys(counts).forEach((domId) => {
        const container = document.querySelector(`.js-view-dom-id-${domId}`);
        // once() re-processes the freshly rendered container after each AJAX swap.
        if (!container || !once("ys-filter-status", container).length) {
          return;
        }
        if (!recorded[domId]) {
          recorded[domId] = true;
          return;
        }
        const results = Drupal.formatPlural(
          counts[domId],
          "1 result",
          "@count results"
        );
        const pager = pagerClicked[domId] && container.querySelector(".pager");
        delete pagerClicked[domId];
        if (!pager) {
          Drupal.announce(results);
          return;
        }
        const target = pagerFocusTarget(pager);
        if (target.matches("span")) {
          target.setAttribute("tabindex", "-1");
        }
        // Plain focus(): it scrolls only when the target is out of view,
        // which happens when a shorter last page moves the pager up.
        target.focus();
        const current = pager.querySelector(".is-active .pager__link");
        Drupal.announce(
          Drupal.t("Page @page, @results", {
            "@page": current ? current.textContent.replace(/\D+/g, "") : "",
            "@results": results,
          })
        );
      });
    },
  };
})(Drupal, drupalSettings, once);
