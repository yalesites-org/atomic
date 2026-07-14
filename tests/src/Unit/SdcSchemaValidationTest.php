<?php

namespace Drupal\Tests\atomic\Unit;

use Drupal\Tests\UnitTestCase;
use JsonSchema\Validator;
use Symfony\Component\Yaml\Yaml;

/**
 * Validates SDC component prop schemas against sample data.
 *
 * This is the lightweight, mechanical schema-validation pattern every wave of
 * the SDC migration (epic #1351) reuses: valid data passes, invalid data (wrong
 * enum / missing required) fails predictably. It uses justinrainbow/json-schema
 * (available via drupal/core-dev) directly against the props schema authored in
 * each *.component.yml, so it needs no Drupal bootstrap and runs fast in CI.
 *
 * Render-level enforcement (that SDC actually applies these schemas when
 * assertions are on) is covered by the Kernel test.
 *
 * @group yalesites
 * @group sdc
 */
class SdcSchemaValidationTest extends UnitTestCase {

  /**
   * Loads the props schema from a component's *.component.yml.
   */
  private function propsSchema(string $name): object {
    $file = __DIR__ . "/../../../components/$name/$name.component.yml";
    $this->assertFileExists($file, "component.yml for $name should exist");
    $meta = Yaml::parseFile($file);
    // Convert the parsed props array to the stdClass shape json-schema expects.
    return json_decode(json_encode($meta['props']));
  }

  /**
   * Validates $data against a component's props schema; returns error array.
   */
  private function validate(string $name, array $data): array {
    $validator = new Validator();
    // Cast to object so an empty prop set encodes as {} (a JSON object) rather
    // than [] (a JSON array), which would fail the schema's "type: object".
    $subject = json_decode(json_encode((object) $data));
    $validator->validate($subject, $this->propsSchema($name));
    return $validator->getErrors();
  }

  /**
   * Divider: the cheap case. Valid width/position pass.
   */
  public function testDividerValidData(): void {
    $this->assertSame([], $this->validate('divider', [
      'divider__width' => '50',
      'divider__position' => 'Left',
    ]));
  }

  /**
   * Divider: an out-of-enum width fails predictably.
   */
  public function testDividerInvalidWidthFails(): void {
    $this->assertNotEmpty($this->validate('divider', [
      'divider__width' => '999',
    ]));
  }

  /**
   * Callout: a valid dial value passes.
   */
  public function testCalloutValidDial(): void {
    $this->assertSame([], $this->validate('callout', [
      'callout__background_color' => 'three',
      'callout__alignment' => 'left',
    ]));
  }

  /**
   * Callout: an invalid dial value fails.
   *
   * This is the no-drift enforcement, proven at the schema level (render-level
   * enforcement is covered separately).
   */
  public function testCalloutInvalidDialFails(): void {
    $this->assertNotEmpty($this->validate('callout', [
      'callout__background_color' => 'not-a-real-theme',
      'callout__alignment' => 'center',
    ]));
  }

  /**
   * Accordion: a valid dial value passes.
   */
  public function testAccordionValidDial(): void {
    $this->assertSame([], $this->validate('accordion', [
      'accordion__theme' => 'default',
      'accordion__alignment' => 'left',
      'accordion__width' => 'content',
    ]));
  }

  /**
   * Text atom (Wave 1): a valid wrapper element passes.
   */
  public function testTextValidData(): void {
    $this->assertSame([], $this->validate('text', ['text__element' => 'p']));
  }

  /**
   * Heading atom (Wave 1): a valid level passes.
   */
  public function testHeadingValidLevel(): void {
    $this->assertSame([], $this->validate('heading', ['heading__level' => '3']));
  }

  /**
   * Heading atom (Wave 1): an out-of-range level fails.
   */
  public function testHeadingInvalidLevelFails(): void {
    $this->assertNotEmpty($this->validate('heading', ['heading__level' => '9']));
  }

  /**
   * CTA atom (Wave 1): a valid fill style passes; an invalid one fails.
   */
  public function testCtaStyle(): void {
    $this->assertSame([], $this->validate('cta', ['cta__style' => 'outline']));
    $this->assertNotEmpty($this->validate('cta', ['cta__style' => 'bogus']));
  }

  /**
   * Text Link atom (Wave 1): a valid type passes; an invalid one fails.
   */
  public function testTextLinkType(): void {
    $this->assertSame([], $this->validate('text-link', ['link__type' => 'external']));
    $this->assertNotEmpty($this->validate('text-link', ['link__type' => 'bogus']));
  }

  /**
   * Text Copy Button atom (Wave 1): valid data (with an optional tag) passes.
   */
  public function testTextCopyButtonValid(): void {
    $this->assertSame([], $this->validate('text-copy-button', [
      'text_copy_button__pre_text' => 'copy me',
      'text_copy_button__pre_text_tag' => 'span',
    ]));
  }

  /**
   * Lists atom (Wave 1, codegen-seeded): a valid type passes; invalid fails.
   */
  public function testListsType(): void {
    $this->assertSame([], $this->validate('lists', ['list__type' => 'ol']));
    $this->assertNotEmpty($this->validate('lists', ['list__type' => 'table']));
  }

  /**
   * Date Time atom (Wave 1): a valid format passes; an invalid one fails.
   */
  public function testDateTimeFormat(): void {
    $this->assertSame([], $this->validate('date-time', [
      'date_time__start' => 1700000000,
      'date_time__format' => 'date',
    ]));
    $this->assertNotEmpty($this->validate('date-time', [
      'date_time__start' => 1700000000,
      'date_time__format' => 'bogus',
    ]));
    // Omitting the required date_time__start fails.
    $this->assertNotEmpty($this->validate('date-time', ['date_time__format' => 'date']));
  }

  /**
   * Skip Link atom (Wave 1): valid data passes.
   */
  public function testLinkSkipValid(): void {
    $this->assertSame([], $this->validate('link-skip', ['link_skip__url' => '#main']));
  }

  /**
   * Read Time atom (Wave 1): valid data passes.
   */
  public function testReadTimeValid(): void {
    $this->assertSame([], $this->validate('read-time', ['read_time__label' => 'Read time']));
  }

  /**
   * Taxonomy Display (Wave 1): a valid theme passes; an invalid one fails.
   */
  public function testTaxonomyDisplayTheme(): void {
    $this->assertSame([], $this->validate('taxonomy-display', ['taxonomy_display__theme' => 'one']));
    $this->assertNotEmpty($this->validate('taxonomy-display', ['taxonomy_display__theme' => 'nope']));
  }

  /**
   * Image atom (Wave 1): valid data (with an optional caption) passes.
   */
  public function testImageValid(): void {
    $this->assertSame([], $this->validate('image', ['figure__caption' => 'A caption']));
    $this->assertSame([], $this->validate('image', ['figure__caption' => NULL]));
  }

  /**
   * Basic Meta (Wave 1): the (prop-less) schema accepts an empty prop set.
   */
  public function testBasicMetaValid(): void {
    $this->assertSame([], $this->validate('basic-meta', []));
  }

  /**
   * Inline Message (Wave 2): a valid theme/type passes; an invalid theme fails.
   */
  public function testInlineMessage(): void {
    $this->assertSame([], $this->validate('inline-message', [
      'inline_message__theme' => 'two',
      'inline_message__type' => 'marketing',
    ]));
    $this->assertNotEmpty($this->validate('inline-message', [
      'inline_message__theme' => 'nope',
    ]));
  }

  /**
   * Pull Quote (Wave 2): a valid style passes; an invalid one fails.
   */
  public function testPullQuoteStyle(): void {
    $this->assertSame([], $this->validate('pull-quote', ['pull_quote__style' => 'quote-left']));
    $this->assertNotEmpty($this->validate('pull-quote', ['pull_quote__style' => 'nope']));
  }

  /**
   * Wrapped Image (Wave 2): valid dials pass; an invalid style fails.
   */
  public function testWrappedImage(): void {
    $this->assertSame([], $this->validate('wrapped-image', [
      'wrapped_image__style' => 'floated',
      'wrapped_image__alignment' => 'right',
    ]));
    $this->assertNotEmpty($this->validate('wrapped-image', ['wrapped_image__style' => 'nope']));
  }

  /**
   * Wrapped Callout (Wave 2): valid dials pass; an invalid theme fails.
   */
  public function testWrappedCallout(): void {
    $this->assertSame([], $this->validate('wrapped-callout', [
      'wrapped_callout__theme' => 'two',
      'wrapped_callout__alignment' => 'right',
    ]));
    $this->assertNotEmpty($this->validate('wrapped-callout', ['wrapped_callout__theme' => 'nope']));
  }

  /**
   * Link Grid (Wave 2): valid themes (incl. "inherit") pass; invalid fails.
   */
  public function testLinkGrid(): void {
    $this->assertSame([], $this->validate('link-grid', ['link_grid__theme' => 'three']));
    $this->assertSame([], $this->validate('link-grid', ['link_grid__theme' => 'inherit']));
    $this->assertNotEmpty($this->validate('link-grid', ['link_grid__theme' => 'nope']));
  }

  /**
   * Quick Links (Wave 2): a valid variation passes; an invalid one fails.
   */
  public function testQuickLinks(): void {
    $this->assertSame([], $this->validate('quick-links', ['quick_links__variation' => 'subtle']));
    $this->assertNotEmpty($this->validate('quick-links', ['quick_links__variation' => 'nope']));
  }

  /**
   * Embed (Wave 3): valid data passes.
   */
  public function testEmbed(): void {
    $this->assertSame([], $this->validate('embed', ['embed__width' => 'site']));
  }

  /**
   * Video (Wave 3): a valid alignment passes; an invalid one fails.
   */
  public function testVideo(): void {
    $this->assertSame([], $this->validate('video', ['video__alignment' => 'center']));
    $this->assertNotEmpty($this->validate('video', ['video__alignment' => 'nope']));
  }

  /**
   * Tabs (Wave 3): valid data passes.
   */
  public function testTabs(): void {
    $this->assertSame([], $this->validate('tabs', ['tabs__id' => 'abc', 'tabs__theme' => 'one']));
  }

  /**
   * Text With Image / Content Spotlight (Wave 4): valid data passes.
   */
  public function testTextWithImage(): void {
    $this->assertSame([], $this->validate('text-with-image', [
      'text_with_image__position' => 'image-left',
      'text_with_image__theme' => 'one',
    ]));
  }

  /**
   * Tiles (Wave 5): valid dials pass; an invalid grid count fails.
   */
  public function testTiles(): void {
    $this->assertSame([], $this->validate('tiles', [
      'tiles__alignment' => 'center',
      'tiles__grid_count' => 'three',
      'tiles__vertical_alignment' => 'top',
    ]));
    $this->assertNotEmpty($this->validate('tiles', ['tiles__grid_count' => 'five']));
  }

  /**
   * Breadcrumbs (Wave 7, global chrome): a valid item trail passes.
   *
   * A missing required item field, or the absent required items array, fails.
   */
  public function testBreadcrumbs(): void {
    $this->assertSame([], $this->validate('breadcrumbs', [
      'breadcrumbs__items' => [
        ['title' => 'Home', 'url' => '/'],
        ['title' => 'Chemistry', 'url' => '/chem', 'is_active' => TRUE],
      ],
    ]));
    // Omitting the required breadcrumbs__items fails.
    $this->assertNotEmpty($this->validate('breadcrumbs', [
      'breadcrumbs__modifiers' => 'collapsible',
    ]));
    // An item missing its required title fails.
    $this->assertNotEmpty($this->validate('breadcrumbs', [
      'breadcrumbs__items' => [['url' => '/no-title']],
    ]));
  }

  /**
   * Profile Meta (Wave 1): valid image dials pass; bad orientation fails.
   */
  public function testProfileMetaValid(): void {
    $this->assertSame([], $this->validate('profile-meta', [
      'profile_meta__heading' => 'Jane Doe',
      'profile_meta__image_orientation' => 'portrait',
      'profile_meta__image_style' => 'outdent',
      'profile_meta__image_alignment' => 'right',
    ]));
    $this->assertNotEmpty($this->validate('profile-meta', [
      'profile_meta__heading' => 'Jane Doe',
      'profile_meta__image_orientation' => 'diagonal',
    ]));
  }

  /**
   * Page Title (Wave 1): a valid display value passes; an invalid one fails.
   */
  public function testPageTitleValid(): void {
    $this->assertSame([], $this->validate('page-title', [
      'page_title__heading' => 'About',
      'page_title__display' => 'visually-hidden',
      'page_title__show_social_media_sharing_links' => 'true',
    ]));
    $this->assertNotEmpty($this->validate('page-title', [
      'page_title__heading' => 'About',
      'page_title__display' => 'nope',
    ]));
  }

  /**
   * Event Meta (Wave 1): a valid heading + array/object props pass.
   */
  public function testEventMetaValid(): void {
    $this->assertSame([], $this->validate('event-meta', [
      'event_title__heading' => 'Reception',
      'event_dates' => [['formatted_start_date' => 'Jan 1']],
      'event_types' => [['name' => 'Talk', 'url' => '/t']],
      'event_meta__with_calendar' => TRUE,
    ]));
  }

  /**
   * Publication Meta (Wave 1): a valid heading + typed props pass.
   */
  public function testPublicationMetaValid(): void {
    $this->assertSame([], $this->validate('publication-meta', [
      'publication_detail__heading' => 'Policy Brief',
      'publication_detail__resource_type' => 'document',
      'publication_detail__authors' => [['label' => 'A. Author', 'url' => '/a']],
    ]));
  }

  /**
   * Facts and Figures (Wave 2): valid dials pass; a bad grid count fails.
   */
  public function testFactsValid(): void {
    $this->assertSame([], $this->validate('facts', [
      'facts_and_figures__group__grid_count' => 'three',
      'facts_and_figures__group__theme' => 'two',
      'facts_and_figures__group__alignment' => 'center',
      'facts_and_figures__group__bg_image' => 'true',
    ]));
    $this->assertNotEmpty($this->validate('facts', [
      'facts_and_figures__group__grid_count' => 'five',
    ]));
  }

  /**
   * Quote Callout (Wave 2): a valid style/theme passes; a bad style fails.
   */
  public function testQuoteCalloutValid(): void {
    $this->assertSame([], $this->validate('quote-callout', [
      'quote_callout__style' => 'quote',
      'quote_callout__accent_theme' => 'three',
      'quote_callout__quote_alignment' => 'right',
    ]));
    $this->assertNotEmpty($this->validate('quote-callout', [
      'quote_callout__style' => 'nope',
    ]));
  }

  /**
   * Content Spotlight Portrait (Wave 2): valid dials pass; bad position fails.
   */
  public function testContentSpotlightPortraitValid(): void {
    $this->assertSame([], $this->validate('content-spotlight-portrait', [
      'content_spotlight_portrait__position' => 'image-right',
      'content_spotlight_portrait__style' => 'offset',
      'content_spotlight_portrait__theme' => 'two',
      'content_spotlight_portrait__vertical_align' => 'top',
    ]));
    $this->assertNotEmpty($this->validate('content-spotlight-portrait', [
      'content_spotlight_portrait__position' => 'image-top',
    ]));
  }

}
