<?php

namespace Drupal\single_content_sync\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * The event dispatched when the entity is exported.
 */
class ExportEvent extends Event {

  /**
   * The entity being exported.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected ContentEntityInterface $entity;

  /**
   * The content being exported.
   *
   * @var array
   */
  protected array $content;

  /**
   * Constructs a new ExportEvent object.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being exported.
   * @param array $content
   *   The content being exported.
   */
  public function __construct(ContentEntityInterface $entity, array $content) {
    $this->entity = $entity;
    $this->content = $content;
  }

  /**
   * Gets the entity being exported.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity being exported.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Gets the content being exported.
   *
   * @return array
   *   The exported content. The array keys are:
   *   - 'entity_type': The entity type.
   *   - 'bundle': The entity bundle.
   *   - 'uuid': The entity UUID.
   *   - 'base_fields': The entity base fields.
   *   - 'custom_fields': The entity custom fields.
   */
  public function getContent(): array {
    return $this->content;
  }

  /**
   * Sets the exported content.
   *
   * @param array $content
   *   The exported content. The same array keys should be preserved as returned
   *   by getContent().
   *
   * @return $this
   */
  public function setContent(array $content): self {
    $this->content = $content;
    return $this;
  }

}
