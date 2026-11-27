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
 * Test blocks, by name, with their block.json folder (null for a block
 * registered with acf_register_block_type()): one per ACF block version, as
 * sites built with older ACF releases use version 2 and newer ones version 3;
 * one that stores its values in the post's meta (`usePostMeta`) instead of
 * the block; and two that render through a callback instead of a template,
 * one per way of registering it. Each holds an image field `photo` and a
 * Hotspot Field `hotspots` linked to it, and renders the Hotspot Field with
 * hotspot_field_render().
 */
const HOTSPOT_FIELD_TEST_BLOCKS = array(
	'acf/hotspot-test-v2'           => 'hotspot-v2',
	'acf/hotspot-test-v3'           => 'hotspot-v3',
	'acf/hotspot-test-meta'         => 'hotspot-meta',
	'acf/hotspot-test-callback'     => 'hotspot-callback',
	'acf/hotspot-test-php-callback' => null,
);

/**
 * Render callback of the test blocks that render through a callback: the
 * same markup as the render template, which ACF does not render then.
 *
 * @param array $block The block being rendered.
 */
function hotspot_field_test_block_render( array $block ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Read by the template.
	require __DIR__ . '/blocks/render.php';
}

add_action(
	'init',
	static function (): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		foreach ( array_filter( HOTSPOT_FIELD_TEST_BLOCKS ) as $hotspot_field_folder ) {
			register_block_type( __DIR__ . '/blocks/' . $hotspot_field_folder );
		}
	}
);

add_action(
	'acf/init',
	static function (): void {
		if ( ! function_exists( 'acf_register_block_type' ) ) {
			return;
		}

		acf_register_block_type(
			array(
				'name'              => 'hotspot-test-php-callback',
				'title'             => 'Hotspot test block (ACF block registered in PHP, rendering through a callback)',
				'category'          => 'media',
				'icon'              => 'location',
				'mode'              => 'preview',
				'acf_block_version' => 3,
				'api_version'       => 3,
				'render_callback'   => 'hotspot_field_test_block_render',
			)
		);
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
