<?php

declare(strict_types=1);

namespace Drupal\Tests\open_vocabularies\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\open_vocabularies\Traits\VocabularyTestTrait;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\open_vocabularies\Entity\OpenVocabularyAssociation;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Tests the vocabulary reference field formatters.
 *
 * @group open_vocabularies
 */
class VocabularyReferenceFormatterTest extends KernelTestBase {

  use VocabularyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'entity_test',
    'field',
    'language',
    'open_vocabularies',
    'open_vocabularies_test',
    'system',
    'user',
  ];

  /**
   * The referenceable countries, keyed by label.
   *
   * @var \Drupal\entity_test\Entity\EntityTestWithBundle[]
   */
  protected $countries = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Use Classy theme for testing markup output.
    \Drupal::service('theme_installer')->install(['starterkit_theme']);
    $this->config('system.theme')->set('default', 'starterkit_theme')->save();

    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_with_bundle');
    $this->installConfig([
      'language',
      'content_translation',
      'field',
      'user',
    ]);

    // Allow anonymous users to see test entities and their translations.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('view test entity')
      ->grantPermission('view test entity translations')
      ->save();

    // Create a vocabulary reference field.
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'entity_test',
      'type' => 'open_vocabulary_reference',
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'entity_test',
    ])->save();

    // Set the correct formatter for the field.
    \Drupal::service('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test')
      ->setComponent('field_test', [
        'type' => 'open_vocabulary_reference_label',
        'settings' => [
          'link' => FALSE,
        ],
      ])
      ->save();

    // Create a test bundle to use as referenced bundle.
    EntityTestBundle::create(['id' => 'country'])->save();

    // Create a vocabulary with two associations.
    $this->createVocabulary([
      'id' => 'countries',
      'handler' => 'entity_test_with_bundle',
      'handler_settings' => [
        'target_bundles' => [
          'country' => 'country',
        ],
      ],
    ]);
    $this->createVocabularyAssociation('countries', [
      'label' => 'Participating countries',
      'name' => 'participating',
      'cardinality' => OpenVocabularyAssociationInterface::CARDINALITY_UNLIMITED,
      'fields' => [
        'entity_test.entity_test.field_test',
      ],
    ]);
    $this->createVocabularyAssociation('countries', [
      'label' => 'Hosting country',
      'name' => 'hosting',
      'cardinality' => 1,
      'fields' => [
        'entity_test.entity_test.field_test',
      ],
    ]);

    // Enable translations for the country bundle.
    ConfigurableLanguage::createFromLangcode('it')->save();
    \Drupal::service('content_translation.manager')->setEnabled('entity_test_with_bundle', 'country', TRUE);

    // Create a few countries.
    $countries = [
      'Italy' => 'Italia',
      'Belgium' => NULL,
      'Lithuania' => 'Lituania',
    ];
    foreach ($countries as $name => $translation) {
      /** @var \Drupal\entity_test\Entity\EntityTestWithBundle $country */
      $country = EntityTestWithBundle::create([
        'type' => 'country',
        'name' => $name,
      ]);
      $country->save();

      if ($translation) {
        $country->addTranslation('it', ['name' => $translation])->save();
      }

      $this->countries[$name] = $country;
    }

    // Create another vocabulary that reference a different entity type, to make
    // sure that the correct vocabulary and handlers are used.
    $this->createVocabulary([
      'id' => 'test_entities',
    ]);
    $this->createVocabularyAssociation('test_entities', [
      'label' => 'Related entities',
      'name' => 'related',
      'fields' => [
        'entity_test.entity_test.field_test',
      ],
    ]);
  }

  /**
   * Tests the entity label formatter.
   *
   * @covers \Drupal\open_vocabularies\Plugin\Field\FieldFormatter\VocabularyReferenceLabelFormatter
   */
  public function testLabelFormatter(): void {
    // Create an entity that will be referenced in the test.
    $referenced = EntityTest::create(['name' => 'Referenced']);
    $referenced->save();

    // Create the host entity.
    /** @var \Drupal\entity_test\Entity\EntityTest $entity */
    $entity = EntityTest::create();

    $entity->set('field_test', [
      [
        'target_association_id' => 'countries.participating',
        'target_id' => $this->countries['Italy']->id(),
      ],
      [
        'target_association_id' => 'countries.participating',
        'target_id' => $this->countries['Lithuania']->id(),
      ],
      [
        'target_association_id' => 'countries.participating',
        'target_id' => $this->countries['Belgium']->id(),
      ],
      [
        'target_association_id' => 'countries.hosting',
        'target_id' => $this->countries['Italy']->id(),
      ],
      [
        'target_association_id' => 'test_entities.related',
        'target_id' => $referenced->id(),
      ],
    ]);

    $build = $entity->get('field_test')->view('default');

    // The outside field wrapper contains the cache information returned by
    // the access checks.
    $this->assertEqualsCanonicalizing([
      'contexts' => ['user.permissions'],
      'tags' => [
        'open_vocabularies_test_access_tag:1',
        'open_vocabularies_test_access_tag:2',
        'open_vocabularies_test_access_tag:3',
      ],
      'max-age' => Cache::PERMANENT,
    ], $build['#cache']);

    // The lists have the cache metadata of the association.
    $this->assertEquals([
      'contexts' => ['languages:language_interface'],
      'tags' => ['config:open_vocabularies.open_vocabulary_association.countries.participating'],
      'max-age' => Cache::PERMANENT,
    ], $build[0]['#cache']);
    $this->assertEquals([
      'contexts' => ['languages:language_interface'],
      'tags' => ['config:open_vocabularies.open_vocabulary_association.countries.hosting'],
      'max-age' => Cache::PERMANENT,
    ], $build[1]['#cache']);
    $this->assertEquals([
      'contexts' => ['languages:language_interface'],
      'tags' => ['config:open_vocabularies.open_vocabulary_association.test_entities.related'],
      'max-age' => Cache::PERMANENT,
    ], $build[2]['#cache']);

    // Each item list has the cache metadata of the referenced entity.
    $this->assertEquals([
      'contexts' => [],
      'tags' => ['entity_test_with_bundle:1'],
      'max-age' => Cache::PERMANENT,
    ], $build[0]['#items'][0]['#cache']);
    $this->assertEquals([
      'contexts' => [],
      'tags' => ['entity_test_with_bundle:3'],
      'max-age' => Cache::PERMANENT,
    ], $build[0]['#items'][1]['#cache']);
    $this->assertEquals([
      'contexts' => [],
      'tags' => ['entity_test_with_bundle:2'],
      'max-age' => Cache::PERMANENT,
    ], $build[0]['#items'][2]['#cache']);
    $this->assertEquals([
      'contexts' => [],
      'tags' => ['entity_test_with_bundle:1'],
      'max-age' => Cache::PERMANENT,
    ], $build[1]['#items'][0]['#cache']);
    $this->assertEquals([
      'contexts' => [],
      'tags' => ['entity_test:1'],
      'max-age' => Cache::PERMANENT,
    ], $build[2]['#items'][0]['#cache']);

    // Render the formatter output.
    $html = (string) \Drupal::service('renderer')->renderRoot($build);
    $crawler = new Crawler($html);
    $wrapper = $crawler->filter('div.field__items');
    $this->assertCount(1, $wrapper);
    $lists = $wrapper->filter('div.field__item div.item-list');
    $this->assertCount(3, $lists);

    // Assert the titles of each list.
    $this->assertEquals('Participating countries', $lists->eq(0)->filter('h3')->html());
    $this->assertEquals('Hosting country', $lists->eq(1)->filter('h3')->html());
    $this->assertEquals('Related entities', $lists->eq(2)->filter('h3')->html());

    // Verify the items in the first list.
    $items = $lists->eq(0)->filter('ul li');
    $this->assertCount(3, $items);
    $this->assertEquals('Italy', $items->eq(0)->html());
    $this->assertEquals('Lithuania', $items->eq(1)->html());
    $this->assertEquals('Belgium', $items->eq(2)->html());
    // And in the second.
    $items = $lists->eq(1)->filter('ul li');
    $this->assertCount(1, $items);
    $this->assertEquals('Italy', $items->html());
    // Amd finally the third.
    $items = $lists->eq(2)->filter('ul li');
    $this->assertCount(1, $items);
    $this->assertEquals('Referenced', $items->html());

    // Set the formatter to link to the entity.
    \Drupal::service('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test')
      ->setComponent('field_test', [
        'type' => 'open_vocabulary_reference_label',
        'settings' => [
          'link' => TRUE,
        ],
      ])
      ->save();

    $build = $entity->get('field_test')->view('default');
    $html = (string) \Drupal::service('renderer')->renderRoot($build);
    $crawler = new Crawler($html);

    // Verify that the labels are linked to the entity path.
    $lists = $crawler->filter('div.field__item div.item-list');
    $links = $lists->eq(0)->filter('ul li a');
    $this->assertEquals($this->countries['Italy']->toUrl()->toString(), $links->eq(0)->attr('href'));
    $this->assertEquals('Italy', $links->eq(0)->html());
    $this->assertEquals($this->countries['Lithuania']->toUrl()->toString(), $links->eq(1)->attr('href'));
    $this->assertEquals('Lithuania', $links->eq(1)->html());
    $this->assertEquals($this->countries['Belgium']->toUrl()->toString(), $links->eq(2)->attr('href'));
    $this->assertEquals('Belgium', $links->eq(2)->html());
    // Verify also the second list.
    $links = $lists->eq(1)->filter('ul li a');
    $this->assertEquals($this->countries['Italy']->toUrl()->toString(), $links->eq(0)->attr('href'));
    $this->assertEquals('Italy', $links->eq(0)->html());
    // And the third.
    $links = $lists->eq(2)->filter('ul li a');
    $this->assertEquals($referenced->toUrl()->toString(), $links->eq(0)->attr('href'));
    $this->assertEquals('Referenced', $links->eq(0)->html());

    // Set Italian as the current language.
    \Drupal::service('language.default')->set(ConfigurableLanguage::load('it'));
    \Drupal::languageManager()->reset();

    $build = $entity->get('field_test')->view('default');
    $html = (string) \Drupal::service('renderer')->renderRoot($build);
    $crawler = new Crawler($html);

    // Verify that the labels are translated when a translation is available.
    $lists = $crawler->filter('div.field__item div.item-list');
    $links = $lists->eq(0)->filter('ul li a');
    $this->assertEquals('Italia', $links->eq(0)->html());
    $this->assertEquals('Lituania', $links->eq(1)->html());
    $this->assertEquals('Belgium', $links->eq(2)->html());
    // Verify the second link list.
    $this->assertEquals('Italia', $lists->eq(1)->filter('ul li a')->html());

    // Change the weight of the hosting country association.
    $association = OpenVocabularyAssociation::load('countries.hosting');
    $association->set('weight', -10)->save();

    $build = $entity->get('field_test')->view('default');
    $html = (string) \Drupal::service('renderer')->renderRoot($build);
    $crawler = new Crawler($html);

    // The order of the item lists follows the weight of the associations.
    $lists = $crawler->filter('div.field__item div.item-list');
    $this->assertEquals('Hosting country', $lists->eq(0)->filter('h3')->html());
    $this->assertEquals('Participating countries', $lists->eq(1)->filter('h3')->html());
    $this->assertEquals('Related entities', $lists->eq(2)->filter('h3')->html());

    // Prevent anonymous users to access translations.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->revokePermission('view test entity translations')
      ->save();
    // Reset the cached access results.
    \Drupal::entityTypeManager()->getAccessControlHandler('entity_test_with_bundle')->resetCache();

    $build = $entity->get('field_test')->view('default');

    // All the access cache metadata is still present, even if countries will
    // not be rendered.
    $this->assertEqualsCanonicalizing([
      'contexts' => ['user.permissions'],
      'tags' => [
        'open_vocabularies_test_access_tag:1',
        'open_vocabularies_test_access_tag:2',
        'open_vocabularies_test_access_tag:3',
      ],
      'max-age' => Cache::PERMANENT,
    ], $build['#cache']);
    $this->assertEquals([
      'contexts' => [
        'languages:language_interface',
      ],
      'tags' => ['config:open_vocabularies.open_vocabulary_association.countries.participating'],
      'max-age' => Cache::PERMANENT,
    ], $build[0]['#cache']);

    // Assert the rendering.
    $html = (string) \Drupal::service('renderer')->renderRoot($build);
    $crawler = new Crawler($html);
    $lists = $crawler->filter('div.field__item div.item-list');
    // The "hosting country" list is not rendered anymore.
    $this->assertCount(2, $lists);
    $this->assertEquals('Participating countries', $lists->eq(0)->filter('h3')->text());
    $this->assertEquals('Related entities', $lists->eq(1)->filter('h3')->text());
    // Only the accessible entities are rendered.
    $links = $lists->eq(0)->filter('a');
    $this->assertCount(1, $links);
    $this->assertEquals('Belgium', $links->html());
  }

}
