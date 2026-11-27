<?php
/**
 * Plugin Name: Hotspot Field test blocks
 * Description: Test environment only: registers ACF blocks holding a Hotspot Field, for the PHPUnit and Playwright suites of Hotspot Field for ACF.
 * Version:     1.0.0
 * License:     GPLv2 or later
 *
 * @package HotspotFieldForAcf
 */

defined( 'ABSPATH' ) || exit;

/**
 * Test blocks, by name: one per ACF block version, as sites built with older
 * ACF releases use version 2 and newer ones version 3. Each holds an image
 * field `photo` and a Hotspot Field `hotspots` linked to it, and renders the
 * Hotspot Field with hotspot_field_render().
 */
const HOTSPOT_FIELD_TEST_BLOCKS = array(
	'acf/hotspot-test-v2' => 'hotspot-v2',
	'acf/hotspot-test-v3' => 'hotspot-v3',
);

add_action(
	'init',
	static function (): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		foreach ( HOTSPOT_FIELD_TEST_BLOCKS as $hotspot_field_folder ) {
			register_block_type( __DIR__ . '/blocks/' . $hotspot_field_folder );
		}
	}
);

add_action(
	'acf/include_fields',
	static function (): void {
		foreach ( array_keys( HOTSPOT_FIELD_TEST_BLOCKS ) as $hotspot_field_block ) {
			$hotspot_field_slug = str_replace( array( 'acf/', '-' ), array( '', '_' ), $hotspot_field_block );

			acf_add_local_field_group(
				array(
					'key'      => "group_{$hotspot_field_slug}",
					'title'    => "Hotspot test block ({$hotspot_field_block})",
					'fields'   => array(
						array(
							'key'           => "field_{$hotspot_field_slug}_photo",
							'label'         => 'Block photo',
							'name'          => 'photo',
							'type'          => 'image',
							'return_format' => 'id',
						),
						array(
							'key'                => "field_{$hotspot_field_slug}_hotspots",
							'label'              => 'Block hotspots',
							'name'               => 'hotspots',
							'type'               => 'hotspot',
							'linked_image_field' => 'photo',
						),
					),
					'location' => array(
						array(
							array(
								'param'    => 'block',
								'operator' => '==',
								'value'    => $hotspot_field_block,
							),
						),
					),
				)
			);
		}
	}
);
