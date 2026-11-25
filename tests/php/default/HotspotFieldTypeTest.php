<?php
/**
 * Tests for registering the Hotspot Field type with ACF.
 *
 * @package HotspotFieldForAcf
 */

/**
 * The Hotspot Field type as a site builder sees it through ACF.
 */
class HotspotFieldTypeTest extends WP_UnitTestCase {

	/**
	 * A site builder can pick "Hotspot" as a field type.
	 */
	public function test_hotspot_field_type_is_available_in_acf(): void {
		$field_type = acf_get_field_type( 'hotspot' );

		$this->assertInstanceOf( acf_field::class, $field_type );
		$this->assertSame( 'Hotspot', $field_type->label );
	}

	/**
	 * The Linked Image Field name set in the field settings is saved.
	 */
	public function test_linked_image_field_setting_is_saved_with_the_field(): void {
		$field_group = acf_update_field_group(
			array(
				'key'   => 'group_hotspot_test',
				'title' => 'Hotspot test',
			)
		);

		acf_update_field(
			array(
				'key'                => 'field_hotspot_test',
				'label'              => 'Hotspots',
				'name'               => 'hotspots',
				'type'               => 'hotspot',
				'parent'             => $field_group['ID'],
				'linked_image_field' => 'photo',
			)
		);

		acf_get_store( 'fields' )->reset();
		$field = acf_get_field( 'field_hotspot_test' );

		$this->assertSame( 'hotspot', $field['type'] );
		$this->assertSame( 'photo', $field['linked_image_field'] );
	}
}
