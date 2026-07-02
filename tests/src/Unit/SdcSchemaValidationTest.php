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
    $subject = json_decode(json_encode($data));
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

}
