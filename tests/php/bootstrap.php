<?php
/**
 * PHPUnit bootstrap: loads the WordPress test suite with this plugin active.
 * Runs inside wp-env's tests-cli container.
 *
 * By default ACF Pro is loaded too. Two environment variables simulate the
 * requirement failures instead:
 * - HOTSPOT_FIELD_TESTS_WITHOUT_ACF=1 does not load ACF at all;
 * - HOTSPOT_FIELD_TESTS_ACF_VERSION=x.y.z makes ACF report that version.
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
		if ( ! getenv( 'HOTSPOT_FIELD_TESTS_WITHOUT_ACF' ) ) {
			require_once WP_PLUGIN_DIR . '/advanced-custom-fields-pro/acf.php';
		}

		$acf_version = getenv( 'HOTSPOT_FIELD_TESTS_ACF_VERSION' );
		if ( $acf_version ) {
			add_filter( 'acf/settings/version', static fn () => $acf_version );
		}

		require_once dirname( __DIR__, 2 ) . '/hotspot-field-for-acf.php';
	}
);

require $hotspot_field_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/includes/class-hotspot-field-test-case.php';
