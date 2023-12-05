<?php

namespace Drupal\views_term_hierarchy_weight_field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Service for fields.
 */
class Fields {

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected \Drupal\Core\Entity\EntityStorageInterface $taxonomyStorage;


  /**
   * @inheritDoc
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->taxonomyStorage = $entityTypeManager->getStorage('taxonomy_term');
  }

  /**
   * @param \Drupal\taxonomy\VocabularyInterface $vocabulary
   *
   * @return void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createFields(VocabularyInterface $vocabulary) {
    FieldConfig::create([
      'field_name' => 'field_tax_hierarchical_weight',
      'entity_type' => 'taxonomy_term',
      'bundle' => $vocabulary->id(),
      'label' => 'Hierarchical Weight',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_tax_hierarchical_depth',
      'entity_type' => 'taxonomy_term',
      'bundle' => $vocabulary->id(),
      'label' => 'Hierarchical Depth',
    ])->save();

    $tree = $this->taxonomyStorage->loadTree($vocabulary->id(), 0, NULL, FALSE);

    views_term_hierarchy_weight_field_calculate_and_set_for_tree($tree);
  }

}
