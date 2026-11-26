import { execFileSync } from 'node:child_process';
import { expect, test, type Locator, type Page } from '@playwright/test';

/**
 * Runs WP-CLI in wp-env and returns what the command printed.
 *
 * @param args WP-CLI arguments.
 */
function wp( ...args: string[] ): string {
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
function wpEval< T >( php: string ): T {
	const output = wp( 'eval', `${ php } echo '<<<', wp_json_encode( $result ), '>>>';` );
	const match = output.match( /<<<([\s\S]*)>>>/ );
	if ( ! match ) {
		throw new Error( `WP-CLI printed no result: ${ output }` );
	}
	return JSON.parse( match[ 1 ] ) as T;
}

type Hotspot = { x: number; y: number; label: string; description: string };

const suffix = Date.now();
const rowError = 'This Hotspot needs a Label.';

let imageId: number;
const posts: number[] = [];
const fieldGroups: string[] = [];

/**
 * Creates a post with an image and a field group, shown only on that post,
 * holding the image field `photo` and the Hotspot Field `hotspots`.
 *
 * @param name     Name used in keys and the title.
 * @param required Whether the Hotspot Field is required.
 * @return Post ID.
 */
function createPost( name: string, required: boolean ): number {
	const postId = Number( wp( 'post', 'create', '--post_type=post', '--post_status=publish', '--post_author=1', `--post_title=Hotspot validation ${ name } ${ suffix }`, '--porcelain' ) );
	const key = `${ name }_${ suffix }`;
	wpEval( `
		$result = acf_import_field_group( array(
			'key'      => 'group_e2e_validation_${ key }',
			'title'    => 'Hotspot validation ${ key }',
			'fields'   => array(
				array( 'key' => 'field_e2e_validation_photo_${ key }', 'label' => 'Photo', 'name' => 'photo', 'type' => 'image', 'return_format' => 'id' ),
				array( 'key' => 'field_e2e_validation_hotspots_${ key }', 'label' => 'Hotspots', 'name' => 'hotspots', 'type' => 'hotspot', 'linked_image_field' => 'photo', 'required' => ${ required ? 1 : 0 } ),
			),
			'location' => array( array( array( 'param' => 'post', 'operator' => '==', 'value' => '${ postId }' ) ) ),
		) )['ID'];
		update_field( 'field_e2e_validation_photo_${ key }', ${ imageId }, ${ postId } );
	` );
	posts.push( postId );
	fieldGroups.push( `group_e2e_validation_${ key }` );
	return postId;
}

/**
 * The Hotspots saved on a post, as a theme developer reads them.
 *
 * @param postId Post ID.
 */
function savedHotspots( postId: number ): Hotspot[] {
	return wpEval< Hotspot[] >( `$result = get_field( 'hotspots', ${ postId } );` );
}

/**
 * Whether a value for the Hotspot Field has been saved on a post.
 *
 * @param postId Post ID.
 */
function isSaved( postId: number ): boolean {
	return wpEval< boolean >( `$result = metadata_exists( 'post', ${ postId }, 'hotspots' );` );
}

test.describe( 'Validating a Hotspot Field on save', () => {
	// Tall enough for the block editor's meta box pane to show the whole field.
	test.use( { viewport: { width: 1280, height: 1600 } } );

	test.beforeAll( () => {
		imageId = Number( wp( 'media', 'import', 'wp-content/plugins/hotspot-field-for-acf/tests/fixtures/hotspot-photo.jpg', '--alt=A living room', '--porcelain' ) );
	} );

	test.afterAll( () => {
		wpEval( `
			foreach ( json_decode( '${ JSON.stringify( fieldGroups ) }', true ) as $key ) {
				$group = acf_get_field_group( $key );
				if ( $group ) { acf_delete_field_group( $group['ID'] ); }
			}
			foreach ( json_decode( '${ JSON.stringify( posts ) }', true ) as $id ) { wp_delete_post( $id, true ); }
			wp_delete_attachment( ${ imageId || 0 }, true );
			$result = true;
		` );
	} );

	test( 'a Hotspot without a Label blocks the save and its row says so', async ( { page } ) => {
		const postId = createPost( 'label', false );
		await openEditor( page, postId );

		const field = page.locator( '.acf-field-hotspot' );
		const image = await waitForImage( field );
		const rows = field.getByRole( 'listitem' );

		await clickAt( image, 0.25, 0.5 );
		await rows.nth( 0 ).getByLabel( 'Label' ).fill( 'Lamp' );
		await clickAt( image, 0.75, 0.2 );
		await expect( rows ).toHaveCount( 2 );

		await clickSave( page );
		await expect( field.getByText( 'Hotspot 2 needs a Label.' ) ).toBeVisible();
		await expect( page.getByText( /Validation failed/ ).first() ).toBeVisible();

		const missing = rows.nth( 1 ).getByLabel( 'Label' );
		await expect( rows.nth( 1 ).getByText( rowError ) ).toBeVisible();
		await expect( missing ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( missing ).toHaveAccessibleDescription( rowError );
		await expect( rows.nth( 0 ).getByText( rowError ) ).toBeHidden();
		await expect( rows.nth( 0 ).getByLabel( 'Label' ) ).not.toHaveAttribute( 'aria-invalid', 'true' );

		expect( isSaved( postId ), 'the blocked save stored Hotspots' ).toBe( false );

		// Giving the Hotspot a Label clears its row's message and lets the save through.
		await missing.fill( 'Chair' );
		await expect( rows.nth( 1 ).getByText( rowError ) ).toBeHidden();
		await expect( missing ).not.toHaveAttribute( 'aria-invalid', 'true' );

		await save( page );
		expect( savedHotspots( postId ).map( ( { label } ) => label ) ).toEqual( [ 'Lamp', 'Chair' ] );
	} );

	test( 'a required Hotspot Field with no Hotspots blocks the save', async ( { page } ) => {
		const postId = createPost( 'required', true );
		await openEditor( page, postId );

		const field = page.locator( '.acf-field-hotspot' );
		const image = await waitForImage( field );

		await clickSave( page );
		await expect( field.getByText( 'Add at least one Hotspot to Hotspots.' ) ).toBeVisible();
		await expect( page.getByText( /Validation failed/ ).first() ).toBeVisible();
		expect( isSaved( postId ), 'the blocked save stored Hotspots' ).toBe( false );

		await clickAt( image, 0.5, 0.5 );
		await field.getByRole( 'listitem' ).nth( 0 ).getByLabel( 'Label' ).fill( 'Lamp' );
		await save( page );
		expect( savedHotspots( postId ).map( ( { label } ) => label ) ).toEqual( [ 'Lamp' ] );
	} );

	test( 'a Hotspot Field that is not required saves without Hotspots', async ( { page } ) => {
		const postId = createPost( 'optional', false );
		await openEditor( page, postId );

		const field = page.locator( '.acf-field-hotspot' );
		await waitForImage( field );

		await save( page );
		await expect( field.locator( '.acf-notice.-error' ) ).toHaveCount( 0 );
		expect( isSaved( postId ) ).toBe( true );
		expect( savedHotspots( postId ) ).toEqual( [] );
	} );
} );

/**
 * Opens a post in the block editor, without the welcome guide.
 *
 * @param page   Playwright page.
 * @param postId Post ID.
 */
async function openEditor( page: Page, postId: number ): Promise< void > {
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
 * Waits until the Hotspot Field shows its image, loaded.
 *
 * @param field Hotspot Field.
 * @return The image.
 */
async function waitForImage( field: Locator ): Promise< Locator > {
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
async function clickAt( locator: Locator, x: number, y: number ): Promise< void > {
	const box = await locator.boundingBox();
	expect( box ).not.toBeNull();
	await locator.click( { position: { x: box!.width * x, y: box!.height * y } } );
}

/**
 * Clicks the block editor's save button.
 *
 * @param page Playwright page.
 */
async function clickSave( page: Page ): Promise< void > {
	await page.getByRole( 'region', { name: 'Editor top bar' } ).getByRole( 'button', { name: /^(Save draft|Save|Update)$/ } ).click();
}

/**
 * Saves the post in the block editor and waits for ACF's meta boxes to save.
 *
 * @param page Playwright page.
 */
async function save( page: Page ): Promise< void > {
	const metaBoxesSaved = page.waitForResponse(
		( response ) => response.url().includes( 'meta-box-loader=1' ) && response.request().method() === 'POST'
	);
	await clickSave( page );
	const response = await metaBoxesSaved;
	expect( response.status(), `meta box save answered ${ response.status() } ${ response.url() }` ).toBeLessThan( 400 );
}
