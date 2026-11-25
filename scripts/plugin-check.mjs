#!/usr/bin/env node
/**
 * Builds the distributable plugin and runs WordPress Plugin Check on it inside
 * wp-env. `wp plugin check` exits 0 even when it reports problems, so this
 * script fails whenever any error or warning is reported.
 */
import { execFileSync, spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );

execFileSync( 'bash', [ join( root, 'scripts/build.sh' ) ], { stdio: 'inherit' } );

const result = spawnSync(
	'npx',
	[
		'wp-env',
		'run',
		'cli',
		'wp',
		'plugin',
		'check',
		'/var/www/html/wp-content/plugins/hotspot-field-for-acf/build/hotspot-field-for-acf',
		'--slug=hotspot-field-for-acf',
		'--format=table',
	],
	{ cwd: root, encoding: 'utf8' }
);

process.stdout.write( result.stdout );
process.stderr.write( result.stderr );

if ( result.status !== 0 ) {
	process.exit( result.status ?? 1 );
}

if ( /^FILE: /m.test( result.stdout ) || ! /No errors found/.test( result.stdout ) ) {
	console.error( '\nPlugin Check reported problems.' );
	process.exit( 1 );
}
