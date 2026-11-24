/**
 * @file
 * Re-run a listing exposed-filter submit that Drupal AJAX dropped.
 *
 * Drupal.Ajax.eventResponse() ignores a submit while the form's previous
 * request is still in flight. Clearing a listing search box auto-submits an
 * empty search that can take a couple of seconds, so an Enter pressed (or a
 * typing auto-submit fired) during that window was thrown away: the box said
 * "Raghunathan" while the list still showed everyone.
 *
 * Wrap eventResponse so that a submit dropped for this reason on one of our
 * listing exposed forms is remembered, then re-clicked once the in-flight
 * request completes. Only listing forms (the ones atomic registers in
 * ysViewsBasicFilter) are touched; every other AJAX element keeps core's
 * behavior. The re-click reads the field's current value, so the results end up
 * matching whatever is in the box.
 */
((Drupal, drupalSettings, $) => {
  const proto = Drupal.Ajax && Drupal.Ajax.prototype;
  if (
    !proto ||
    typeof proto.eventResponse !== "function" ||
    proto.eventResponse.ysListingRetry
  ) {
    return;
  }

  // View DOM ids whose submit was dropped while a request was in flight.
  const pending = new Set();

  // The view DOM id when the element is inside a listing exposed filter form.
  const listingDomId = (element) => {
    const form =
      element && element.closest
        ? element.closest("form.views-exposed-form")
        : null;
    const domId = form && form.getAttribute("data-drupal-target-view");
    const registry = drupalSettings.ysViewsBasicFilter || {};
    return domId && Object.prototype.hasOwnProperty.call(registry, domId)
      ? domId
      : null;
  };

  const original = proto.eventResponse;
  const eventResponse = function eventResponse(element, event) {
    if (this.ajaxing) {
      const domId = listingDomId(element);
      if (domId) {
        pending.add(domId);
      }
    }
    return original.call(this, element, event);
  };
  eventResponse.ysListingRetry = true;
  proto.eventResponse = eventResponse;

  // jQuery runs the request's own complete handler (which clears `ajaxing`)
  // before this global event, so the form accepts a new submit by now.
  $(document).on("ajaxComplete", () => {
    pending.forEach((domId) => {
      pending.delete(domId);
      const form = document.querySelector(
        `form.views-exposed-form[data-drupal-target-view="${domId}"]`
      );
      const submit =
        form &&
        form.querySelector(
          "[data-bef-auto-submit-click], input[type=submit], button[type=submit]"
        );
      if (submit) {
        submit.click();
      }
    });
  });
})(Drupal, drupalSettings, jQuery);
