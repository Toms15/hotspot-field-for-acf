<?php
/**
 * Render template of the test blocks (and markup of their render callback):
 * the block's Hotspot Field on the image of its Linked Image Field, through
 * hotspot_field_render(). PHPUnit tests
 * use the `hotspot_field_tests_block_markup` filter to render it through
 * another entry point instead.
 *
 * @package HotspotFieldForAcf
 *
 * @var array $block The block being rendered.
 */

defined( 'ABSPATH' ) || exit;

$hotspot_field_markup = apply_filters( 'hotspot_field_tests_block_markup', null, $block );

?>
<div class="hotspot-test-block">
	<?php echo $hotspot_field_markup ?? hotspot_field_render( 'hotspots' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup escaped by the renderer. ?>
</div>
