import { execFileSync } from 'node:child_process';
import { expect, test as base, type Frame, type FrameLocator, type Locator, type Page, type Request } from '@playwright/test';

export { expect };

/**
 * Playwright's `test`, checking every frontend page a test ends on, and the
 * content of the block editor (where ACF block previews show): no duplicate
 * `id`s, and every `popovertarget` / `aria-controls` names an element on the
 * page. Specs import `test` from here so the check runs after each of them.
 */
export const test = base.extend( {
	page: async ( { page, baseURL }, use ) => {
		await use( page );

		if ( isFrontendPage( page, baseURL ) ) {
			await expectUniqueIds( page );
		}

		// The block editor repeats some of WordPress's own ids (such as its
		// duotone filters) in the content: check the Hotspot Fields' ids.
		const canvas = page.isClosed() ? null : page.frame( { name: 'editor-canvas' } );
		if ( canvas ) {
			await expectUniqueIds( canvas, '.hotspot-field' );
		}
	},
} );

/**
 * Whether a page shows the site's frontend, not the admin or the login.
 *
 * @param page    Playwright page.
 * @param baseURL Site URL.
 */
function isFrontendPage( page: Page, baseURL: string | undefined ): boolean {
	if ( page.isClosed() || ! baseURL ) {
		return false;
	}
	const url = new URL( page.url() );
	return url.origin === new URL( baseURL ).origin && ! /^\/(wp-admin\/|wp-login\.php)/.test( url.pathname );
}

/**
 * Asserts that a page (or the block editor's content frame) has no duplicate
 * `id`s and that every `popovertarget` and `aria-controls` points at an
 * element of the page.
 *
 * @param page   Playwright page or frame.
 * @param within Only check ids and references inside elements matching
 *               this selector; the whole page by default.
 */
export async function expectUniqueIds( page: Page | Frame, within = ':root' ): Promise< void > {
	const problems = await page.evaluate( ( scope ) => {
		const found: string[] = [];
		const counts = new Map< string, number >();
		const inScope = ( selector: string ): Element[] => [ ...document.querySelectorAll( `${ scope }${ selector }, ${ scope } ${ selector }` ) ];
		for ( const element of inScope( '[id]' ) ) {
			counts.set( element.id, ( counts.get( element.id ) ?? 0 ) + 1 );
		}
		for ( const [ id, count ] of counts ) {
			if ( count > 1 ) {
				found.push( `id "${ id }" is used ${ count } times` );
			}
		}
		for ( const attribute of [ 'popovertarget', 'aria-controls' ] ) {
			for ( const element of inScope( `[${ attribute }]` ) ) {
				for ( const id of ( element.getAttribute( attribute ) ?? '' ).split( /\s+/ ).filter( Boolean ) ) {
					if ( ! document.getElementById( id ) ) {
						found.push( `${ attribute }="${ id }" points at no element` );
					}
				}
			}
		}
		return found;
	}, within );

	expect( problems, `duplicate ids or dangling references on ${ page.url() }` ).toEqual( [] );
}

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

export type Hotspot = { x: number; y: number; label: string; description: string };

/**
 * Opens a post in the block editor, without the welcome guide.
 *
 * @param page   Playwright page.
 * @param postId Post ID.
 */
async function loadEditor( page: Page, postId: number ): Promise< void > {
	await page.goto( `/wp-admin/post.php?post=${ postId }&action=edit` );
	await page.waitForFunction( () => ( window as any ).wp?.data?.select( 'core/editor' )?.getCurrentPostId() );
	await page.evaluate( () => ( window as any ).wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false ) );
}

/**
 * Opens a post in the block editor, without the welcome guide, and shows the
 * first Hotspot Field of its field groups.
 *
 * @param page   Playwright page.
 * @param postId Post ID.
 */
export async function openEditor( page: Page, postId: number ): Promise< void > {
	await loadEditor( page, postId );

	// ACF's fields live in the meta box pane under the content; open it.
	const metaBoxes = page.getByRole( 'region', { name: 'Meta Boxes' } ).getByRole( 'button', { name: 'Meta Boxes' } );
	if ( ( await metaBoxes.count() ) && ( await metaBoxes.getAttribute( 'aria-expanded' ) ) === 'false' ) {
		// The resize handle covers the middle of the toggle: use the keyboard.
		await metaBoxes.press( 'Enter' );
		await expect( metaBoxes ).toHaveAttribute( 'aria-expanded', 'true' );
	}
	await page.locator( '.acf-field-hotspot' ).first().scrollIntoViewIfNeeded();
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
export async function save( page: Page ): Promise< void > {
	const metaBoxesSaved = page.waitForResponse(
		( response ) => response.url().includes( 'meta-box-loader=1' ) && response.request().method() === 'POST'
	);
	await clickSave( page );
	const response = await metaBoxesSaved;
	expect( response.status(), `meta box save answered ${ response.status() } ${ response.url() }` ).toBeLessThan( 400 );
}

/**
 * Clicks save when ACF's validation is expected to fail, and waits until the
 * save has settled: ACF answered the validation request and the editor is no
 * longer saving. Fails if the meta boxes (ACF's fields) were saved anyway.
 *
 * @param page Playwright page.
 */
export async function saveBlocked( page: Page ): Promise< void > {
	const metaBoxSaves: string[] = [];
	const onRequest = ( request: Request ): void => {
		if ( request.url().includes( 'meta-box-loader=1' ) && request.method() === 'POST' ) {
			metaBoxSaves.push( request.url() );
		}
	};
	page.on( 'request', onRequest );

	try {
		const validated = page.waitForResponse(
			( response ) =>
				response.url().includes( 'admin-ajax.php' ) &&
				response.request().method() === 'POST' &&
				( response.request().postData() ?? '' ).includes( 'action=acf%2Fvalidate_save_post' )
		);
		await clickSave( page );
		const response = await validated;
		expect( response.status() ).toBeLessThan( 400 );
		expect( ( await response.json() ).data?.valid, 'ACF accepted the content' ).toBeFalsy();

		await expect( page.getByText( /Validation failed/ ).first() ).toBeVisible();
		await page.waitForFunction( () => {
			const editor = ( window as any ).wp.data.select( 'core/editor' );
			return ! editor.isSavingPost() && ! editor.isAutosavingPost();
		} );
	} finally {
		page.off( 'request', onRequest );
	}

	expect( metaBoxSaves, 'the meta boxes were saved despite the failed validation' ).toEqual( [] );
}

/**
 * The ACF test blocks registered by `tests/fixtures/hotspot-test-blocks`, one
 * per ACF block version. Each holds an image field `photo` and a Hotspot
 * Field `hotspots` linked to it, and renders it with hotspot_field_render().
 */
export const TEST_BLOCKS = [
	{ version: 2, name: 'acf/hotspot-test-v2' },
	{ version: 3, name: 'acf/hotspot-test-v3' },
] as const;

export type TestBlock = ( typeof TEST_BLOCKS )[ number ][ 'name' ];

/**
 * Creates a published post holding test blocks, saved as the block editor
 * saves them.
 *
 * @param title  Post title.
 * @param blocks Blocks, in order: test block name, image and Hotspots.
 * @return Post ID.
 */
export function createPostWithBlocks( title: string, blocks: Array< { name: TestBlock; image: number | null; hotspots: Array< Partial< Hotspot > > } > ): number {
	const payload = Buffer.from( JSON.stringify( { title, blocks } ) ).toString( 'base64' );

	return wpEval< number >( `
		$input   = json_decode( base64_decode( '${ payload }' ), true );
		$content = '';
		foreach ( $input['blocks'] as $block ) {
			$prefix   = 'field_' . str_replace( array( 'acf/', '-' ), array( '', '_' ), $block['name'] );
			$hotspots = array_map( static fn ( $hotspot ) => $hotspot + array( 'label' => '', 'description' => '' ), $block['hotspots'] );
			$content .= serialize_block( array(
				'blockName'    => $block['name'],
				'attrs'        => array(
					'name' => $block['name'],
					'data' => array(
						"{$prefix}_photo"    => null === $block['image'] ? '' : (string) $block['image'],
						"{$prefix}_hotspots" => wp_json_encode( $hotspots ),
					),
					'mode' => 'preview',
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			) ) . "\\n\\n";
		}
		$result = wp_insert_post( wp_slash( array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_title'   => $input['title'],
			'post_content' => $content,
		) ) );
	` );
}

/**
 * Opens a post in the block editor, without the welcome guide.
 *
 * @param page   Playwright page.
 * @param postId Post ID.
 */
export async function openBlockEditor( page: Page, postId: number ): Promise< void > {
	await loadEditor( page, postId );
	await expect( editorCanvas( page ).locator( '[data-type^="acf/"]' ).first() ).toBeVisible();
}

/**
 * The block editor's content, where blocks and their previews show.
 *
 * @param page Playwright page.
 */
export function editorCanvas( page: Page ): FrameLocator {
	return page.frameLocator( 'iframe[name="editor-canvas"]' );
}

/**
 * The preview of an ACF block in the block editor's content.
 *
 * @param page  Playwright page.
 * @param index Position of the block in the post, from 0.
 */
export function blockPreview( page: Page, index: number ): Locator {
	return editorCanvas( page ).locator( '[data-type^="acf/"]' ).nth( index );
}

/**
 * Selects a block, as an editor clicking it would, and returns its Hotspot
 * Field in the block settings sidebar.
 *
 * @param page  Playwright page.
 * @param index Position of the block in the post, from 0.
 */
export async function selectBlock( page: Page, index: number ): Promise< Locator > {
	// Clicking the preview could land on a Hotspot's button: click its edge.
	await blockPreview( page, index ).click( { position: { x: 2, y: 2 } } );
	await page.evaluate( () => ( window as any ).wp.data.dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/block' ) );

	const field = page.locator( '.block-editor-block-inspector .acf-field-hotspot' );
	await expect( field ).toHaveCount( 1 );
	return field;
}
