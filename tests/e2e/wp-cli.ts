import { execFileSync } from 'node:child_process';

/**
 * Runs WP-CLI in wp-env and returns what the command printed.
 *
 * @param args WP-CLI arguments.
 */
export function wp( ...args: string[] ): string {
	return execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'wp', ...args ], {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'ignore' ],
	} ).trim();
}

/**
 * Runs PHP in WordPress and returns the JSON it printed between markers.
 *
 * @param php PHP code that assigns the result to `$result`.
 */
export function wpEval< T >( php: string ): T {
	const output = wp( 'eval', `${ php } echo '<<<', wp_json_encode( $result ), '>>>';` );
	const match = output.match( /<<<([\s\S]*)>>>/ );
	if ( ! match ) {
		throw new Error( `WP-CLI printed no result: ${ output }` );
	}
	return JSON.parse( match[ 1 ] ) as T;
}
