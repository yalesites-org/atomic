/**
 * @file
 * Suppress the Views AJAX "scroll to top" for listing exposed-filter submits.
 *
 * Core's ViewAjaxController adds a ScrollTopCommand (the `scrollTop` AJAX
 * command) whenever the AJAX request carries a `pager_element` — which the
 * Views AJAX settings always include, so it fires on filter auto-submit as well
 * as pager clicks. On a pager click the scroll is wanted, but the listing
 * filters auto-submit on every keystroke/selection via Better Exposed Filters,
 * so the same scroll yanks the page down mid-interaction (#1299 QA). Wrap the
 * command and skip it only when the request came from one of the listing
 * exposed-filter forms; pagers, other views, and other modules keep the default
 * scroll.
 */
((Drupal, drupalSettings) => {
  const commands = Drupal.AjaxCommands && Drupal.AjaxCommands.prototype;
  // Bail if core's scrollTop command is missing, or we already wrapped it. The
  // guard flag lives on the wrapper function, not on the command prototype, so
  // it is never mistaken for an AJAX command during command dispatch.
  if (
    !commands ||
    typeof commands.scrollTop !== "function" ||
    commands.scrollTop.ysListingWrapped
  ) {
    return;
  }

  const original = commands.scrollTop;

  // True when the AJAX request came from a listing view's exposed filter form.
  // The exposed-form AJAX is bound to elements inside the form (the submit
  // button that Better Exposed Filters triggers on auto-submit), whereas pager
  // AJAX is bound to a pager link that is not inside the form — so a pager still
  // scrolls. The form carries the view's dom id in data-drupal-target-view
  // (ViewsExposedForm), and atomic keys the same dom ids in ysViewsBasicFilter
  // for exactly the listing views, so that registry is the single source of
  // truth for "is this one of our listings" (no hardcoded view machine name).
  const isListingFilterSubmit = (ajax) => {
    const element = ajax && ajax.element;
    const form =
      element && element.closest
        ? element.closest("form.views-exposed-form")
        : null;
    if (!form) {
      return false;
    }
    const domId = form.getAttribute("data-drupal-target-view");
    const registry =
      (drupalSettings && drupalSettings.ysViewsBasicFilter) || {};
    return !!(domId && Object.prototype.hasOwnProperty.call(registry, domId));
  };

  const scrollTop = function scrollTop(ajax, response, status) {
    if (isListingFilterSubmit(ajax)) {
      return;
    }
    original.call(this, ajax, response, status);
  };
  scrollTop.ysListingWrapped = true;
  commands.scrollTop = scrollTop;
})(Drupal, drupalSettings);
