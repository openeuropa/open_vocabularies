<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\FunctionalJavascript;

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

}
