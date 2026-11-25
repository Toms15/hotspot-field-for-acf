<?php
/**
 * Bootstrap: checks the ACF requirement and registers the Hotspot Field type.
 *
 * @package HotspotFieldForAcf
 */

namespace HotspotFieldForAcf;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin into WordPress and ACF.
 */
final class Plugin {

	/**
	 * Hooks the requirement check once all plugins are loaded, so ACF has had
	 * the chance to load regardless of plugin order.
	 */
	public static function boot(): void {
		add_action( 'plugins_loaded', array( self::class, 'init' ) );
	}

	/**
	 * Registers the field type when ACF is compatible, otherwise only shows an
	 * admin notice.
	 */
	public static function init(): void {
		if ( ! self::has_compatible_acf() ) {
			add_action( 'admin_notices', array( self::class, 'render_requirement_notice' ) );
			return;
		}

		add_action( 'acf/include_field_types', array( self::class, 'register_field_type' ) );
	}

	/**
	 * Whether an ACF version at or above the minimum is active.
	 */
	private static function has_compatible_acf(): bool {
		if ( ! function_exists( 'acf_get_setting' ) || ! function_exists( 'acf_register_field_type' ) ) {
			return false;
		}

		$version = (string) acf_get_setting( 'version' );

		return '' !== $version && version_compare( $version, MINIMUM_ACF_VERSION, '>=' );
	}

	/**
	 * Registers the Hotspot Field type with ACF.
	 */
	public static function register_field_type(): void {
		require_once __DIR__ . '/class-hotspot-field.php';

		acf_register_field_type( Hotspot_Field::class );
	}

	/**
	 * Explains that ACF 6.0 or later is required.
	 */
	public static function render_requirement_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: minimum ACF version number. */
					__( 'Hotspot Field for ACF requires Advanced Custom Fields (or ACF Pro) %s or later. Please install and activate it.', 'hotspot-field-for-acf' ),
					MINIMUM_ACF_VERSION
				)
			)
		);
	}
}
