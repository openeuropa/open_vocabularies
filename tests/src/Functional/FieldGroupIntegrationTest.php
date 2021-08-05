<?php

declare(strict_types = 1);

namespace Drupal\Tests\open_vocabularies\Functional;

use Drupal\Tests\field_group\Functional\FieldGroupTestTrait;

/**
 * Class for testing the integration with the field_group module.
 *
 * @ingroup open_vocabularies
 */
class FieldGroupIntegrationTest extends OpenVocabularyTestBase {

  use FieldGroupTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_group',
    'field_ui',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->createGroup('node', 'page', 'form', 'default', [
      'group_name' => 'foo',
      'label' => 'Group foo',
      'format_type' => 'fieldset',
    ]);
    $this->createGroup('node', 'page', 'form', 'default', [
      'group_name' => 'bar',
      'label' => 'Group bar',
      'format_type' => 'fieldset',
    ]);
  }

  /**
   * Tests the placement of computed fields inside form field groups.
   */
  public function testFormPlacement(): void {
    $administrator = $this->createUser(['administer node form display']);
    $this->drupalLogin($administrator);
    $this->drupalGet('/admin/structure/types/manage/page/form-display');

    $assert_session = $this->assertSession();
    // Place the vocabulary reference placeholder widget inside the "foo" group,
    // followed by the responsible entity reference field.
    $assert_session->selectExists('fields[vocabulary_reference][region]')->selectOption('content');
    $assert_session->selectExists('fields[vocabulary_reference][parent]')->selectOption('group_foo');
    $assert_session->fieldExists('fields[vocabulary_reference][weight]')->setValue(0);
    $assert_session->selectExists('fields[responsible][region]')->selectOption('content');
    $assert_session->selectExists('fields[responsible][parent]')->selectOption('group_foo');
    $assert_session->fieldExists('fields[responsible][weight]')->setValue(1);
    // Make sure that a consistent widget is used for the extra entity
    // reference field.
    $assert_session->fieldExists('fields[responsible][type]')->setValue('options_select');
    $this->getSession()->getPage()->pressButton('Save');

    $user = $this->createUser([
      'access content',
      'create page content',
      'edit any page content',
      'view test entity',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('/node/add/page');

    $fieldset = $assert_session->elementExists('named', [
      'fieldset',
      'Group foo',
    ]);
    // Prepare an XPath to assert the position of the elements.
    // The first element inside the fieldset is the wrapper itself.
    $xpath = '((./div[@class and contains(concat(" ", normalize-space(@class), " "), " fieldset-wrapper ")]'
      // The first element is the participating countries wrapper.
      . '/div[@class and contains(concat(" ", normalize-space(@class), " "), " field--type-entity-reference ")]'
      . '[.//table//th/h4[text()="Participating countries"]]'
      // Get the first following sibling.
      . '/following-sibling::div)[1]'
      // Verify that is the hosting country wrapper.
      . '[@class and contains(concat(" ", normalize-space(@class), " "), " field--type-entity-reference ")]'
      . '[.//label[text()="Hosting country"]]'
      // The next sibling is the wrapper of the responsible person field.
      . '/following-sibling::div)[1]'
      . '[@class and contains(concat(" ", normalize-space(@class), " "), " field--type-entity-reference ")]'
      . '[.//label[text()="Responsible person"]]';
    $assert_session->elementExists('xpath', $xpath, $fieldset);

    // Log back as administrator and move the vocabulary reference widget
    // inside the "bar" group.
    $this->drupalLogin($administrator);
    $this->drupalGet('/admin/structure/types/manage/page/form-display');
    $assert_session->selectExists('fields[vocabulary_reference][parent]')->selectOption('group_bar');
    $this->getSession()->getPage()->pressButton('Save');

    $this->drupalLogin($user);
    $this->drupalGet('/node/add/page');

    // The "foo" fieldset group doesn't contain anymore the computed reference
    // fields.
    $fieldset_foo = $assert_session->elementExists('named', [
      'fieldset',
      'Group foo',
    ]);
    $assert_session->fieldNotExists('Participating countries', $fieldset_foo);
    $assert_session->fieldNotExists('Hosting country', $fieldset_foo);
    $assert_session->fieldExists('Responsible person', $fieldset_foo);

    // The fields show correctly inside the "bar" group.
    $fieldset_bar = $assert_session->elementExists('named', [
      'fieldset',
      'Group bar',
    ]);
    $assert_session->fieldExists('Participating countries', $fieldset_bar);
    $assert_session->fieldExists('Hosting country', $fieldset_bar);
  }

}
