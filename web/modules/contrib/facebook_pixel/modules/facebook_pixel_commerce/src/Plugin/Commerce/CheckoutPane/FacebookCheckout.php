<?php

namespace Drupal\facebook_pixel_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facebook_pixel\FacebookEventInterface;
use Drupal\facebook_pixel_commerce\FacebookCommerceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the completion message pane.
 *
 * Hijack the pane form subsystem so that we can
 * call our addEvent for initialize checkout on the
 * first stage of checkout.
 *
 * @CommerceCheckoutPane(
 *   id = "facebook_checkout",
 *   label = @Translation("Facebook Checkout"),
 *   default_step = "order_information",
 * )
 */
class FacebookCheckout extends CheckoutPaneBase {

  /**
   * The facebook pixel event.
   *
   * @var \Drupal\facebook_pixel\FacebookEventInterface
   */
  protected $facebookEvent;

  /**
   * The facebook pixel comment.
   *
   * @var \Drupal\facebook_pixel_commerce\FacebookCommerceInterface
   */
  protected $facebookComment;

  /**
   * Constructs a new CheckoutPaneBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $checkout_flow
   *   The parent checkout flow.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\facebook_pixel\FacebookEventInterface $facebook_event
   *   The facebook pixel event.
   * @param \Drupal\facebook_pixel_commerce\FacebookCommerceInterface $facebook_comment
   *   The facebook pixel commerce.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow, EntityTypeManagerInterface $entity_type_manager, FacebookEventInterface $facebook_event, FacebookCommerceInterface $facebook_comment) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);

    $this->facebookEvent = $facebook_event;
    $this->facebookComment = $facebook_comment;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager'),
      $container->get('facebook_pixel.facebook_event'),
      $container->get('facebook_pixel_commerce.facebook_commerce')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Only fire the FB event on page load.
    if (!$form_state->getTriggeringElement()) {
      $data = $this->facebookComment->getOrderData($this->order);
      $this->facebookEvent->addEvent('InitiateCheckout', $data);
    }
    return [];
  }

}
