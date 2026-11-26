import { expect, type Locator, type Page, type Request } from '@playwright/test';

export { wp, wpEval } from './wp-cli';

export type Hotspot = { x: number; y: number; label: string; description: string };

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
