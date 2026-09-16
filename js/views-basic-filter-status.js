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
 */
((Drupal, drupalSettings, once) => {
  const recorded = {};

  Drupal.behaviors.ysViewsBasicFilterStatus = {
    attach() {
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
        Drupal.announce(
          Drupal.formatPlural(counts[domId], "1 result", "@count results")
        );
      });
    },
  };
})(Drupal, drupalSettings, once);
