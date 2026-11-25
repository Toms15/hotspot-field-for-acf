#!/usr/bin/env node
/**
 * Downloads ACF Pro into `.wp-env-plugins/advanced-custom-fields-pro`, where
 * `.wp-env.json` expects it.
 *
 * The licence key is read from the ACF_PRO_LICENSE environment variable or,
 * failing that, from the git-ignored `.env` file at the repository root. The
 * key is never printed: errors only report the HTTP status.
 */
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const pluginsDir = join( root, '.wp-env-plugins' );
const target = join( pluginsDir, 'advanced-custom-fields-pro' );
const force = process.argv.includes( '--force' );

if ( existsSync( join( target, 'acf.php' ) ) && ! force ) {
	console.log( 'ACF Pro is already installed (use --force to reinstall).' );
	process.exit( 0 );
}

function readLicence() {
	if ( process.env.ACF_PRO_LICENSE ) {
		return process.env.ACF_PRO_LICENSE.trim();
	}
	const envFile = join( root, '.env' );
	if ( ! existsSync( envFile ) ) {
		return '';
	}
	for ( const line of readFileSync( envFile, 'utf8' ).split( /\r?\n/ ) ) {
		const match = line.match( /^\s*ACF_PRO_LICENSE\s*=\s*(.*)\s*$/ );
		if ( match ) {
			return match[ 1 ].replace( /^(['"])(.*)\1$/, '$2' ).trim();
		}
	}
	return '';
}

const licence = readLicence();
if ( ! licence ) {
	console.error(
		'ACF_PRO_LICENSE is not set. Add it to the git-ignored .env file (ACF_PRO_LICENSE=...) or export it.'
	);
	process.exit( 1 );
}

const url = new URL( 'https://connect.advancedcustomfields.com/v2/plugins/download' );
url.searchParams.set( 'p', 'pro' );
url.searchParams.set( 'k', licence );

const response = await fetch( url );
if ( ! response.ok ) {
	console.error( `Downloading ACF Pro failed with HTTP ${ response.status }. Check the licence key.` );
	process.exit( 1 );
}

const zip = Buffer.from( await response.arrayBuffer() );
if ( zip.subarray( 0, 2 ).toString() !== 'PK' ) {
	console.error( 'Downloading ACF Pro did not return a zip file. Check the licence key.' );
	process.exit( 1 );
}

mkdirSync( pluginsDir, { recursive: true } );
rmSync( target, { recursive: true, force: true } );
const zipPath = join( pluginsDir, 'advanced-custom-fields-pro.zip' );
writeFileSync( zipPath, zip );
execFileSync( 'unzip', [ '-q', '-o', zipPath, '-d', pluginsDir ] );
rmSync( zipPath );

if ( ! existsSync( join( target, 'acf.php' ) ) ) {
	console.error( 'The ACF Pro archive did not contain advanced-custom-fields-pro/acf.php.' );
	process.exit( 1 );
}

const version = readFileSync( join( target, 'acf.php' ), 'utf8' ).match( /Version:\s*(\S+)/ );
console.log( `ACF Pro ${ version ? version[ 1 ] : '' } installed.` );
