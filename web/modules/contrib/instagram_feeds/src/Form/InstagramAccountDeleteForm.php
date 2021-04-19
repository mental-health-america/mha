<?php

namespace Drupal\instagram_feeds\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;

/**
 * Provides the Instagram Account deletion form.
 */
class InstagramAccountDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->getQuestion() . ' ' . parent::getDescription();
  }

}
