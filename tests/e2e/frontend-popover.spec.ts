import { expect, test, type Locator, type Page } from '@playwright/test';
import { wp, wpEval } from './wp-cli';

type Hotspot = { x: number; y: number; label: string; description: string };

// In editor order. Window and Rug sit at the image edges.
const hotspots: Hotspot[] = [
	{ x: 50, y: 50, label: 'Lamp', description: 'A brass lamp.\nSecond line.' },
	{ x: 98, y: 4, label: 'Window', description: 'A tall window facing the garden, with linen curtains that let the morning light in.' },
	{ x: 2, y: 97, label: 'Rug', description: 'A hand woven wool rug from a small workshop, about two metres wide.' },
];

const suffix = Date.now();
const fieldGroupKey = `group_e2e_popover_${ suffix }`;
const imageFieldKey = `field_e2e_popover_photo_${ suffix }`;
const hotspotFieldKey = `field_e2e_popover_hotspots_${ suffix }`;

let postId: number;
let pageId: number;
let plainPageId: number;
let twicePageId: number;
let imageId: number;

test.beforeAll( () => {
	wpEval( `
		$result = acf_import_field_group( array(
			'key'      => '${ fieldGroupKey }',
			'title'    => 'Hotspot popover e2e ${ suffix }',
			'fields'   => array(
				array( 'key' => '${ imageFieldKey }', 'label' => 'Photo', 'name' => 'popover_photo', 'type' => 'image', 'return_format' => 'id' ),
				array( 'key' => '${ hotspotFieldKey }', 'label' => 'Hotspots', 'name' => 'popover_hotspots', 'type' => 'hotspot', 'linked_image_field' => 'popover_photo' ),
			),
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
		) )['ID'];
	` );

	imageId = Number( wp( 'media', 'import', 'wp-content/plugins/hotspot-field-for-acf/tests/fixtures/hotspot-photo.jpg', '--alt=A living room', '--porcelain' ) );
	postId = Number( wp( 'post', 'create', '--post_type=post', '--post_status=publish', '--post_author=1', `--post_title=Hotspot popover e2e ${ suffix }`, '--porcelain' ) );
	wpEval( `
		update_field( '${ imageFieldKey }', ${ imageId }, ${ postId } );
		$result = update_field( '${ hotspotFieldKey }', json_decode( '${ JSON.stringify( hotspots ) }', true ), ${ postId } );
	` );
	pageId = Number(
		wp(
			'post',
			'create',
			'--post_type=page',
			'--post_status=publish',
			`--post_title=Hotspot popover e2e ${ suffix }`,
			`--post_content=[hotspot_field name="popover_hotspots" post_id="${ postId }"]`,
			'--porcelain'
		)
	);
	twicePageId = Number(
		wp(
			'post',
			'create',
			'--post_type=page',
			'--post_status=publish',
			`--post_title=Hotspots twice e2e ${ suffix }`,
			`--post_content=[hotspot_field name="popover_hotspots" post_id="${ postId }"]\n\n[hotspot_field name="popover_hotspots" post_id="${ postId }"]`,
			'--porcelain'
		)
	);
	plainPageId = Number(
		wp( 'post', 'create', '--post_type=page', '--post_status=publish', `--post_title=No Hotspots e2e ${ suffix }`, '--post_content=Just text.', '--porcelain' )
	);
} );

test.afterAll( () => {
	wpEval( `
		$group = acf_get_field_group( '${ fieldGroupKey }' );
		if ( $group ) { acf_delete_field_group( $group['ID'] ); }
		wp_delete_post( ${ postId || 0 }, true );
		wp_delete_post( ${ pageId || 0 }, true );
		wp_delete_post( ${ plainPageId || 0 }, true );
		wp_delete_post( ${ twicePageId || 0 }, true );
		wp_delete_attachment( ${ imageId || 0 }, true );
		$result = true;
	` );
} );

test.describe( 'Frontend Popover', () => {
	test( 'click, Enter and Space open the Popover with the Label and Description', async ( { page } ) => {
		const { button, popover } = await openPage( page, 'Lamp' );

		await expect( popover ).toBeHidden();
		await button.click();
		await expect( popover ).toBeVisible();
		await expect( popover.locator( '.hotspot-field__popover-label' ) ).toHaveText( 'Lamp' );
		expect( await popover.locator( '.hotspot-field__popover-description' ).innerText() ).toBe( 'A brass lamp.\nSecond line.' );

		await button.click();
		await expect( popover ).toBeHidden();

		await button.focus();
		await page.keyboard.press( 'Enter' );
		await expect( popover ).toBeVisible();
		await page.keyboard.press( 'Escape' );
		await expect( popover ).toBeHidden();

		await button.focus();
		await page.keyboard.press( 'Space' );
		await expect( popover ).toBeVisible();
	} );

	test( 'Escape, a click outside and activating the button again close it', async ( { page } ) => {
		const { button, popover } = await openPage( page, 'Lamp' );

		await button.click();
		await expect( popover ).toBeVisible();
		await page.keyboard.press( 'Escape' );
		await expect( popover ).toBeHidden();

		await button.click();
		await expect( popover ).toBeVisible();
		const image = page.locator( '.hotspot-field' ).getByRole( 'img', { name: 'A living room' } );
		await clickAt( image, 0.25, 0.25 );
		await expect( popover ).toBeHidden();

		await button.click();
		await expect( popover ).toBeVisible();
		await button.click();
		await expect( popover ).toBeHidden();
	} );

	test( 'only one Popover is open at a time', async ( { page } ) => {
		const lamp = await openPage( page, 'Lamp' );
		const rug = hotspot( page, 'Rug' );

		await lamp.button.click();
		await expect( lamp.popover ).toBeVisible();

		await rug.button.click();
		await expect( rug.popover ).toBeVisible();
		await expect( lamp.popover ).toBeHidden();
		await expect( page.locator( '.hotspot-field__popover:popover-open' ) ).toHaveCount( 1 );

		await lamp.button.focus();
		await page.keyboard.press( 'Enter' );
		await expect( lamp.popover ).toBeVisible();
		await expect( rug.popover ).toBeHidden();
	} );

	test( 'Tab follows the editor-defined order and buttons expose their expanded state', async ( { page } ) => {
		await openPage( page, 'Lamp' );
		const buttons = page.locator( '.hotspot-field' ).getByRole( 'button' );

		await expect( buttons ).toHaveCount( 3 );
		for ( const [ index, { label } ] of hotspots.entries() ) {
			await expect( buttons.nth( index ) ).toHaveAccessibleName( label );
		}

		await buttons.nth( 0 ).focus();
		await page.keyboard.press( 'Tab' );
		await expect( buttons.nth( 1 ) ).toBeFocused();
		await page.keyboard.press( 'Tab' );
		await expect( buttons.nth( 2 ) ).toBeFocused();
		await page.keyboard.press( 'Shift+Tab' );
		await expect( buttons.nth( 1 ) ).toBeFocused();

		// What assistive technology reads, from Chromium's accessibility tree.
		expect( await accessibleExpanded( page, buttons.nth( 1 ) ) ).toBe( false );
		await page.keyboard.press( 'Enter' );
		await expect( hotspot( page, 'Window' ).popover ).toBeVisible();
		expect( await accessibleExpanded( page, buttons.nth( 1 ) ) ).toBe( true );
		await page.keyboard.press( 'Escape' );
		await expect( hotspot( page, 'Window' ).popover ).toBeHidden();
		expect( await accessibleExpanded( page, buttons.nth( 1 ) ) ).toBe( false );
	} );

	test( 'hotspotfield:open carries the Hotspot and the button before the Popover opens', async ( { page } ) => {
		const { button, popover } = await openPage( page, 'Window' );

		await page.evaluate( () => {
			( window as any ).hotspotEvents = [];
			document.addEventListener( 'hotspotfield:open', ( event ) => {
				const { detail } = event as CustomEvent;
				( window as any ).hotspotEvents.push( {
					hotspot: detail.hotspot,
					buttonName: detail.button.textContent,
					targetIsButton: event.target === detail.button,
					bubbles: event.bubbles,
					cancelable: event.cancelable,
					openAlready: detail.button.nextElementSibling.matches( ':popover-open' ),
				} );
			} );
		} );

		await button.click();
		await expect( popover ).toBeVisible();

		expect( await page.evaluate( () => ( window as any ).hotspotEvents ) ).toEqual( [
			{
				hotspot: hotspots[ 1 ],
				buttonName: 'Window',
				targetIsButton: true,
				bubbles: true,
				cancelable: true,
				openAlready: false,
			},
		] );

		// Closing does not fire it.
		await button.click();
		await expect( popover ).toBeHidden();
		expect( await page.evaluate( () => ( window as any ).hotspotEvents.length ) ).toBe( 1 );
	} );

	test( 'cancelling hotspotfield:open keeps the Popover closed', async ( { page } ) => {
		const lamp = await openPage( page, 'Lamp' );
		const rug = hotspot( page, 'Rug' );

		await page.evaluate( () => {
			document.addEventListener( 'hotspotfield:open', ( event ) => {
				if ( 'Lamp' === ( event as CustomEvent ).detail.hotspot.label ) {
					event.preventDefault();
				}
			} );
		} );

		const open = page.locator( '.hotspot-field__popover:popover-open' );

		await lamp.button.click();
		await expect( open ).toHaveCount( 0 );
		expect( await accessibleExpanded( page, lamp.button ) ).toBe( false );

		await lamp.button.press( 'Enter' );
		await expect( open ).toHaveCount( 0 );
		expect( await accessibleExpanded( page, lamp.button ) ).toBe( false );

		await rug.button.click();
		await expect( rug.popover ).toBeVisible();
	} );

	test( 'the Popover stays inside the viewport near the image edges', async ( { page } ) => {
		await page.setViewportSize( { width: 360, height: 640 } );
		await openPage( page, 'Lamp' );

		for ( const { label } of hotspots ) {
			const { button, popover } = hotspot( page, label );
			await button.scrollIntoViewIfNeeded();
			await button.click();
			await expect( popover ).toBeVisible();
			await expectInsideViewportNear( page, popover, button );
			await page.keyboard.press( 'Escape' );
		}

		// A button at the bottom of the viewport opens its Popover above it.
		const rug = hotspot( page, 'Rug' );
		await rug.button.evaluate( ( element ) => element.scrollIntoView( { block: 'end' } ) );
		await rug.button.press( 'Enter' );
		await expect( rug.popover ).toBeVisible();
		await expectInsideViewportNear( page, rug.popover, rug.button );
		expect( ( await rug.popover.boundingBox() )!.y ).toBeLessThan( ( await rug.button.boundingBox() )!.y );

		// It follows its button when the page scrolls.
		await page.mouse.wheel( 0, 120 );
		await expect.poll( async () => ( await rug.button.boundingBox() )!.y - ( await rug.popover.boundingBox() )!.y ).toBeGreaterThan( 0 );
		await expectInsideViewportNear( page, rug.popover, rug.button );
	} );

	test( 'an open Popover stays with its button when the button scrolls out of view', async ( { page } ) => {
		await page.setViewportSize( { width: 360, height: 640 } );
		const { button, popover } = await openPage( page, 'Lamp' );
		await page.addStyleTag( { content: 'body { padding-bottom: 300vh !important; }' } );

		await button.evaluate( ( element ) => element.scrollIntoView( { block: 'center' } ) );
		await button.click();
		await expect( popover ).toBeVisible();
		await expectInsideViewportNear( page, popover, button );

		// Scroll until the button is well above the viewport.
		await page.evaluate( () => window.scrollBy( 0, document.documentElement.clientHeight * 1.5 ) );
		await expect.poll( async () => ( await button.boundingBox() )!.y + ( await button.boundingBox() )!.height ).toBeLessThan( 0 );

		// Not pinned to the viewport edge, away from its button: it moved out
		// of view with it.
		await expect( async () => {
			const box = ( await popover.boundingBox() )!;
			const anchor = ( await button.boundingBox() )!;

			expect( box.y + box.height ).toBeLessThanOrEqual( 0 );
			expect( box.y - ( anchor.y + anchor.height ) ).toBeGreaterThanOrEqual( 0 );
			expect( box.y - ( anchor.y + anchor.height ) ).toBeLessThanOrEqual( 16 );
		} ).toPass( { timeout: 2000 } );

		// Scrolled back, it is right next to its button again.
		await button.evaluate( ( element ) => element.scrollIntoView( { block: 'center' } ) );
		await expect( popover ).toBeVisible();
		await expectInsideViewportNear( page, popover, button );
	} );

	test( 'markers react to hover, keeping their custom colour', async ( { page } ) => {
		const { button } = await openPage( page, 'Lamp' );
		const marker = () =>
			button.evaluate( ( element ) => {
				const style = getComputedStyle( element, '::before' );

				return { scale: new DOMMatrix( style.transform ).a, color: style.backgroundColor };
			} );

		await page.addStyleTag( { content: ':root { --hotspot-field-marker-color: rgb(0, 128, 0); }' } );
		await page.mouse.move( 0, 0 );
		await expect.poll( marker ).toEqual( { scale: 1, color: 'rgb(0, 128, 0)' } );

		await button.hover();
		await expect.poll( async () => ( await marker() ).scale ).toBeGreaterThan( 1 );
		expect( ( await marker() ).color ).toBe( 'rgb(0, 128, 0)' );

		await page.addStyleTag( { content: ':root { --hotspot-field-marker-hover-scale: 1.5; }' } );
		await expect.poll( marker ).toEqual( { scale: 1.5, color: 'rgb(0, 128, 0)' } );

		await page.mouse.move( 0, 0 );
		await expect.poll( async () => ( await marker() ).scale ).toBe( 1 );
	} );

	test( 'markers are large enough to tap, and custom properties restyle them and the Popover', async ( { page } ) => {
		const { button, popover } = await openPage( page, 'Lamp' );

		const box = ( await button.boundingBox() )!;
		expect( box.width ).toBeGreaterThanOrEqual( 44 );
		expect( box.height ).toBeGreaterThanOrEqual( 44 );

		await page.addStyleTag( {
			content: `:root {
				--hotspot-field-marker-size: 60px;
				--hotspot-field-marker-color: rgb(0, 128, 0);
				--hotspot-field-focus-ring-color: rgb(255, 0, 255);
				--hotspot-field-popover-background: rgb(10, 20, 30);
				--hotspot-field-popover-color: rgb(250, 240, 230);
			}`,
		} );

		await expect.poll( async () => ( await button.boundingBox() )!.width ).toBe( 60 );
		expect( await button.evaluate( ( element ) => getComputedStyle( element, '::before' ).backgroundColor ) ).toBe( 'rgb(0, 128, 0)' );

		await button.focus();
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Shift+Tab' );
		await expect( button ).toBeFocused();
		expect( await button.evaluate( ( element ) => getComputedStyle( element ).outlineColor ) ).toBe( 'rgb(255, 0, 255)' );

		await page.keyboard.press( 'Enter' );
		await expect( popover ).toBeVisible();
		expect( await popover.evaluate( ( element ) => getComputedStyle( element ).backgroundColor ) ).toBe( 'rgb(10, 20, 30)' );
		expect( await popover.evaluate( ( element ) => getComputedStyle( element ).color ) ).toBe( 'rgb(250, 240, 230)' );
	} );

	test( 'the same Hotspots rendered twice on a page open their own Popovers', async ( { page } ) => {
		await page.goto( `/?page_id=${ twicePageId }` );
		const fields = page.locator( '.hotspot-field' );
		await expect( fields ).toHaveCount( 2 );
		await expectUniqueIdsAndOwnPopovers( page );

		for ( const index of [ 1, 0 ] ) {
			const button = fields.nth( index ).getByRole( 'button', { name: 'Lamp', exact: true } );
			const popover = fields.nth( index ).locator( '.hotspot-field__popover' ).first();

			await button.click();
			await expect( popover ).toBeVisible();
			await expect( page.locator( '.hotspot-field__popover:popover-open' ) ).toHaveCount( 1 );
			await expectInsideViewportNear( page, popover, button );
			await page.keyboard.press( 'Escape' );
			await expect( popover ).toBeHidden();
		}
	} );

	test( 'frontend assets load only on pages with a Hotspot Field', async ( { page } ) => {
		await page.goto( `/?page_id=${ plainPageId }` );
		await expect( page.getByText( 'Just text.' ) ).toBeVisible();
		await expect( page.locator( 'link#hotspot-field-for-acf-css' ) ).toHaveCount( 0 );
		await expect( page.locator( 'script#hotspot-field-for-acf-js' ) ).toHaveCount( 0 );

		await openPage( page, 'Lamp' );
		await expect( page.locator( 'link#hotspot-field-for-acf-css' ) ).toHaveCount( 1 );
		await expect( page.locator( 'script#hotspot-field-for-acf-js' ) ).toHaveCount( 1 );
	} );
} );

test.describe( 'Frontend Popover without JavaScript', () => {
	test.use( { javaScriptEnabled: false } );

	test( 'each copy of Hotspots rendered twice opens its own Popovers', async ( { page } ) => {
		await page.goto( `/?page_id=${ twicePageId }` );
		const fields = page.locator( '.hotspot-field' );
		await expect( fields ).toHaveCount( 2 );
		await expectUniqueIdsAndOwnPopovers( page );

		for ( const index of [ 1, 0 ] ) {
			for ( const { label } of hotspots ) {
				const button = fields.nth( index ).getByRole( 'button', { name: label, exact: true } );
				const popover = button.locator( 'xpath=following-sibling::*[1][@popover]' );

				await button.click();
				await expect( popover ).toBeVisible();
				await expect( popover.locator( '.hotspot-field__popover-label' ) ).toHaveText( label );
				await expect( page.locator( '.hotspot-field__popover:popover-open' ) ).toHaveCount( 1 );
				await page.keyboard.press( 'Escape' );
				await expect( popover ).toBeHidden();
			}
		}
	} );
} );

test.describe( 'Frontend Popover on a touch screen', () => {
	test.use( { hasTouch: true, isMobile: true, viewport: { width: 390, height: 844 } } );

	test( 'tapping a marker opens its Popover', async ( { page } ) => {
		const { button, popover } = await openPage( page, 'Lamp' );

		await button.tap();
		await expect( popover ).toBeVisible();
		await expect( popover.locator( '.hotspot-field__popover-label' ) ).toHaveText( 'Lamp' );
		await expectInsideViewportNear( page, popover, button );

		await button.tap();
		await expect( popover ).toBeHidden();
	} );
} );

/**
 * Opens the page rendering the Hotspots and returns one Hotspot's button and
 * Popover.
 *
 * @param page  Playwright page.
 * @param label Label of the Hotspot.
 */
async function openPage( page: Page, label: string ): Promise< { button: Locator; popover: Locator } > {
	await page.goto( `/?page_id=${ pageId }` );
	const image = page.locator( '.hotspot-field' ).getByRole( 'img', { name: 'A living room' } );
	await expect( image ).toBeVisible();
	await expect.poll( () => image.evaluate( ( img: HTMLImageElement ) => img.complete && img.naturalWidth > 0 ) ).toBe( true );

	return hotspot( page, label );
}

/**
 * One Hotspot's button and the Popover it controls.
 *
 * @param page  Playwright page.
 * @param label Label of the Hotspot.
 */
function hotspot( page: Page, label: string ): { button: Locator; popover: Locator } {
	const button = page.locator( '.hotspot-field' ).getByRole( 'button', { name: label, exact: true } );

	return { button, popover: button.locator( 'xpath=following-sibling::*[1][@popover]' ) };
}

/**
 * Whether Chromium's accessibility tree reports the element as expanded.
 *
 * @param page    Playwright page.
 * @param locator Element.
 */
async function accessibleExpanded( page: Page, locator: Locator ): Promise< boolean | undefined > {
	const cdp = await page.context().newCDPSession( page );
	try {
		await locator.evaluate( ( element ) => element.setAttribute( 'data-e2e-ax', '' ) );
		const { root } = await cdp.send( 'DOM.getDocument', { depth: 0 } );
		const { nodeId } = await cdp.send( 'DOM.querySelector', { nodeId: root.nodeId, selector: '[data-e2e-ax]' } );
		const { nodes } = await cdp.send( 'Accessibility.getPartialAXTree', { nodeId, fetchRelatives: false } );
		const expanded = nodes[ 0 ]?.properties?.find( ( property ) => property.name === 'expanded' );

		return expanded?.value.value as boolean | undefined;
	} finally {
		await locator.evaluate( ( element ) => element.removeAttribute( 'data-e2e-ax' ) );
		await cdp.detach();
	}
}

/**
 * Asserts that an open Popover lies inside the viewport, right above or
 * below its button and overlapping it horizontally.
 *
 * @param page    Playwright page.
 * @param popover Popover.
 * @param button  Its button.
 */
async function expectInsideViewportNear( page: Page, popover: Locator, button: Locator ): Promise< void > {
	// The script places the Popover in the frame it opens in: retry until then.
	await expect( async () => {
		const viewport = await page.evaluate( () => ( {
			width: document.documentElement.clientWidth,
			height: document.documentElement.clientHeight,
		} ) );
		const box = ( await popover.boundingBox() )!;
		const anchor = ( await button.boundingBox() )!;

		expect( box.x ).toBeGreaterThanOrEqual( 0 );
		expect( box.y ).toBeGreaterThanOrEqual( 0 );
		expect( box.x + box.width ).toBeLessThanOrEqual( viewport.width );
		expect( box.y + box.height ).toBeLessThanOrEqual( viewport.height );

		const gapBelow = box.y - ( anchor.y + anchor.height );
		const gapAbove = anchor.y - ( box.y + box.height );
		expect( Math.max( gapBelow, gapAbove ), 'right above or below the button' ).toBeLessThanOrEqual( 16 );
		expect( Math.max( gapBelow, gapAbove ), 'not over the button' ).toBeGreaterThanOrEqual( 0 );
		expect( box.x, 'horizontally next to the button' ).toBeLessThanOrEqual( anchor.x + anchor.width );
		expect( box.x + box.width, 'horizontally next to the button' ).toBeGreaterThanOrEqual( anchor.x );
	} ).toPass( { timeout: 2000 } );
}

/**
 * Asserts that no two elements on the page share an ID, and that every
 * Hotspot button's `popovertarget` names the Popover rendered right after it.
 *
 * @param page Playwright page.
 */
async function expectUniqueIdsAndOwnPopovers( page: Page ): Promise< void > {
	const { ids, buttons, ownPopovers } = await page.evaluate( () => {
		const all = Array.from( document.querySelectorAll( '[id]' ), ( element ) => element.id );
		const hotspotButtons = Array.from( document.querySelectorAll( '.hotspot-field__hotspot' ) );

		return {
			ids: all,
			buttons: hotspotButtons.length,
			ownPopovers: hotspotButtons.filter( ( button ) => {
				const next = button.nextElementSibling;

				return !! next && next.hasAttribute( 'popover' ) && next.id === button.getAttribute( 'popovertarget' );
			} ).length,
		};
	} );

	expect( buttons ).toBe( 2 * hotspots.length );
	expect( ids.filter( ( id, index ) => ids.indexOf( id ) !== index ), 'duplicate IDs' ).toEqual( [] );
	expect( ownPopovers ).toBe( buttons );
}

/**
 * Clicks an element at a fraction of its width and height.
 *
 * @param locator Element.
 * @param x       Fraction of the width.
 * @param y       Fraction of the height.
 */
async function clickAt( locator: Locator, x: number, y: number ): Promise< void > {
	const box = ( await locator.boundingBox() )!;
	await locator.click( { position: { x: box.width * x, y: box.height * y } } );
}
