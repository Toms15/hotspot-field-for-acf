import { type Locator, type Page } from '@playwright/test';
import { expect, test, clickAt, loadedImage, openEditor, save, wp, wpEval, type Hotspot } from './helpers';

const suffix = Date.now();
const key = ( name: string ): string => `field_e2e_linked_${ name }_${ suffix }`;
const fieldGroupKey = `group_e2e_linked_${ suffix }`;

const NO_IMAGE = /^No image chosen yet\. Choose an image in the “Photo” field to place Hotspots on it\.$/;
const NOT_FOUND = /^Linked Image Field not found\./;

let postId: number;
const images: Record< 'cover' | 'kitchen' | 'bedroom', number > = { cover: 0, kitchen: 0, bedroom: 0 };
const stems: Record< number, RegExp > = {};

/**
 * The Hotspot Field (or any field) with a given key, outside ACF's templates
 * for new repeater rows.
 *
 * @param scope Page or element to look in.
 * @param name  Name part of the field key.
 */
function field( scope: Page | Locator, name: string ): Locator {
	return scope.locator( `.acf-field[data-key="${ key( name ) }"]:not(.acf-clone .acf-field)` );
}

/**
 * The rows of a repeater, without ACF's template for new rows.
 *
 * @param page Playwright page.
 * @param name Name part of the repeater's key.
 */
function rows( page: Page, name: string ): Locator {
	return field( page, name ).locator( '.acf-row:not(.acf-clone)' );
}

/**
 * Asserts that a Hotspot Field shows an attachment's image (in any size).
 *
 * @param hotspotField The Hotspot Field.
 * @param attachmentId Attachment ID.
 */
async function expectImage( hotspotField: Locator, attachmentId: number ): Promise< void > {
	const image = await loadedImage( hotspotField );
	await expect( image ).toHaveAttribute( 'src', stems[ attachmentId ] );
}

test.describe( 'Linked Image Field', () => {
	test.use( { viewport: { width: 1280, height: 1600 } } );

	test.beforeAll( () => {
		const image = ( name: string ): string => `array( 'key' => '${ key( name ) }', 'label' => 'Photo', 'name' => 'photo', 'type' => 'image', 'return_format' => 'id' )`;
		const hotspots = ( name: string, linked: string ): string =>
			`array( 'key' => '${ key( name ) }', 'label' => 'Hotspots', 'name' => 'hotspots', 'type' => 'hotspot', 'linked_image_field' => '${ linked }' )`;

		wpEval( `
			$result = acf_import_field_group( array(
				'key'      => '${ fieldGroupKey }',
				'title'    => 'Linked Image Field e2e ${ suffix }',
				'fields'   => array(
					${ image( 'photo' ) },
					${ hotspots( 'hotspots', 'photo' ) },
					array( 'key' => '${ key( 'rooms' ) }', 'label' => 'Rooms', 'name' => 'rooms', 'type' => 'repeater', 'layout' => 'block', 'button_label' => 'Add Room', 'sub_fields' => array(
						${ image( 'room_photo' ) },
						${ hotspots( 'room_hotspots', 'photo' ) },
					) ),
					array( 'key' => '${ key( 'details' ) }', 'label' => 'Details', 'name' => 'details', 'type' => 'repeater', 'layout' => 'block', 'sub_fields' => array(
						${ hotspots( 'detail_hotspots', 'photo' ) },
					) ),
					array( 'key' => '${ key( 'broken' ) }', 'label' => 'Broken', 'name' => 'broken', 'type' => 'hotspot', 'linked_image_field' => 'no_such_field' ),
				),
				'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
			) )['ID'];
		` );

		for ( const name of Object.keys( images ) as Array< keyof typeof images > ) {
			images[ name ] = Number( wp( 'media', 'import', 'wp-content/plugins/hotspot-field-for-acf/tests/fixtures/hotspot-photo.jpg', `--title=${ name } ${ suffix }`, '--porcelain' ) );
			// Any size of the attachment: its file name, maybe with a size suffix.
			const url = wpEval< string >( `$result = wp_get_attachment_url( ${ images[ name ] } );` );
			const stem = url.replace( /\.jpe?g$/, '' ).replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
			stems[ images[ name ] ] = new RegExp( `^${ stem }(-\\d+x\\d+)?\\.jpe?g$` );
		}
	} );

	test.beforeEach( () => {
		postId = Number( wp( 'post', 'create', '--post_type=post', '--post_status=publish', '--post_author=1', `--post_title=Linked e2e ${ suffix }`, '--porcelain' ) );
		wpEval( `
			update_field( '${ key( 'photo' ) }', ${ images.cover }, ${ postId } );
			update_field( '${ key( 'hotspots' ) }', array( array( 'x' => 25, 'y' => 50, 'label' => 'Lamp', 'description' => '' ) ), ${ postId } );
			update_field( '${ key( 'rooms' ) }', array(
				array( '${ key( 'room_photo' ) }' => ${ images.kitchen } ),
				array( '${ key( 'room_photo' ) }' => ${ images.bedroom } ),
			), ${ postId } );
			update_field( '${ key( 'details' ) }', array( array( '${ key( 'detail_hotspots' ) }' => array() ) ), ${ postId } );
			$result = true;
		` );
	} );

	test.afterEach( () => {
		wpEval( `wp_delete_post( ${ postId || 0 }, true ); $result = true;` );
	} );

	test.afterAll( () => {
		wpEval( `
			$group = acf_get_field_group( '${ fieldGroupKey }' );
			if ( $group ) { acf_delete_field_group( $group['ID'] ); }
			foreach ( array( ${ Object.values( images ).join( ', ' ) } ) as $id ) { wp_delete_attachment( $id, true ); }
			$result = true;
		` );
	} );

	test( 'each repeater row uses its own image, and saves its own Hotspots', async ( { page } ) => {
		await openEditor( page, postId );

		const roomRows = rows( page, 'rooms' );
		await expect( roomRows ).toHaveCount( 2 );
		await expectImage( field( roomRows.nth( 0 ), 'room_hotspots' ), images.kitchen );
		await expectImage( field( roomRows.nth( 1 ), 'room_hotspots' ), images.bedroom );

		// A Hotspot placed in the second row belongs to that row only.
		const bedroom = field( roomRows.nth( 1 ), 'room_hotspots' );
		await clickAt( await loadedImage( bedroom ), 0.6, 0.3 );
		await bedroom.getByRole( 'listitem' ).getByLabel( 'Label' ).fill( 'Bed' );
		await save( page );

		const saved = wpEval< Array< { hotspots: Hotspot[] } > >( `$result = get_field( 'rooms', ${ postId } );` );
		expect( saved[ 0 ].hotspots ).toEqual( [] );
		expect( saved[ 1 ].hotspots ).toHaveLength( 1 );
		expect( saved[ 1 ].hotspots[ 0 ] ).toMatchObject( { label: 'Bed' } );
		expect( saved[ 1 ].hotspots[ 0 ].x ).toBeCloseTo( 60, 0 );
		expect( saved[ 1 ].hotspots[ 0 ].y ).toBeCloseTo( 30, 0 );
	} );

	test( 'a repeater row without the image field uses the field group’s image', async ( { page } ) => {
		await openEditor( page, postId );

		await expectImage( field( rows( page, 'details' ).nth( 0 ), 'detail_hotspots' ), images.cover );
	} );

	test( 'changing the Linked Image Field replaces the image without a reload; Hotspots keep their positions', async ( { page } ) => {
		await openEditor( page, postId );

		const photo = field( page, 'photo' );
		const hotspots = field( page, 'hotspots' );
		const marker = hotspots.getByRole( 'button', { name: 'Hotspot 1: Lamp', exact: true } );
		await expectImage( hotspots, images.cover );
		const position = await marker.evaluate( ( node: HTMLElement ) => [ node.style.left, node.style.top ] );
		expect( position ).toEqual( [ '25%', '50%' ] );
		const value = await hotspots.locator( 'input[type="hidden"]' ).inputValue();

		// Survives only as long as the page is not reloaded.
		await page.evaluate( () => ( ( window as any ).hotspotFieldNoReload = true ) );

		// Removing the image: the message replaces the image, the Hotspots stay.
		await photo.locator( '.image-wrap' ).hover();
		await photo.locator( 'a[data-name="remove"]' ).click();
		await expect( hotspots.getByText( NO_IMAGE ) ).toBeVisible();
		await expect( hotspots.getByRole( 'img', { name: 'Image to place Hotspots on' } ) ).toBeHidden();
		await expect( hotspots.getByRole( 'listitem' ).getByLabel( 'Label' ) ).toHaveValue( 'Lamp' );

		// Choosing another image shows it at once.
		await photo.getByRole( 'button', { name: 'Add Image' } ).or( photo.getByRole( 'link', { name: 'Add Image' } ) ).click();
		const modal = page.locator( '.media-modal:visible' );
		await modal.getByRole( 'tab', { name: 'Media Library' } ).click();
		await modal.locator( `li.attachment[data-id="${ images.bedroom }"]` ).click();
		await modal.getByRole( 'button', { name: 'Select', exact: true } ).click();
		await expect( modal ).toBeHidden();

		await expectImage( hotspots, images.bedroom );
		await expect( hotspots.getByText( NO_IMAGE ) ).toBeHidden();
		expect( await marker.evaluate( ( node: HTMLElement ) => [ node.style.left, node.style.top ] ) ).toEqual( [ '25%', '50%' ] );
		await expect( hotspots.locator( 'input[type="hidden"]' ) ).toHaveValue( value );
		expect( await page.evaluate( () => ( window as any ).hotspotFieldNoReload ) ).toBe( true );

		// The marker sits at the same place on the new image.
		const imageBox = await ( await loadedImage( hotspots ) ).boundingBox();
		const markerBox = await marker.boundingBox();
		expect( Math.abs( ( markerBox!.x + markerBox!.width / 2 - imageBox!.x ) / imageBox!.width - 0.25 ) ).toBeLessThan( 0.01 );
		expect( Math.abs( ( markerBox!.y + markerBox!.height / 2 - imageBox!.y ) / imageBox!.height - 0.5 ) ).toBeLessThan( 0.01 );

		// The other rows keep their own images.
		await expectImage( field( rows( page, 'rooms' ).nth( 0 ), 'room_hotspots' ), images.kitchen );
	} );

	test( 'a new repeater row says no image has been chosen yet, until one is', async ( { page } ) => {
		await openEditor( page, postId );

		await field( page, 'rooms' ).getByRole( 'link', { name: 'Add Room' } ).or( field( page, 'rooms' ).getByRole( 'button', { name: 'Add Room' } ) ).last().click();
		const roomRows = rows( page, 'rooms' );
		await expect( roomRows ).toHaveCount( 3 );

		const newRoom = field( roomRows.nth( 2 ), 'room_hotspots' );
		await expect( newRoom.getByText( NO_IMAGE ) ).toBeVisible();
		await expect( newRoom.getByText( NOT_FOUND ) ).toHaveCount( 0 );
		await expect( newRoom.getByRole( 'img', { name: 'Image to place Hotspots on' } ) ).toBeHidden();

		const photo = field( roomRows.nth( 2 ), 'room_photo' );
		await photo.getByRole( 'button', { name: 'Add Image' } ).or( photo.getByRole( 'link', { name: 'Add Image' } ) ).click();
		const modal = page.locator( '.media-modal:visible' );
		await modal.getByRole( 'tab', { name: 'Media Library' } ).click();
		await modal.locator( `li.attachment[data-id="${ images.kitchen }"]` ).click();
		await modal.getByRole( 'button', { name: 'Select', exact: true } ).click();

		await expectImage( newRoom, images.kitchen );
		await expect( newRoom.getByText( NO_IMAGE ) ).toBeHidden();
		await expectImage( field( roomRows.nth( 1 ), 'room_hotspots' ), images.bedroom );
	} );

	test( 'a duplicated repeater row has its own editor, with one live region', async ( { page } ) => {
		await openEditor( page, postId );

		const roomRows = rows( page, 'rooms' );
		// ACF shows a row's Duplicate button while Shift is held.
		await roomRows.nth( 0 ).hover();
		await page.keyboard.down( 'Shift' );
		await roomRows.nth( 0 ).locator( ':scope > .acf-row-handle [data-event="duplicate-row"]' ).click();
		await page.keyboard.up( 'Shift' );
		await expect( roomRows ).toHaveCount( 3 );

		for ( const index of [ 0, 1 ] ) {
			const room = field( roomRows.nth( index ), 'room_hotspots' );
			await expectImage( room, images.kitchen );
			await expect( room.locator( '.hotspot-field-editor__status' ) ).toHaveCount( 1 );
		}

		// The copy works on its own: a Hotspot added there stays there.
		const copy = field( roomRows.nth( 1 ), 'room_hotspots' );
		await clickAt( await loadedImage( copy ), 0.5, 0.5 );
		await expect( copy.getByRole( 'listitem' ) ).toHaveCount( 1 );
		await expect( field( roomRows.nth( 0 ), 'room_hotspots' ).getByRole( 'listitem' ) ).toHaveCount( 0 );
	} );

	test( 'a Hotspot Field whose Linked Image Field does not exist says so', async ( { page } ) => {
		await openEditor( page, postId );

		const broken = field( page, 'broken' );
		await expect( broken.getByText( NOT_FOUND ) ).toBeVisible();
		await expect( broken.getByText( NO_IMAGE ) ).toHaveCount( 0 );
		await expect( broken.getByRole( 'img', { name: 'Image to place Hotspots on' } ) ).toBeHidden();
	} );
} );
