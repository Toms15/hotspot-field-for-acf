import { execFileSync } from 'node:child_process';
import { expect, type Locator, type Page } from '@playwright/test';

export type Hotspot = { x: number; y: number; label: string; description: string };

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

/**
 * Opens a post in the block editor, without the welcome guide.
 *
 * @param page   Playwright page.
 * @param postId Post ID.
 */
export async function openEditor( page: Page, postId: number ): Promise< void > {
	await page.goto( `/wp-admin/post.php?post=${ postId }&action=edit` );
	await page.waitForFunction( () => ( window as any ).wp?.data?.select( 'core/editor' )?.getCurrentPostId() );
	await page.evaluate( () => ( window as any ).wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false ) );

	// ACF's fields live in the meta box pane under the content; open it.
	const metaBoxes = page.getByRole( 'region', { name: 'Meta Boxes' } ).getByRole( 'button', { name: 'Meta Boxes' } );
	if ( ( await metaBoxes.count() ) && ( await metaBoxes.getAttribute( 'aria-expanded' ) ) === 'false' ) {
		// The resize handle covers the middle of the toggle: use the keyboard.
		await metaBoxes.press( 'Enter' );
		await expect( metaBoxes ).toHaveAttribute( 'aria-expanded', 'true' );
	}
	await page.locator( '.acf-field-hotspot' ).scrollIntoViewIfNeeded();
}

/**
 * The Hotspot Field's image, once it has loaded.
 *
 * @param field The Hotspot Field.
 */
export async function loadedImage( field: Locator ): Promise< Locator > {
	const image = field.getByRole( 'img', { name: 'Image to place Hotspots on' } );
	await expect( image ).toBeVisible();
	await expect.poll( () => image.evaluate( ( img: HTMLImageElement ) => img.complete && img.naturalWidth > 0 ) ).toBe( true );
	return image;
}

/**
 * Clicks an element at a fraction of its width and height.
 *
 * @param locator Element.
 * @param x       Fraction of the width.
 * @param y       Fraction of the height.
 */
export async function clickAt( locator: Locator, x: number, y: number ): Promise< void > {
	const box = await locator.boundingBox();
	expect( box ).not.toBeNull();
	await locator.click( { position: { x: box!.width * x, y: box!.height * y } } );
}

/**
 * Saves the post in the block editor and waits for ACF's meta boxes to save.
 *
 * @param page Playwright page.
 */
export async function save( page: Page ): Promise< void > {
	const metaBoxesSaved = page.waitForResponse(
		( response ) => response.url().includes( 'meta-box-loader=1' ) && response.request().method() === 'POST'
	);
	await page.getByRole( 'region', { name: 'Editor top bar' } ).getByRole( 'button', { name: /^(Save draft|Save|Update)$/ } ).click();
	const response = await metaBoxesSaved;
	expect( response.status(), `meta box save answered ${ response.status() } ${ response.url() }` ).toBeLessThan( 400 );
}
