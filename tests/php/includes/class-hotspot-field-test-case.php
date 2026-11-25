<?php
/**
 * Shared setup for tests that use a Hotspot Field in a field group.
 *
 * @package HotspotFieldForAcf
 */

/**
 * Registers a field group with an image field named `photo` and a Hotspot
 * Field named `hotspots` whose Linked Image Field is `photo`.
 */
abstract class Hotspot_Field_Test_Case extends WP_UnitTestCase {

	/**
	 * Registers the field group used by the tests.
	 */
	public function set_up(): void {
		parent::set_up();

		acf_add_local_field_group(
			array(
				'key'      => 'group_hotspot_tests',
				'title'    => 'Hotspot tests',
				'fields'   => array(
					array(
						'key'           => 'field_hotspot_tests_photo',
						'label'         => 'Photo',
						'name'          => 'photo',
						'type'          => 'image',
						'return_format' => 'id',
					),
					array(
						'key'                => 'field_hotspot_tests_hotspots',
						'label'              => 'Hotspots',
						'name'               => 'hotspots',
						'type'               => 'hotspot',
						'linked_image_field' => 'photo',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
			)
		);
	}

	/**
	 * Saves Hotspots on a post through ACF, as the editor's save does.
	 *
	 * @param int   $post_id  Post ID.
	 * @param mixed $hotspots Value to save.
	 */
	protected function save_hotspots( int $post_id, $hotspots ): void {
		update_field( 'field_hotspot_tests_hotspots', $hotspots, $post_id );
	}

	/**
	 * Uploads the test image into the media library.
	 *
	 * @param string $alt Alternative text stored in the media library.
	 * @return int Attachment ID.
	 */
	protected function create_image( string $alt = '' ): int {
		$attachment_id = self::factory()->attachment->create_upload_object( dirname( __DIR__, 2 ) . '/fixtures/hotspot-photo.jpg' );

		if ( '' !== $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}

		return $attachment_id;
	}
}
