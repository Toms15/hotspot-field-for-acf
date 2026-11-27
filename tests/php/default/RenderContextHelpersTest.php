<?php
/**
 * Tests for the render-context helpers of the shared test case.
 *
 * @package HotspotFieldForAcf
 */

/**
 * The helpers build each render context from distinct objects, so tests that
 * use them prove something about the context, and undo it afterwards.
 */
class RenderContextHelpersTest extends Hotspot_Field_Test_Case {

	/**
	 * Registers a repeater named `items` to loop over.
	 */
	public function set_up(): void {
		parent::set_up();

		acf_add_local_field_group(
			array(
				'key'      => 'group_render_context_tests',
				'title'    => 'Render context tests',
				'fields'   => array(
					array(
						'key'        => 'field_render_context_items',
						'label'      => 'Items',
						'name'       => 'items',
						'type'       => 'repeater',
						'sub_fields' => array(
							array(
								'key'   => 'field_render_context_item_text',
								'label' => 'Text',
								'name'  => 'text',
								'type'  => 'text',
							),
						),
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
	 * The preview's global post is the previewed post while ACF resolves its
	 * ID to another object, the revision; the editor and the preview query
	 * are gone after the cleanup.
	 */
	public function test_preview_builds_distinct_objects_and_cleans_up(): void {
		$visitor_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $visitor_id );
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		update_field( 'field_render_context_items', array( array( 'field_render_context_item_text' => 'Saved' ) ), $post_id );

		$revision_id = $this->go_to_preview(
			$post_id,
			static function ( int $revision_id ): void {
				update_metadata( 'post', $revision_id, 'items_0_text', 'Previewed' );
			}
		);

		$this->assertNotSame( $post_id, $revision_id );
		$this->assertSame( $post_id, (int) wp_is_post_revision( $revision_id ) );
		$this->assertSame( $post_id, get_the_ID() );
		$this->assertSame( $revision_id, acf_get_valid_post_id( $post_id ) );
		$this->assertTrue( is_preview() );
		$this->assertArrayHasKey( 'preview', $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads the test request.
		$this->assertTrue( current_user_can( 'edit_post', $post_id ) );
		$this->assertNotSame( $visitor_id, get_current_user_id() );
		$this->assertSame( 'Previewed', get_field( 'items', $post_id )[0]['text'], 'ACF reads the revision.' );

		$this->end_render_contexts();

		$this->assertSame( $visitor_id, get_current_user_id() );
		$this->assertArrayNotHasKey( 'preview', $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads the test request.
		$this->assertArrayNotHasKey( 'preview_id', $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads the test request.
	}

	/**
	 * Two previews in one test still restore the user from before the first.
	 */
	public function test_second_preview_restores_the_original_user(): void {
		$visitor_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $visitor_id );

		$this->go_to_preview( self::factory()->post->create( array( 'post_status' => 'draft' ) ) );
		$this->go_to_preview( self::factory()->post->create( array( 'post_status' => 'draft' ) ) );
		$this->end_render_contexts();

		$this->assertSame( $visitor_id, get_current_user_id() );
	}

	/**
	 * A foreign loop left open reads another post than the current one; the
	 * cleanup closes it.
	 */
	public function test_open_foreign_rows_loop_on_another_post_builds_distinct_objects_and_cleans_up(): void {
		$current_id = self::factory()->post->create();
		$other_id   = self::factory()->post->create();
		update_field( 'field_render_context_items', array( array( 'field_render_context_item_text' => 'Other' ) ), $other_id );
		$this->go_to( get_permalink( $current_id ) );
		the_post();

		$this->open_foreign_rows_loop( 'items', $other_id );

		$loop = acf_get_loop( 'active' );
		$this->assertIsArray( $loop );
		$this->assertSame( $other_id, (int) $loop['post_id'] );
		$this->assertNotSame( $current_id, (int) $loop['post_id'] );
		$this->assertSame( $current_id, get_the_ID() );
		$this->assertSame( 'Other', get_sub_field( 'text' ) );

		$this->end_render_contexts();

		$this->assertFalse( acf_get_loop( 'active' ) );
	}

	/**
	 * A foreign loop over `option` renders each of its rows while the current
	 * post stays another object.
	 */
	public function test_render_foreign_rows_on_option_builds_distinct_objects(): void {
		$current_id = self::factory()->post->create();
		update_field(
			'field_render_context_items',
			array(
				array( 'field_render_context_item_text' => 'First' ),
				array( 'field_render_context_item_text' => 'Second' ),
			),
			'option'
		);
		$this->go_to( get_permalink( $current_id ) );
		the_post();

		$seen = $this->render_foreign_rows(
			'items',
			'option',
			static function () use ( $current_id ): string {
				$loop = acf_get_loop( 'active' );
				return sprintf( '%s:%s:%d', $loop['post_id'], get_sub_field( 'text' ), get_the_ID() === $current_id ? 1 : 0 );
			}
		);

		$this->assertSame( array( 'options:First:1', 'options:Second:1' ), $seen );
		$this->assertFalse( acf_get_loop( 'active' ), 'A loop read to its end closes itself.' );
	}

	/**
	 * The foreign loop helpers refuse a loop on the current post, which would
	 * prove nothing about the context.
	 */
	public function test_foreign_loop_on_the_current_post_is_refused(): void {
		$current_id = self::factory()->post->create();
		$this->go_to( get_permalink( $current_id ) );
		the_post();

		$this->expectException( \PHPUnit\Framework\ExpectationFailedException::class );
		$this->open_foreign_rows_loop( 'items', $current_id );
	}
}
