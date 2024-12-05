<?php

namespace Drupal\Tests\single_content_sync\Kernel\FieldProcessor;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;

/**
 * @coversDefaultClass \Drupal\single_content_sync\Plugin\SingleContentSyncFieldProcessor\SimpleField
 *
 * @todo add test cases for all field types described in SimpleFieldDeriver.
 */
class SimpleFieldProcessorTest extends FieldProcessorTestBase {

  // The address field data used for testing.
  const ADDRESS_FIELD_TEST_DATA = [
    'country_code' => 'US',
    'administrative_area' => 'CA',
    'locality' => 'Mountain View',
    'postal_code' => '94043',
    'address_line1' => '1098 Alta Ave',
    'organization' => 'Google Inc.',
    'given_name' => 'John',
    'family_name' => 'Smith',
  ];

  // Full address field data, including all possible values; that's what
  // get stored in the field.
  const FULL_ADDRESS_FIELD_TEST_DATA = [
    'country_code' => 'US',
    'administrative_area' => 'CA',
    'locality' => 'Mountain View',
    'postal_code' => '94043',
    'address_line1' => '1098 Alta Ave',
    'organization' => 'Google Inc.',
    'given_name' => 'John',
    'family_name' => 'Smith',
    'langcode' => NULL,
    'dependent_locality' => NULL,
    'sorting_code' => NULL,
    'address_line2' => NULL,
    // address_line3 added in address 2.0.0.
    'address_line3' => NULL,
    'additional_name' => NULL,
  ];

  const BLOCK_FIELD_TEST_DATA = [
    'plugin_id' => 'system_branding_block',
    'settings' => [
      'id' => 'system_branding_block',
      'label' => 'Site branding',
      'label_display' => FALSE,
      'provider' => 'system',
      'use_site_logo' => 0,
      'use_site_name' => 1,
      'use_site_slogan' => 1,
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public static function importFieldValueDataProvider(): array {
    return [
      'address' => [
        [
          'type' => 'address',
          'settings' => [
            'available_countries' => [],
          ],
        ],
        [0 => self::ADDRESS_FIELD_TEST_DATA],
        [0 => self::FULL_ADDRESS_FIELD_TEST_DATA],
        ['address'],
      ],
      'address_country' => [
        [
          'type' => 'address_country',
          'settings' => [
            'available_countries' => [],
          ],
        ],
        [0 => ['value' => 'UA']],
        NULL,
        ['address'],
      ],
      'boolean' => [
        ['type' => 'boolean'],
        [0 => ['value' => TRUE]],
      ],
      'block_field' => [
        ['type' => 'block_field'],
        [0 => self::BLOCK_FIELD_TEST_DATA],
        [0 => self::BLOCK_FIELD_TEST_DATA],
        ['block_field'],
      ],
      'color_field_type' => [
        [
          'type' => 'color_field_type',
          'settings' => [
            'format' => '#HEXHEX',
          ],
        ],
        [
          0 => [
            'color' => '#DDDDFF',
            'opacity' => '1',
          ],
        ],
        NULL,
        ['color_field'],
      ],
      'daterange' => [
        [
          'type' => 'daterange',
          'settings' => ['datetime_type' => DateRangeItem::DATETIME_TYPE_DATE],
          'module' => 'datetime_range',
        ],
        [
          0 => [
            'value' => '2019-01-01',
            'end_value' => '2020-01-31',
          ],
        ],
        NULL,
        ['datetime', 'datetime_range'],
      ],
      'datetime' => [
        [
          'type' => 'datetime',
          'settings' => [
            'datetime_type' => 'datetime',
          ],
        ],
        [
          0 => [
            'value' => '2024-01-11T14:38:58',
          ],
        ],
        NULL,
        ['datetime'],
      ],
      'decimal' => [
        [
          'type' => 'decimal',
          'settings' => [
            'precision' => 10,
            'scale' => 2,
          ],
        ],
        [0 => ['value' => '123.45']],
      ],
      'email' => [
        ['type' => 'email'],
        [0 => ['value' => 'someone@example.com']],
      ],
      'float' => [
        ['type' => 'float'],
        [0 => ['value' => '123.45']],
      ],
      'geofield' => [
        ['type' => 'geofield'],
        [
          0 => [
            'value' => 'POINT (0.5 0.5)',
            'geo_type' => 'Point',
            'lat' => 0.5,
            'lon' => 0.5,
            'left' => 0.5,
            'top' => 0.5,
            'right' => 0.5,
            'bottom' => 0.5,
            'geohash' => 's006g',
            'latlon' => '0.5,0.5',
          ],
        ],
        NULL,
        ['geofield'],
      ],
      'geolocation' => [
        ['type' => 'geolocation'],
        [
          0 => [
            // 0, 0 coordinates are used for testing since other values are
            // causing floating point precision issues.
            'lat' => '0',
            'lng' => '0',
            'lat_sin' => 0,
            'lat_cos' => 1.0,
            'lng_rad' => 0.0,
            'value' => '0, 0',
          ],
        ],
        NULL,
        ['geolocation'],
      ],
      'integer' => [
        ['type' => 'integer'],
        [0 => ['value' => '123']],
      ],
      'link' => [
        ['type' => 'link'],
        [0 => ['uri' => 'internal:/node/1', 'title' => 'hello', 'options' => []]],
        NULL,
        ['link'],
      ],
      'link_no_options' => [
        ['type' => 'link'],
        [0 => ['uri' => 'internal:/node/1', 'title' => 'hello']],
        [0 => ['uri' => 'internal:/node/1', 'title' => 'hello', 'options' => []]],
        ['link'],
      ],
      'list_float' => [
        [
          'type' => 'list_float',
          'settings' => [
            'allowed_values' => [
              '0.5' => 'half',
              '1.0' => 'full',
            ],
          ],
        ],
        [0 => ['value' => 0.5]],
        NULL,
        ['options'],
      ],
      'list_integer' => [
        [
          'type' => 'list_integer',
          'settings' => [
            'allowed_values' => [
              '1' => 'one',
              '2' => 'two',
            ],
          ],
        ],
        [0 => ['value' => 1]],
        NULL,
        ['options'],
      ],
      'list_string' => [
        [
          'type' => 'list_string',
          'settings' => [
            'allowed_values' => [
              'one' => 'one',
              'two' => 'two',
            ],
          ],
        ],
        [0 => ['value' => 'one']],
        NULL,
        ['options'],
      ],
      'paragraph_view_mode' => [
        [
          'type' => 'paragraph_view_mode',
          'settings' => [
            'max_length' => 255,
          ],
        ],
        [0 => ['value' => 'teaser']],
        NULL,
        ['paragraph_view_mode'],
      ],
      'string' => [
        [
          'type' => 'string',
          'settings' => [
            'max_length' => 255,
          ],
        ],
        [0 => ['value' => 'hello']],
      ],
      // Multivalued field test; there is just one for string since other field
      // types share the same code/logic and there's no sense in testing them
      // all.
      'multivalued string' => [
        [
          'type' => 'string',
          'settings' => [
            'max_length' => 255,
          ],
          'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
        ],
        [0 => ['value' => 'hello once'], 1 => ['value' => 'hello again']],
      ],
      'string_long' => [
        ['type' => 'string_long'],
        [0 => ['value' => 'hello']],
      ],
      'telephone' => [
        ['type' => 'telephone'],
        [0 => ['value' => '1234567890']],
        NULL,
        ['telephone'],
      ],
      'text' => [
        ['type' => 'text', 'max_length' => 255],
        [0 => ['value' => 'hello', 'format' => 'plain_text']],
        NULL,
        ['text'],
      ],
      'timestamp' => [
        ['type' => 'timestamp'],
        [0 => ['value' => '1234567890']],
      ],
      'viewsreference' => [
        ['type' => 'viewsreference'],
        [
          0 => [
            'target_id' => 'content_recent',
            'display_id' => 'block_1',
            'data' => 'a:5:{s:5:"pager";N;s:6:"offset";N;s:5:"title";N;s:8:"argument";N;s:5:"limit";N;}',
          ],
        ],
        NULL,
        ['viewsreference', 'views'],
      ],
      'video_embed_field' => [
        ['type' => 'video_embed_field'],
        [0 => ['value' => 'video-url']],
        NULL,
        ['video_embed_field'],
      ],
      'weight' => [
        ['type' => 'weight'],
        [0 => ['value' => '10']],
        NULL,
        ['weight'],
      ],
      'yearonly' => [
        ['type' => 'yearonly'],
        [0 => ['value' => '2019']],
        NULL,
        ['yearonly'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function exportFieldValueDataProvider(): array {
    return [
      'address' => [
        [
          'type' => 'address',
          'settings' => [
            'available_countries' => [],
          ],
        ],
        self::ADDRESS_FIELD_TEST_DATA,
        [0 => self::FULL_ADDRESS_FIELD_TEST_DATA],
        ['address'],
      ],
      'address_country' => [
        [
          'type' => 'address_country',
          'settings' => [
            'available_countries' => [],
          ],
        ],
        'UA',
        [0 => ['value' => 'UA']],
        ['address'],
      ],
      'boolean' => [
        ['type' => 'boolean'],
        TRUE,
        [0 => ['value' => TRUE]],
      ],
      'block_field' => [
        ['type' => 'block_field'],
        self::BLOCK_FIELD_TEST_DATA,
        [0 => self::BLOCK_FIELD_TEST_DATA],
        ['block_field'],
      ],
      'color_field_type' => [
        [
          'type' => 'color_field_type',
          'settings' => [
            'format' => '#HEXHEX',
          ],
        ],
        [
          'color' => '#DDDDFF',
          'opacity' => '1',
        ],
        [
          0 => [
            'color' => '#DDDDFF',
            'opacity' => '1',
          ],
        ],
        ['color_field'],
      ],
      'daterange' => [
        [
          'type' => 'daterange',
          'settings' => [
            'datetime_type' => 'datetime',
          ],
        ],
        [
          'value' => '2019-01-01',
          'end_value' => '2020-01-31',
        ],
        [
          0 => [
            'value' => '2019-01-01',
            'end_value' => '2020-01-31',
          ],
        ],
        ['datetime', 'datetime_range'],
      ],
      'datetime' => [
        [
          'type' => 'datetime',
          'settings' => [
            'datetime_type' => 'datetime',
          ],
        ],
        [
          'value' => '2024-01-11T14:38:58',
        ],
        [
          0 => [
            'value' => '2024-01-11T14:38:58',
          ],
        ],
        ['datetime'],
      ],
      'decimal' => [
        [
          'type' => 'decimal',
          'settings' => [
            'precision' => 10,
            'scale' => 2,
          ],
        ],
        123.45,
        [0 => ['value' => '123.45']],
      ],
      'email' => [
        ['type' => 'email'],
        'someone@example.com',
        [0 => ['value' => 'someone@example.com']],
      ],
      'float' => [
        ['type' => 'float'],
        123.45,
        [0 => ['value' => '123.45']],
      ],
      'geofield' => [
        ['type' => 'geofield'],
        [
          'value' => 'POINT (0.5 0.5)',
        ],
        [
          0 => [
            'value' => 'POINT (0.5 0.5)',
            'geo_type' => 'Point',
            'lat' => 0.5,
            'lon' => 0.5,
            'left' => 0.5,
            'top' => 0.5,
            'right' => 0.5,
            'bottom' => 0.5,
            'geohash' => 's006g',
            'latlon' => '0.5,0.5',
          ],
        ],
        ['geofield'],
      ],
      'geolocation' => [
        ['type' => 'geolocation'],
        [
          'lat' => '0',
          'lng' => '0',
        ],
        [
          0 => [
            'lat' => '0',
            'lng' => '0',
            'lat_sin' => 0,
            'lat_cos' => 1.0,
            'lng_rad' => 0.0,
            'value' => '0, 0',
          ],
        ],
        ['geolocation'],
      ],
      'integer' => [
        ['type' => 'integer'],
        123,
        [0 => ['value' => '123']],
      ],
      'link' => [
        ['type' => 'link'],
        [
          'uri' => 'internal:/node/1',
          'title' => 'hello',
          'options' => [],
        ],
        [
          0 => [
            'uri' => 'internal:/node/1',
            'title' => 'hello',
            'options' => [],
          ],
        ],
        ['link'],
      ],
      'list_float' => [
        [
          'type' => 'list_float',
          'settings' => [
            'allowed_values' => [
              '0.5' => 'half',
              '1.0' => 'full',
            ],
          ],
        ],
        0.5,
        [0 => ['value' => 0.5]],
        ['options'],
      ],
      'list_integer' => [
        [
          'type' => 'list_integer',
          'settings' => [
            'allowed_values' => [
              '1' => 'one',
              '2' => 'two',
            ],
          ],
        ],
        1,
        [0 => ['value' => 1]],
        ['options'],
      ],
      'list_string' => [
        [
          'type' => 'list_string',
          'settings' => [
            'allowed_values' => [
              'one' => 'one',
              'two' => 'two',
            ],
          ],
        ],
        'one',
        [0 => ['value' => 'one']],
        ['options'],
      ],
      'paragraph_view_mode' => [
        [
          'type' => 'paragraph_view_mode',
          'settings' => [
            'max_length' => 255,
          ],
        ],
        'teaser',
        [0 => ['value' => 'teaser']],
        ['paragraph_view_mode'],
      ],
      'string' => [
        [
          'type' => 'string',
          'settings' => [
            'max_length' => 255,
          ],
        ],
        'hello',
        [0 => ['value' => 'hello']],
      ],
      'multivalued string' => [
        [
          'type' => 'string',
          'settings' => [
            'max_length' => 255,
          ],
          'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
        ],
        [0 => ['value' => 'hello once'], 1 => ['value' => 'hello again']],
        [0 => ['value' => 'hello once'], 1 => ['value' => 'hello again']],
      ],
      'string_long' => [
        ['type' => 'string_long'],
        'hello',
        [0 => ['value' => 'hello']],
      ],
      'telephone' => [
        [
          'type' => 'telephone',
        ],
        '1234567890',
        [0 => ['value' => '1234567890']],
        ['telephone'],
      ],
      'text' => [
        ['type' => 'text', 'max_length' => 255],
        ['value' => 'hello', 'format' => 'plain_text'],
        [0 => ['value' => 'hello', 'format' => 'plain_text']],
        ['text'],
      ],
      'timestamp' => [
        ['type' => 'timestamp'],
        1234567890,
        [0 => ['value' => '1234567890']],
      ],
      'viewsreference' => [
        ['type' => 'viewsreference'],
        [
          'target_id' => 'content_recent',
          'display_id' => 'block',
          'data' => 'a:5:{s:5:"pager";N;s:6:"offset";N;s:5:"title";N;s:8:"argument";N;s:5:"limit";N;}',
        ],
        [
          0 => [
            'target_id' => 'content_recent',
            'display_id' => 'block',
            'data' => 'a:5:{s:5:"pager";N;s:6:"offset";N;s:5:"title";N;s:8:"argument";N;s:5:"limit";N;}',
          ],
        ],
        ['viewsreference', 'views'],
      ],
      'video_embed_field' => [
        ['type' => 'video_embed_field'],
        'video-url',
        [0 => ['value' => 'video-url']],
        ['video_embed_field'],
      ],
      'weight' => [
        ['type' => 'weight'],
        10,
        [0 => ['value' => '10']],
        ['weight'],
      ],
      'yearonly' => [
        ['type' => 'yearonly'],
        2019,
        [0 => ['value' => '2019']],
        ['yearonly'],
      ],
    ];
  }

}
