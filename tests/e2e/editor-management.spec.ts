import { expect, test, type Locator, type Page } from '@playwright/test';
import { loadedImage, openEditor, save, wp, wpEval, type Hotspot } from './helpers';

const suffix = Date.now();
const fieldGroupKey = `group_e2e_editor_management_${ suffix }`;
const imageFieldKey = `field_e2e_manage_photo_${ suffix }`;
const hotspotFieldKey = `field_e2e_manage_hotspots_${ suffix }`;

let postId: number;
let imageId: number;

/**
 * Stores Hotspots on the test post, as a theme developer would with
 * update_field().
 *
 * @param hotspots Hotspots to store.
 */
function storeHotspots( hotspots: Array< Partial< Hotspot > > ): void {
	const json = JSON.stringify( hotspots.map( ( hotspot ) => ( { label: '', description: '', ...hotspot } ) ) );
	wpEval( `$result = update_field( '${ hotspotFieldKey }', json_decode( '${ json }', true ), ${ postId } );` );
}

/**
 * The Hotspots saved on the test post, as a theme developer reads them.
 */
function savedHotspots(): Hotspot[] {
	return wpEval< Hotspot[] >( `$result = get_field( '${ hotspotFieldKey }', ${ postId } );` );
}

/**
 * Asserts that an element shows a visible focus indicator.
 *
 * @param locator Focused element.
 */
async function expectVisibleFocus( locator: Locator ): Promise< void > {
	await expect( locator ).toBeFocused();
	await expect
		.poll(
			() =>
				locator.evaluate( ( node ) => {
					const style = getComputedStyle( node );
					const outline = style.outlineStyle !== 'none' && parseFloat( style.outlineWidth ) > 0 && style.outlineColor !== 'rgba(0, 0, 0, 0)';
					return outline || style.boxShadow !== 'none';
				} ),
			{ message: 'focused element has an outline or a focus ring' }
		)
		.toBe( true );
}

/**
 * The centre of an element, in percent of the image's width and height.
 *
 * @param element Element.
 * @param image   Image.
 */
async function centreOn( element: Locator, image: Locator ): Promise< { x: number; y: number } > {
	const box = ( await element.boundingBox() )!;
	const imageBox = ( await image.boundingBox() )!;
	return {
		x: ( ( box.x + box.width / 2 - imageBox.x ) / imageBox.width ) * 100,
		y: ( ( box.y + box.height / 2 - imageBox.y ) / imageBox.height ) * 100,
	};
}

/**
 * The Label of every row, in list order.
 *
 * @param rows List rows.
 */
async function rowLabels( rows: Locator ): Promise< string[] > {
	const labels: string[] = [];
	for ( let index = 0; index < ( await rows.count() ); index++ ) {
		labels.push( await rows.nth( index ).getByLabel( 'Label' ).inputValue() );
	}
	return labels;
}

test.describe( 'Managing Hotspots in the editor', () => {
	// Tall enough for the block editor's meta box pane to show the whole image.
	test.use( { viewport: { width: 1280, height: 1600 } } );

	let page: Page;
	let field: Locator;
	let image: Locator;
	let rows: Locator;

	/**
	 * A marker on the image, by its accessible name.
	 *
	 * @param name Accessible name, such as "Hotspot 1: Lamp".
	 */
	const marker = ( name: string ) => field.getByRole( 'button', { name, exact: true } );

	test.beforeAll( () => {
		wpEval( `
			$result = acf_import_field_group( array(
				'key'      => '${ fieldGroupKey }',
				'title'    => 'Hotspot e2e management ${ suffix }',
				'fields'   => array(
					array( 'key' => '${ imageFieldKey }', 'label' => 'Photo', 'name' => 'manage_photo', 'type' => 'image', 'return_format' => 'id' ),
					array( 'key' => '${ hotspotFieldKey }', 'label' => 'Hotspots', 'name' => 'manage_hotspots', 'type' => 'hotspot', 'linked_image_field' => 'manage_photo' ),
				),
				'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
			) )['ID'];
		` );

		imageId = Number( wp( 'media', 'import', 'wp-content/plugins/hotspot-field-for-acf/tests/fixtures/hotspot-photo.jpg', '--alt=A living room', '--porcelain' ) );
		postId = Number( wp( 'post', 'create', '--post_type=post', '--post_status=publish', '--post_author=1', `--post_title=Hotspot e2e management ${ suffix }`, '--porcelain' ) );
		wpEval( `$result = update_field( '${ imageFieldKey }', ${ imageId }, ${ postId } );` );
	} );

	test.afterAll( () => {
		wpEval( `
			$group = acf_get_field_group( '${ fieldGroupKey }' );
			if ( $group ) { acf_delete_field_group( $group['ID'] ); }
			wp_delete_post( ${ postId || 0 }, true );
			wp_delete_attachment( ${ imageId || 0 }, true );
			$result = true;
		` );
	} );

	/**
	 * Stores the given Hotspots and opens the post in the editor.
	 *
	 * @param testPage Playwright page.
	 * @param hotspots Hotspots to start with.
	 */
	async function start( testPage: Page, hotspots: Array< Partial< Hotspot > > ): Promise< void > {
		storeHotspots( hotspots );
		page = testPage;
		await openEditor( page, postId );
		field = page.locator( '.acf-field-hotspot' );
		image = await loadedImage( field );
		rows = field.getByRole( 'listitem' );
		await expect( rows ).toHaveCount( hotspots.length );
	}

	test( 'selecting a marker selects its row, and selecting a row selects its marker', async ( { page: testPage } ) => {
		await start( testPage, [
			{ x: 20, y: 20, label: 'A' },
			{ x: 50, y: 50, label: 'B' },
			{ x: 80, y: 80, label: 'C' },
		] );

		// Nothing is selected yet.
		await expect( field.locator( '[aria-current="true"]' ) ).toHaveCount( 0 );

		// Marker → row.
		await marker( 'Hotspot 2: B' ).click();
		await expect( marker( 'Hotspot 2: B' ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( rows.nth( 1 ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( field.locator( '[aria-current="true"]' ) ).toHaveCount( 2 );
		// Selecting does not add a Hotspot.
		await expect( rows ).toHaveCount( 3 );

		// Row (by focusing one of its inputs) → marker.
		await rows.nth( 2 ).getByLabel( 'Label' ).click();
		await expect( rows.nth( 2 ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( marker( 'Hotspot 3: C' ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( marker( 'Hotspot 2: B' ) ).not.toHaveAttribute( 'aria-current' );
		await expect( rows.nth( 1 ) ).not.toHaveAttribute( 'aria-current' );

		// Row (by clicking its title) → marker.
		await rows.nth( 0 ).getByText( 'Hotspot 1', { exact: true } ).click();
		await expect( rows.nth( 0 ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( marker( 'Hotspot 1: A' ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( field.locator( '[aria-current="true"]' ) ).toHaveCount( 2 );
	} );

	test( 'dragging a marker moves its Hotspot to where it is dropped', async ( { page: testPage } ) => {
		await start( testPage, [
			{ x: 20, y: 30, label: 'A' },
			{ x: 80, y: 80, label: 'B' },
		] );

		const dragged = marker( 'Hotspot 1: A' );
		const from = ( await dragged.boundingBox() )!;
		const imageBox = ( await image.boundingBox() )!;
		await page.mouse.move( from.x + from.width / 2, from.y + from.height / 2 );
		await page.mouse.down();
		await page.mouse.move( imageBox.x + imageBox.width * 0.6, imageBox.y + imageBox.height * 0.7, { steps: 10 } );
		await page.mouse.up();

		await expect( dragged ).toHaveAttribute( 'aria-current', 'true' );
		await expect( rows.nth( 0 ) ).toHaveAttribute( 'aria-current', 'true' );
		const dropped = await centreOn( dragged, image );
		expect( Math.abs( dropped.x - 60 ) ).toBeLessThan( 1 );
		expect( Math.abs( dropped.y - 70 ) ).toBeLessThan( 1 );
		// Dropping does not add a Hotspot; the other one stays put.
		await expect( rows ).toHaveCount( 2 );
		const other = await centreOn( marker( 'Hotspot 2: B' ), image );
		expect( Math.abs( other.x - 80 ) ).toBeLessThan( 1 );
		expect( Math.abs( other.y - 80 ) ).toBeLessThan( 1 );

		await save( page );
		const saved = savedHotspots();
		expect( saved ).toHaveLength( 2 );
		expect( saved[ 0 ].label ).toBe( 'A' );
		expect( Math.abs( saved[ 0 ].x - dropped.x ) ).toBeLessThan( 1 );
		expect( Math.abs( saved[ 0 ].y - dropped.y ) ).toBeLessThan( 1 );
		expect( saved[ 1 ] ).toEqual( { x: 80, y: 80, label: 'B', description: '' } );
	} );

	test( 'arrow keys move the selected Hotspot by 0.5%, Shift+arrow by 5%, within the image', async ( { page: testPage } ) => {
		await start( testPage, [
			{ x: 50, y: 50, label: 'A' },
			{ x: 1, y: 98, label: 'B' },
		] );

		const first = marker( 'Hotspot 1: A' );
		await first.focus();
		await expect( first ).toHaveAttribute( 'aria-current', 'true' );
		await page.keyboard.press( 'ArrowRight' );
		await page.keyboard.press( 'ArrowRight' );
		await page.keyboard.press( 'ArrowUp' );
		await expect.poll( () => first.evaluate( ( node: HTMLElement ) => [ node.style.left, node.style.top ] ) ).toEqual( [ '51%', '49.5%' ] );
		await page.keyboard.press( 'Shift+ArrowLeft' );
		await page.keyboard.press( 'Shift+ArrowDown' );
		await expect.poll( () => first.evaluate( ( node: HTMLElement ) => [ node.style.left, node.style.top ] ) ).toEqual( [ '46%', '54.5%' ] );

		// Keys never move a Hotspot off the image.
		const second = marker( 'Hotspot 2: B' );
		await second.focus();
		await expect( second ).toHaveAttribute( 'aria-current', 'true' );
		await expect( first ).not.toHaveAttribute( 'aria-current' );
		for ( let step = 0; step < 3; step++ ) {
			await page.keyboard.press( 'ArrowLeft' );
		}
		await page.keyboard.press( 'Shift+ArrowDown' );
		await expect.poll( () => second.evaluate( ( node: HTMLElement ) => [ node.style.left, node.style.top ] ) ).toEqual( [ '0%', '100%' ] );
		await expect( second ).toBeFocused();

		await save( page );
		expect( savedHotspots() ).toEqual( [
			{ x: 46, y: 54.5, label: 'A', description: '' },
			{ x: 0, y: 100, label: 'B', description: '' },
		] );
	} );

	test( 'Remove deletes the Hotspot from the image and the list', async ( { page: testPage } ) => {
		await start( testPage, [
			{ x: 20, y: 20, label: 'A', description: 'First' },
			{ x: 50, y: 50, label: 'B' },
			{ x: 80, y: 80, label: 'C' },
		] );

		await field.getByRole( 'button', { name: 'Remove Hotspot 2: B', exact: true } ).click();
		await expect( rows ).toHaveCount( 2 );
		await expect.poll( () => rowLabels( rows ) ).toEqual( [ 'A', 'C' ] );
		await expect( marker( 'Hotspot 1: A' ) ).toBeVisible();
		await expect( marker( 'Hotspot 2: C' ) ).toBeVisible();
		await expect( field.getByRole( 'button', { name: /^Hotspot \d+/ } ) ).toHaveCount( 2 );
		// Focus moves to the next row instead of being lost.
		await expect( field.getByRole( 'button', { name: 'Remove Hotspot 2: C', exact: true } ) ).toBeFocused();

		await save( page );
		expect( savedHotspots() ).toEqual( [
			{ x: 20, y: 20, label: 'A', description: 'First' },
			{ x: 80, y: 80, label: 'C', description: '' },
		] );

		// Removing every Hotspot, by keyboard, leaves an empty list.
		await field.getByRole( 'button', { name: 'Remove Hotspot 2: C', exact: true } ).focus();
		await page.keyboard.press( 'Enter' );
		await expect( rows ).toHaveCount( 1 );
		await expect( field.getByRole( 'button', { name: 'Remove Hotspot 1: A', exact: true } ) ).toBeFocused();
		await page.keyboard.press( 'Enter' );
		await expect( rows ).toHaveCount( 0 );
		await expect( field.getByRole( 'button', { name: /^Hotspot \d+/ } ) ).toHaveCount( 0 );
		await expect( field.getByText( 'Click the image to add a Hotspot.', { exact: false } ) ).toBeFocused();

		await save( page );
		expect( savedHotspots() ).toEqual( [] );
	} );

	test( 'Up and Down reorder the list, and are disabled at its ends', async ( { page: testPage } ) => {
		await start( testPage, [
			{ x: 20, y: 20, label: 'A' },
			{ x: 50, y: 50, label: 'B' },
			{ x: 80, y: 80, label: 'C' },
		] );

		const up = ( name: string ) => field.getByRole( 'button', { name: `Move ${ name } up`, exact: true } );
		const down = ( name: string ) => field.getByRole( 'button', { name: `Move ${ name } down`, exact: true } );

		await expect( up( 'Hotspot 1: A' ) ).toBeDisabled();
		await expect( down( 'Hotspot 1: A' ) ).toBeEnabled();
		await expect( up( 'Hotspot 2: B' ) ).toBeEnabled();
		await expect( down( 'Hotspot 2: B' ) ).toBeEnabled();
		await expect( up( 'Hotspot 3: C' ) ).toBeEnabled();
		await expect( down( 'Hotspot 3: C' ) ).toBeDisabled();

		await up( 'Hotspot 3: C' ).click();
		await expect.poll( () => rowLabels( rows ) ).toEqual( [ 'A', 'C', 'B' ] );
		// The moved Hotspot stays selected and keeps focus on the same control.
		await expect( up( 'Hotspot 2: C' ) ).toBeFocused();
		await expect( rows.nth( 1 ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( marker( 'Hotspot 2: C' ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( down( 'Hotspot 3: B' ) ).toBeDisabled();
		await expect( up( 'Hotspot 3: B' ) ).toBeEnabled();

		// By keyboard: moving C to the top disables its Up button, so focus
		// moves to its Down button.
		await page.keyboard.press( 'Enter' );
		await expect.poll( () => rowLabels( rows ) ).toEqual( [ 'C', 'A', 'B' ] );
		await expect( up( 'Hotspot 1: C' ) ).toBeDisabled();
		await expect( down( 'Hotspot 1: C' ) ).toBeFocused();
		await expect( up( 'Hotspot 2: A' ) ).toBeEnabled();

		// Markers follow the new order and keep their positions.
		const moved = await centreOn( marker( 'Hotspot 1: C' ), image );
		expect( Math.abs( moved.x - 80 ) ).toBeLessThan( 1 );
		expect( Math.abs( moved.y - 80 ) ).toBeLessThan( 1 );

		await page.keyboard.press( ' ' );
		await expect.poll( () => rowLabels( rows ) ).toEqual( [ 'A', 'C', 'B' ] );

		await save( page );
		expect( savedHotspots() ).toEqual( [
			{ x: 20, y: 20, label: 'A', description: '' },
			{ x: 80, y: 80, label: 'C', description: '' },
			{ x: 50, y: 50, label: 'B', description: '' },
		] );

		// Reopening the post shows the saved order.
		await openEditor( page, postId );
		await expect( rows ).toHaveCount( 3 );
		await expect.poll( () => rowLabels( rows ) ).toEqual( [ 'A', 'C', 'B' ] );
	} );

	test( 'every control is reachable by keyboard, shows its focus and is named after its Hotspot', async ( { page: testPage } ) => {
		await start( testPage, [
			{ x: 30, y: 30, label: 'Lamp' },
			{ x: 70, y: 70, label: 'Chair' },
		] );

		// Tab order: markers in list order, then each row's controls. Disabled
		// buttons (Up on the first row, Down on the last) are skipped.
		const order = [
			marker( 'Hotspot 1: Lamp' ),
			marker( 'Hotspot 2: Chair' ),
			rows.nth( 0 ).getByLabel( 'Label' ),
			rows.nth( 0 ).getByLabel( 'Description' ),
			field.getByRole( 'button', { name: 'Move Hotspot 1: Lamp down', exact: true } ),
			field.getByRole( 'button', { name: 'Remove Hotspot 1: Lamp', exact: true } ),
			rows.nth( 1 ).getByLabel( 'Label' ),
			rows.nth( 1 ).getByLabel( 'Description' ),
			field.getByRole( 'button', { name: 'Move Hotspot 2: Chair up', exact: true } ),
			field.getByRole( 'button', { name: 'Remove Hotspot 2: Chair', exact: true } ),
		];

		// Reach the first marker by keyboard from the element before it.
		await order[ 0 ].focus();
		await page.keyboard.press( 'Shift+Tab' );
		await page.keyboard.press( 'Tab' );
		for ( const [ index, control ] of order.entries() ) {
			if ( index > 0 ) {
				await page.keyboard.press( 'Tab' );
			}
			await expectVisibleFocus( control );
		}

		// Markers say how to move them.
		await expect( marker( 'Hotspot 1: Lamp' ) ).toHaveAccessibleDescription( /arrow keys/ );

		// Names follow the Label as the editor types.
		await rows.nth( 0 ).getByLabel( 'Label' ).fill( 'Brass lamp' );
		await expect( marker( 'Hotspot 1: Brass lamp' ) ).toBeVisible();
		await expect( field.getByRole( 'button', { name: 'Move Hotspot 1: Brass lamp down', exact: true } ) ).toBeVisible();
		await expect( field.getByRole( 'button', { name: 'Remove Hotspot 1: Brass lamp', exact: true } ) ).toBeVisible();

		// A Hotspot without a Label is named by its position.
		await rows.nth( 1 ).getByLabel( 'Label' ).fill( '' );
		await expect( marker( 'Hotspot 2' ) ).toBeVisible();
		await expect( field.getByRole( 'button', { name: 'Remove Hotspot 2', exact: true } ) ).toBeVisible();
	} );
	test( 'moves, reorders and removals are announced to screen readers, once per move', async ( { page: testPage } ) => {
		await start( testPage, [
			{ x: 50, y: 50, label: 'A' },
			{ x: 20, y: 20, label: 'B' },
			{ x: 80, y: 80, label: 'C' },
		] );

		const status = field.getByRole( 'status' );
		await expect( status ).toHaveCount( 1 );
		await expect( status ).toHaveText( '' );

		// Record every message the live region gets.
		await status.evaluate( ( node ) => {
			( window as any ).hotspotAnnouncements = [];
			new MutationObserver( () => {
				if ( node.textContent ) {
					( window as any ).hotspotAnnouncements.push( node.textContent );
				}
			} ).observe( node, { childList: true, characterData: true, subtree: true } );
		} );
		const announcements = () => page.evaluate( () => ( window as any ).hotspotAnnouncements as string[] );

		// A burst of key presses, like a held key, is announced once, with
		// where the Hotspot ends up.
		await marker( 'Hotspot 1: A' ).focus();
		await page.keyboard.press( 'ArrowRight' );
		await page.keyboard.press( 'ArrowRight' );
		await page.keyboard.press( 'ArrowRight' );
		await page.keyboard.press( 'Shift+ArrowUp' );
		await expect( status ).toHaveText( 'Hotspot 1: A, 51.5% across, 45% down' );
		await expect.poll( announcements ).toEqual( [ 'Hotspot 1: A, 51.5% across, 45% down' ] );

		// Reordering says where the Hotspot went.
		await field.getByRole( 'button', { name: 'Move Hotspot 3: C up', exact: true } ).click();
		await expect( status ).toHaveText( 'Hotspot 3: C moved to position 2 of 3.' );

		// Removing says which Hotspot was removed.
		await field.getByRole( 'button', { name: 'Remove Hotspot 1: A', exact: true } ).click();
		await expect( status ).toHaveText( 'Hotspot 1: A removed.' );

		await expect.poll( announcements ).toEqual( [
			'Hotspot 1: A, 51.5% across, 45% down',
			'Hotspot 3: C moved to position 2 of 3.',
			'Hotspot 1: A removed.',
		] );
	} );

	test( 'markers keep their size but can be grabbed within a 44px target, and the nearer marker wins', async ( { page: testPage } ) => {
		await start( testPage, [ { x: 50, y: 50, label: 'A' } ] );

		const first = marker( 'Hotspot 1: A' );
		// The visible marker stays 24px.
		await expect.poll( async () => ( await first.boundingBox() )?.height ).toBe( 24 );
		await expect.poll( async () => ( await first.boundingBox() )?.width ).toBe( 24 );

		// Pressing 20px from the centre, outside the visible marker, grabs it
		// instead of adding a Hotspot; dragging from there moves it.
		const box = ( await first.boundingBox() )!;
		const centre = { x: box.x + box.width / 2, y: box.y + box.height / 2 };
		await page.mouse.move( centre.x + 20, centre.y );
		await page.mouse.down();
		await page.mouse.move( centre.x + 60, centre.y, { steps: 5 } );
		await page.mouse.up();
		await expect( rows ).toHaveCount( 1 );
		await expect( first ).toHaveAttribute( 'aria-current', 'true' );
		await expect.poll( async () => Math.round( ( await first.boundingBox() )!.x - box.x ) ).toBe( 40 );

		// Pressing 20px above or below the centre grabs it too.
		for ( const dy of [ -20, 20 ] ) {
			const now = ( await first.boundingBox() )!;
			await page.mouse.click( now.x + now.width / 2, now.y + now.height / 2 + dy );
			await expect( rows ).toHaveCount( 1 );
		}

		// Two markers 16px apart: their targets overlap, but pressing on the
		// visible part of the first one grabs the first one, even where the
		// second one's target lies over it.
		const imageBox = ( await image.boundingBox() )!;
		const apart = ( 16 / imageBox.width ) * 100;
		await start( testPage, [
			{ x: 50, y: 50, label: 'A' },
			{ x: 50 + apart, y: 50, label: 'B' },
		] );
		const a = ( await marker( 'Hotspot 1: A' ).boundingBox() )!;
		const aCentre = { x: a.x + a.width / 2, y: a.y + a.height / 2 };
		await page.mouse.move( aCentre.x + 2, aCentre.y );
		await page.mouse.down();
		await page.mouse.move( aCentre.x + 2, aCentre.y + 50, { steps: 5 } );
		await page.mouse.up();
		await expect( marker( 'Hotspot 1: A' ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( marker( 'Hotspot 2: B' ) ).not.toHaveAttribute( 'aria-current' );
		await expect.poll( async () => Math.round( ( await marker( 'Hotspot 1: A' ).boundingBox() )!.y - a.y ) ).toBe( 50 );
		await expect.poll( async () => Math.round( ( await marker( 'Hotspot 2: B' ).boundingBox() )!.y - a.y ) ).toBe( 0 );

		// And the second marker is still grabbed where only it is visible.
		const b = ( await marker( 'Hotspot 2: B' ).boundingBox() )!;
		await page.mouse.click( b.x + b.width - 2, b.y + b.height / 2 );
		await expect( marker( 'Hotspot 2: B' ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( rows ).toHaveCount( 2 );

		await save( page );
		const saved = savedHotspots();
		expect( saved ).toHaveLength( 2 );
		expect( saved[ 1 ] ).toEqual( { x: Math.round( ( 50 + apart ) * 100 ) / 100, y: 50, label: 'B', description: '' } );
	} );

	test( 'a cancelled drag, by the browser or with Escape, puts the marker back where it was', async ( { page: testPage } ) => {
		await start( testPage, [
			{ x: 30, y: 40, label: 'A' },
			{ x: 80, y: 80, label: 'B' },
		] );

		const dragged = marker( 'Hotspot 1: A' );
		const style = () => dragged.evaluate( ( node: HTMLElement ) => [ node.style.left, node.style.top ] );
		const imageBox = ( await image.boundingBox() )!;

		/**
		 * Presses the first marker and drags it to 60%, 70% without releasing it.
		 */
		const dragHalfway = async (): Promise< void > => {
			const from = ( await dragged.boundingBox() )!;
			await page.mouse.move( from.x + from.width / 2, from.y + from.height / 2 );
			await page.mouse.down();
			await page.mouse.move( imageBox.x + imageBox.width * 0.6, imageBox.y + imageBox.height * 0.7, { steps: 10 } );
			await expect.poll( style ).not.toEqual( [ '30%', '40%' ] );
		};

		// The browser cancels the gesture (for example, to scroll).
		await page.evaluate( () => {
			document.addEventListener( 'pointerdown', ( event ) => ( ( window as any ).lastPointerId = event.pointerId ), true );
		} );
		await dragHalfway();
		const pointerId = await page.evaluate( () => ( window as any ).lastPointerId );
		await dragged.dispatchEvent( 'pointercancel', { pointerId, bubbles: true } );
		await expect.poll( style ).toEqual( [ '30%', '40%' ] );
		// Further moves and the release no longer move it.
		await page.mouse.move( imageBox.x + imageBox.width * 0.1, imageBox.y + imageBox.height * 0.1, { steps: 3 } );
		await page.mouse.up();
		await expect.poll( style ).toEqual( [ '30%', '40%' ] );

		// The editor presses Escape mid-drag.
		await dragHalfway();
		await page.keyboard.press( 'Escape' );
		await expect.poll( style ).toEqual( [ '30%', '40%' ] );
		await page.mouse.move( imageBox.x + imageBox.width * 0.1, imageBox.y + imageBox.height * 0.1, { steps: 3 } );
		await page.mouse.up();
		await expect.poll( style ).toEqual( [ '30%', '40%' ] );
		await expect( dragged ).toBeFocused();
		await expect( rows ).toHaveCount( 2 );

		await save( page );
		expect( savedHotspots() ).toEqual( [
			{ x: 30, y: 40, label: 'A', description: '' },
			{ x: 80, y: 80, label: 'B', description: '' },
		] );
	} );
} );
