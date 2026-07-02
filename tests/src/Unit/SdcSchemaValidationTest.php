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

}
