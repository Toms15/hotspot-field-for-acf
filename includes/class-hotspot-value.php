<?php
/**
 * Hotspot value: the canonical list of Hotspots.
 *
 * @package HotspotFieldForAcf
 */

namespace HotspotFieldForAcf;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes raw input into the canonical, ordered list of Hotspots. The only
 * place that decides what a valid Hotspot is; used when saving, loading and
 * rendering.
 */
final class Hotspot_Value {

	/**
	 * Turns raw input into a list of Hotspots.
	 *
	 * Accepts a list or its JSON encoding (as the editor submits it). Each
	 * Hotspot becomes `{ x: float, y: float, label: string, description: string }`
	 * with positions clamped to 0–100 and rounded to two decimals, and HTML
	 * stripped from the text (Description keeps its line breaks). Entries that
	 * are not Hotspots are dropped; order is kept.
	 *
	 * @param mixed $raw Raw value.
	 * @return list<array{x: float, y: float, label: string, description: string}>
	 */
	public static function normalize( $raw ): array {
		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$hotspots = array();

		foreach ( $raw as $entry ) {
			$hotspot = self::normalize_hotspot( $entry );

			if ( null !== $hotspot ) {
				$hotspots[] = $hotspot;
			}
		}

		return $hotspots;
	}

	/**
	 * Normalizes one entry, or returns null when it is not a Hotspot.
	 *
	 * @param mixed $entry Raw entry.
	 * @return array{x: float, y: float, label: string, description: string}|null
	 */
	private static function normalize_hotspot( $entry ): ?array {
		if ( ! is_array( $entry ) || ! isset( $entry['x'], $entry['y'] ) ) {
			return null;
		}

		$label       = $entry['label'] ?? '';
		$description = $entry['description'] ?? '';

		if ( ! self::is_position( $entry['x'] ) || ! self::is_position( $entry['y'] ) || ! is_scalar( $label ) || ! is_scalar( $description ) ) {
			return null;
		}

		return array(
			'x'           => self::position( $entry['x'] ),
			'y'           => self::position( $entry['y'] ),
			'label'       => self::text( (string) $label, false ),
			'description' => self::text( (string) $description, true ),
		);
	}

	/**
	 * Whether a value can be read as a position.
	 *
	 * @param mixed $value Raw position.
	 */
	private static function is_position( $value ): bool {
		return ( is_int( $value ) || is_float( $value ) || is_string( $value ) ) && is_numeric( $value ) && is_finite( (float) $value );
	}

	/**
	 * A position in percent: clamped to 0–100, rounded to two decimals.
	 *
	 * @param int|float|string $value Numeric position.
	 */
	private static function position( $value ): float {
		return round( min( 100.0, max( 0.0, (float) $value ) ), 2 );
	}

	/**
	 * Plain text without HTML, trimmed.
	 *
	 * @param string $text             Raw text.
	 * @param bool   $keep_line_breaks Whether line breaks survive (normalized to "\n").
	 */
	private static function text( string $text, bool $keep_line_breaks ): string {
		$text = wp_check_invalid_utf8( $text );

		if ( ! $keep_line_breaks ) {
			return wp_strip_all_tags( $text, true );
		}

		return wp_strip_all_tags( str_replace( array( "\r\n", "\r" ), "\n", $text ) );
	}
}
