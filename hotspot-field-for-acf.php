<?php
/**
 * Plugin Name:       Hotspot Field for ACF
 * Description:       An ACF field type that lets editors mark points of interest on an image and lets visitors open each point to read about it.
 * Version:           1.0.0
 * Requires at least: 6.8
 * Requires PHP:      8.3
 * Author:            AUTHOR_NAME_PLACEHOLDER
 * Author URI:        https://author-uri-placeholder.invalid/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hotspot-field-for-acf
 *
 * @package HotspotFieldForAcf
 */

namespace HotspotFieldForAcf;

defined( 'ABSPATH' ) || exit;

const VERSION             = '1.0.0';
const MINIMUM_ACF_VERSION = '6.0';
const PLUGIN_FILE         = __FILE__;

require_once __DIR__ . '/includes/class-hotspot-value.php';
require_once __DIR__ . '/includes/class-field-resolver.php';
require_once __DIR__ . '/includes/class-renderer.php';
require_once __DIR__ . '/includes/class-shortcode.php';
require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/functions.php';

Plugin::boot();
