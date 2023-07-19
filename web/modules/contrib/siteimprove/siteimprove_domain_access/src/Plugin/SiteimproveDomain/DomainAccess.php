<?php

namespace Drupal\siteimprove_domain_access\Plugin\SiteimproveDomain;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\siteimprove\Plugin\SiteimproveDomainBase;
use Drupal\siteimprove\Plugin\SiteimproveDomainInterface;

/**
 * Provides simple plugin instance of Siteimprove Domain settings.
 *
 * @SiteimproveDomain(
 *   id = "siteimprovedomain_domain_access",
 *   label = @Translation("Domain Access support"),
 *   description = @Translation("Automatically use the domains configured in Domain Access as Siteimprove frontend domains."),
 * )
 */
class DomainAccess extends SiteimproveDomainBase implements SiteimproveDomainInterface {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array &$form, FormStateInterface &$form_state, $plugin_definition) {
    parent::buildForm($form, $form_state, $plugin_definition);

    $form[$plugin_definition['id']]['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t("This plugin doesn't contain any settings. Domain names are automatically fetched from Domain Access."),
    ];

    return $form;
  }

  /**
   * Get the base url from a full url string.
   *
   * @param string $url
   *   A string containing a full url.
   *
   * @return string
   */
  private function getBaseUrl(string $url) {
    $url_parts = parse_url($url);
    $base_url = $url_parts['scheme'] . '://' . $url_parts['host'];

    if (isset($url_parts['port']) && $url_parts['port'] != 80 && $url_parts['port'] != 443) {
      $base_url .= ':' . $url_parts['port'];
    }

    return $base_url;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrls(?EntityInterface $entity = NULL) {
    $base_urls = [];

    if ($entity) {
      $domain_access = \Drupal::service('domain_access.manager');
      $urls = $domain_access->getContentUrls($entity);

      foreach ($urls as $url) {
        $base_urls[] = $this->getBaseUrl($url);
      }
    }

    if (empty($base_urls)) {
      $domain_negotiator = \Drupal::service('domain.negotiator');
      $active_hostname = $domain_negotiator->negotiateActiveHostname();
      $base_urls = is_array($active_hostname) ? $active_hostname : [$active_hostname];
    }

    return $base_urls;
  }

}
