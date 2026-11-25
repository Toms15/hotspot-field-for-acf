<?php
/**
 * Tests for the plugin when ACF is not active.
 *
 * @package HotspotFieldForAcf
 */

/**
 * Without ACF the plugin only explains what it needs.
 */
class WithoutAcfTest extends WP_UnitTestCase {

	/**
	 * Administrators see a notice asking for ACF 6.0 or later.
	 */
	public function test_administrators_see_a_requirement_notice(): void {
		$this->assertFalse( function_exists( 'acf' ), 'This suite must run without ACF.' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertStringContainsString(
			'Hotspot Field for ACF requires Advanced Custom Fields (or ACF Pro) 6.0 or later.',
			get_echo( 'do_action', array( 'admin_notices' ) )
		);
	}

	/**
	 * Templates and content that use the render entry points keep working and
	 * simply output nothing.
	 */
	public function test_render_entry_points_output_nothing(): void {
		$post_id = self::factory()->post->create();

		$this->assertSame( '', hotspot_field_render( 'hotspots', $post_id ) );
		$this->assertSame( '', do_shortcode( sprintf( '[hotspot_field name="hotspots" post_id="%d"]', $post_id ) ) );
	}

	/**
	 * Users who cannot manage plugins are not shown the notice.
	 */
	public function test_other_users_do_not_see_the_notice(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertStringNotContainsString( 'Hotspot Field for ACF', get_echo( 'do_action', array( 'admin_notices' ) ) );
	}
}
