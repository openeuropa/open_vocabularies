<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\src\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Base test class for the open vocabularies form tests.
 */
class OpenVocabulariesFormTestBase extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'open_vocabularies',
    'open_vocabularies_test',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('system_messages_block');
    $this->drupalPlaceBlock('page_title_block');

    EntityTestBundle::create([
      'id' => 'alpha',
      'label' => 'Alpha',
    ])->save();
    EntityTestBundle::create([
      'id' => 'beta',
      'label' => 'Beta',
    ])->save();
  }

  /**
   * Checks if a select element contains the specified options.
   *
   * @param string $name
   *   The field name.
   * @param array $expected_options
   *   An array of expected options.
   */
  protected function assertFieldSelectOptions(string $name, array $expected_options): void {
    $field = $this->getSession()->getPage()->findField($name);
    if (!$field) {
      $this->fail('Unable to find field ' . $name);
      return;
    }

    $options = $field->findAll('xpath', 'option');
    array_walk($options, function (NodeElement &$option) {
      $option = $option->getText();
    });
    sort($options);
    sort($expected_options);
    $this->assertIdentical($options, $expected_options);
  }

}
