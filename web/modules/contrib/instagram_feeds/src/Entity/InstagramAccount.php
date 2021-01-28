<?php

namespace Drupal\instagram_feeds\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use GuzzleHttp\Client;

/**
 * Defines the instagram_account entity class.
 *
 * @ContentEntityType(
 *   id = "instagram_account",
 *   label = @Translation("Instagram Account"),
 *   label_collection = @Translation("Instagram Accounts"),
 *   label_singular = @Translation("Instagram account"),
 *   label_plural = @Translation("Instagram accounts"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Instagram account",
 *     plural = "@count Instagram accounts"
 *   ),
 *   bundle_label = @Translation("Instagram Account"),
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\instagram_feeds\Form\InstagramAccountForm",
 *       "delete" = "Drupal\instagram_feeds\Form\InstagramAccountDeleteForm",
 *       "edit" = "Drupal\instagram_feeds\Form\InstagramAccountForm"
 *     },
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "list_builder" = "Drupal\instagram_feeds\InstagramAccountListBuilder"
 *   },
 *   admin_permission = "administer instagram_feeds",
 *   base_table = "instagram_account",
 *   fieldable = TRUE,
 *   field_ui_base_route = "entity.instagram_account.collection",
 *   entity_keys = {
 *     "id" = "iid",
 *     "label" = "account",
 *     "uuid" = "uuid",
 *     "published" = "status",
 *     "status" = "status",
 *     "uid" = "uid",
 *     "cron_import_limit" = "cron_import_limit",
 *     "media_bundle" = "media_bundle",
 *     "token" = "token",
 *     "token_expiration" = "token_expiration"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/media/instagram_feeds/add",
 *     "canonical" = "/admin/config/media/instagram_feeds/manage/{instagram_account}",
 *     "collection" = "/admin/config/media/instagram_feed",
 *     "delete-form" = "/admin/config/media/instagram_feeds/manage/{instagram_account}/delete",
 *     "edit-form" = "/admin/config/media/instagram_feeds/manage/{instagram_account}/edit"
 *   }
 * )
 */
class InstagramAccount extends ContentEntityBase implements InstagramAccountInterface {

  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = [];
    $fields['iid'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Instagram Account ID'))
      ->setSetting('max_length', 32)
      ->addConstraint('UniqueField');

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(new TranslatableMarkup('UUID'))
      ->setReadOnly(TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Published'))
      ->setDefaultValue(TRUE);

    $fields['account'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Instagram Username'))
      ->setDescription(t('The Instagram Account unique username.'))
      ->setSetting('max_length', 30)
      ->addConstraint('UniqueField');

    $fields['cron_import_limit'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Cron Import Limit'))
      ->setDefaultValue(10)
      ->setSetting('min', 1)
      ->setSetting('max', 25);

    $allowed_media_types = static::getInstagramMediaTypes();
    $fields['media_bundle'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Media Bundle'))
      ->setSetting('max_length', 32)
      ->setSetting('allowed_values', $allowed_media_types)
      ->setDefaultValue(array_shift($allowed_media_types));

    $fields['token'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Instagram API Token'))
//      ->setReadOnly(TRUE)
      ->setSetting('max_length', 255);

    $fields['token_expiration'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Instagram Token Expiration Date'))
      ->setReadOnly(TRUE);

    $fields['last_import'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Last Import Date'))
      ->setReadOnly(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Authored by'))
      ->setDescription(new TranslatableMarkup('The username of the author.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getCurrentUserId');

    $weight = -1 - count($fields) * 5;
    /** @var \Drupal\Core\Field\BaseFieldDefinition $field */
    foreach ($fields as $id => $field) {
      $view_settings = $form_settings = ['weight' => $weight];
      if ($id == 'uid') {
        $view_settings['type'] = 'author';
        $form_settings += [
          'type' => 'entity_reference_autocomplete',
          'settings' => [
            'match_operator' => 'CONTAINS',
            'size' => '60',
            'placeholder' => '',
          ],
        ];
      }
      $field->setDisplayOptions('view', $view_settings);
      if ($id != 'last_import') {
        $field->setDisplayOptions('form', $form_settings);
        $field->setDisplayConfigurable('form', TRUE);
      }
      $weight += 5;
      if ($id != 'token') {
        $field->setDisplayConfigurable('view', TRUE);
      }
      if ($id != 'status') {
        $field->setRequired(TRUE);
      }
    }
    return $fields;
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

  /**
   * Gets Media types with Instagram source.
   *
   * @return array
   *   Media Types options for Select form element.
   */
  protected static function getInstagramMediaTypes(): array {
    $options = [];
    $media_types = \Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple();
    foreach ($media_types as $media_type) {
      /** @var \Drupal\media\MediaTypeInterface $media_type */
      if ($media_type->getSource()->getPluginId() === 'instagram') {
        $options[$media_type->id()] = $media_type->label();
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function tokenIsValid(): bool {
    $expires_in = $this->get('token_expiration')->isEmpty() ? [0] : $this->get('token_expiration')->first()->getValue();
    return !$this->get('token')->isEmpty() && \Drupal::time()->getRequestTime() < (int) reset($expires_in);
  }

  /**
   * {@inheritdoc}
   */
  public function getToken($client_id = '', $client_secret = '', $code = '', $save = FALSE): string {
    // Refresh token in any case if $code exists.
    if (!empty($code) && !empty($client_id) && !empty($client_secret)) {
      try {
        $this->setNewToken($client_id, $client_secret, $code, $save);
      } catch (\Exception $e) {
        \Drupal::messenger()->addError($e->getMessage());
      }
    }
    return $this->tokenIsValid() ? $this->get('token')->first()->getString() : '';
  }

  public function getTokenExpirationTime(): int {
    return (int) ($this->get('token_expiration')->isEmpty() ? 0: $this->get('token_expiration')->getString());
  }

  /**
   * Obtains short-lived token and exchanges it to long-lived one.
   *
   * @param string $client_id
   *   Instagram Client (App) ID.
   * @param string $client_secret
   *   Instagram Client (App) Secret.
   * @param string $code
   *   Instagram Auth code.
   * @param bool $save
   *   Save entity or not after token has been refreshed successfully.
   *
   * @return bool
   *   TRUE if success, FALSE otherwise.
   */
  protected function setNewToken($client_id, $client_secret, $code, $save = FALSE): bool {
    $httpClient = \Drupal::httpClient();
    // Obtain short-live token (valid 24 hours).
    $response = $httpClient->post(self::INSTAGRAM_API_ENDPOINT . '/oauth/access_token', [
      'form_params' => [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'authorization_code',
        'redirect_uri' => Url::fromRoute('entity.instagram_account.add_form')->setAbsolute()->toString(),
        'code' => $code,
      ],
    ]);
    $body = $this->extractInstagramData($response);
    $this->set('iid', $body['user_id']);
    // Exchange short-term token to long-lived one (valid for 60 days).
    $response = $httpClient->get(self::INSTAGRAM_GRAPH_ENDPOINT . '/access_token?' . http_build_query([
      'grant_type' => 'ig_exchange_token',
      'client_secret' => $client_secret,
      'access_token' => $body['access_token'],
    ]));
    $body = $this->extractInstagramData($response);
    $this->set('token_expiration', $body['expires_in'] + \Drupal::time()->getRequestTime());
    $this->set('token', $body['access_token']);
    if (!$this->isNew() && $save) {
      $this->save();
    }
    return TRUE;
  }

  /**
   * Extracts Instagram data from Guzzle response.
   *
   * @param \GuzzleHttp\Psr7\Response $response
   *   Guzzle response.
   *
   * @return array
   *   Extracted Instagram data.
   *
   * @throws \Exception
   */
  protected function extractInstagramData($response) {
    $body = Json::decode($response->getBody()->getContents());
    if ($response->getStatusCode() !== 200) {
      throw new \Exception($body['error_message'], $response->getStatusCode());
    }
    return $body;
  }

  /**
   * {@inheritdoc}
   */
  public function refreshToken(Client $http_client, $save = FALSE): bool {
    $time = \Drupal::time();
    // Token can be refreshed only once per day. It lives 60 days. Expired
    // token cannot be refreshed anymore. 60 days - 1 day = 59 days.
    if (!$this->tokenIsValid() || (int) $this->get('token_expiration')->first()->getString() - 5097600 >= $time->getCurrentTime()) {
      return FALSE;
    }
    try {
      $response = $http_client->get(self::INSTAGRAM_GRAPH_ENDPOINT . '/refresh_access_token?' . http_build_query([
        'grant_type' => 'ig_refresh_token',
        'access_token' => $this->getToken(),
      ]));
      $body = $this->extractInstagramData($response);
      $this->set('token', $body['access_token']);
      $this->set('token_expiration', $body['expires_in'] + $time->getCurrentTime());
    }
    catch (\Exception $e) {
      \Drupal::logger('instagram_feeds')->error('An error Occurred when refreshing Instagram token for account @name: <br />@error', [
        '@name' => $this->label(),
        '@error' => $e->getMessage(),
      ]);
    }
    if (!$this->isNew() && $save) {
      return (bool) $this->save();
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOauthUrl($client_id): Url {
    $url = self::INSTAGRAM_API_ENDPOINT . '/oauth/authorize?' . http_build_query([
      'client_id' => $client_id,
      'redirect_uri' => Url::fromRoute('entity.instagram_account.add_form')->setAbsolute()->toString(),
      'scope' => 'user_profile,user_media',
      'response_type' => 'code',
    ]);
    return Url::fromUri($url);
  }

  /**
   * {@inheritdoc}
   */
  public function getAccountName($save = FALSE): string {
    if ($this->get('account')->isEmpty()) {
      $token = $this->getToken();
      if (!$token) {
        return '';
      }
      $response = \Drupal::httpClient()->get(self::INSTAGRAM_GRAPH_ENDPOINT . '/me?' . http_build_query([
        'fields' => 'username',
        'access_token' => $token,
      ]));
      $body = $this->extractInstagramData($response);
      $this->set('account', $body['username']);
      if (!$this->isNew() && $save) {
        $this->save();
      }
    }
    return $this->get('account')->first()->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getLastImportTimestamp(): int {
    return (int) ($this->get('last_import')->getString() ?? 0);
  }

  /**
   * {@inheritdoc}
   */
  public function setLastImportTimestamp(int $timestamp = 0) {
    return $this->set('last_import', $timestamp, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->first()->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->getEntityKey('uid');
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCronLimit(): int {
    return (int) $this->getEntityKey('cron_import_limit');
  }

  /**
   * {@inheritdoc}
   */
  public function getMediaType(): string {
    return $this->getEntityKey('media_bundle');
  }
}
