<?php
/**
 * PHPUnit bootstrap: loads the WordPress test suite with ACF Pro and this
 * plugin active. Runs inside wp-env's tests-cli container.
 *
 * @package HotspotFieldForAcf
 */

$hotspot_field_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $hotspot_field_tests_dir || ! file_exists( $hotspot_field_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "The WordPress test suite was not found. Run the tests with `npm run test:php`.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Read by the WordPress test suite.

require_once $hotspot_field_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require_once WP_PLUGIN_DIR . '/advanced-custom-fields-pro/acf.php';
		require_once dirname( __DIR__, 2 ) . '/hotspot-field-for-acf.php';
	}
);

require $hotspot_field_tests_dir . '/includes/bootstrap.php';
