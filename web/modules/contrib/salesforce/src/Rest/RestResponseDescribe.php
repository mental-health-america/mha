<?php

namespace Drupal\salesforce\Rest;

/**
 * Class RestResponseDescribe response wrapper.
 *
 * @package Drupal\salesforce\Rest
 */
class RestResponseDescribe extends RestResponse {

  /**
   * Array of field definitions for this SObject type, indexed by machine name.
   *
   * @var array
   */
  protected array $fields;

  /**
   * The name of this SObject type, e.g. "Contact", "Account", "Opportunity".
   *
   * @var string
   */
  protected string $name;

  /**
   * Flattened fields mapping field name => field label.
   *
   * @var array
   */
  private array $fieldOptions;

  /**
   * The following protected properties are those we expect from Describe
   * endpoint.
   *
   * For a full accounting and description of the API, refer to Salesforce
   * documentation
   *
   * @see https://developer.salesforce.com/docs/atlas.en-us.246.0.api.meta/api/sforce_api_calls_describesobjects_describesobjectresult.htm
   */
  // phpcs:disable
  protected array $actionOverrides;

  protected bool $activateable;

  protected ?string $associateEntityType;

  protected ?string $associateParentEntity;

  protected array $childRelationships;

  protected bool $compactLayoutable;

  protected bool $createable;

  protected bool $custom;

  protected bool $customSetting;

  protected bool $dataTranslationEnabled;

  protected bool $deepCloneable;

  protected ?string $defaultImplementation;

  protected bool $deletable;

  protected bool $deprecatedAndHidden;

  protected ?string $extendedBy;

  protected ?string $extendsInterfaces;

  protected bool $feedEnabled;

  protected ?string $implementedBy;

  protected ?string $implementsInterfaces;

  protected bool $isInterface;

  protected bool $isSubtype;

  protected string $keyPrefix;

  protected string $label;

  protected string $labelPlural;

  protected bool $layoutable;

  protected bool $mergeable;

  protected bool $mruEnabled;

  protected array $namedLayoutInfos;

  protected ?string $networkScopeFieldName;

  protected bool $queryable;

  protected array $recordTypeInfos;

  protected bool $replicateable;

  protected bool $retrieveable;

  protected bool $searchLayoutable;

  protected bool $searchable;

  protected string $sobjectDescribeOption;

  protected array $supportedScopes;

  protected bool $triggerable;

  protected bool $undeletable;

  protected bool $updateable;

  protected array $urls;

  protected bool $hasSubtypes;

  protected ?string $listviewable;

  protected ?string $lookupLayoutable;

  // phpcs:enable

  /**
   * See
   * https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/dome_sobject_describe.htm.
   *
   * @param \Drupal\salesforce\Rest\RestResponse $response
   *   The Response.
   */
  public function __construct(RestResponse $response) {
    parent::__construct($response->response);

    $this->name = $response->data['name'];
    $this->fields = [];
    // Index fields by machine name, so we don't have to search every time.
    foreach ($response->data['fields'] as $field) {
      $this->fields[$field['name']] = $field;
    }

    foreach ($response->data as $key => $value) {
      if ($key == 'fields') {
        continue;
      }
      $this->$key = $value;
    }
    $this->data = $response->data;
  }

  /**
   * Magic getter.
   */
  public function __get($key) {
    return $this->$key;
  }

  /**
   * Magic setter.
   */
  public function __set($key, $value) {
    $this->$key = $value;
  }

  /**
   * Getter for name.
   *
   * @return string
   *   The object name.
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Getter.
   *
   * @return array
   *   The fields.
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   * Return a field definition for the given field name.
   *
   * A single Salesforce field may contain the following keys:
   *    aggregatable
   *    autoNumber
   *    byteLength
   *    calculated
   *    calculatedFormula
   *    cascadeDelete
   *    caseSensitive
   *    controllerName
   *    createable
   *    custom
   *    defaultValue
   *    defaultValueFormula
   *    defaultedOnCreate
   *    dependentPicklist
   *    deprecatedAndHidden
   *    digits
   *    displayLocationInDecimal
   *    encrypted
   *    externalId
   *    extraTypeInfo
   *    filterable
   *    filteredLookupInfo
   *    groupable
   *    highScaleNumber
   *    htmlFormatted
   *    idLookup
   *    inlineHelpText
   *    label
   *    length
   *    mask
   *    maskType
   *    name
   *    nameField
   *    namePointing
   *    nillable
   *    permissionable
   *    picklistValues
   *    precision
   *    queryByDistance
   *    referenceTargetField
   *    referenceTo
   *    relationshipName
   *    relationshipOrder
   *    restrictedDelete
   *    restrictedPicklist
   *    scale
   *    soapType
   *    sortable
   *    type
   *    unique
   *    updateable
   *    writeRequiresMasterRead.
   *
   * For more information @param string $field_name
   *   A field name.
   *
   * @return array
   *   The field definition.
   *
   * @throws \Exception
   *   If field_name is not defined for this SObject type.
   * @see
   * https://developer.salesforce.com/docs/atlas.en-us.apexcode.meta/apexcode/apex_methods_system_fields_describe.htm.
   *
   */
  public function getField($field_name) {
    if (empty($this->fields[$field_name])) {
      throw new \Exception("No field $field_name");
    }
    return $this->fields[$field_name];
  }

  /**
   * Return a one-dimensional array of field names => field labels.
   *
   * @return array
   *   The field options.
   */
  public function getFieldOptions() {
    if (!isset($this->fieldOptions)) {
      $this->fieldOptions = array_column($this->fields, 'label', 'name');
      asort($this->fieldOptions);
    }
    return $this->fieldOptions;
  }

}
