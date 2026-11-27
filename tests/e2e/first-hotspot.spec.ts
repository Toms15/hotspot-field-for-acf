import { expect, test, clickAt, loadedImage, openEditor, save, wp, wpEval, type Hotspot } from './helpers';

const suffix = Date.now();
const fieldGroupKey = `group_e2e_first_hotspot_${ suffix }`;
const imageFieldKey = `field_e2e_photo_${ suffix }`;
const hotspotFieldKey = `field_e2e_hotspots_${ suffix }`;

let postId: number;
let pageId: number;
let imageId: number;

test.describe( 'First Hotspot in a field group', () => {
	// Tall enough for the block editor's meta box pane to show the whole image.
	test.use( { viewport: { width: 1280, height: 1600 } } );

	test.beforeAll( () => {
		wpEval( `
			$result = acf_import_field_group( array(
				'key'      => '${ fieldGroupKey }',
				'title'    => 'Hotspot e2e ${ suffix }',
				'fields'   => array(
					array( 'key' => '${ imageFieldKey }', 'label' => 'Photo', 'name' => 'photo', 'type' => 'image', 'return_format' => 'id' ),
					array( 'key' => '${ hotspotFieldKey }', 'label' => 'Hotspots', 'name' => 'hotspots', 'type' => 'hotspot', 'linked_image_field' => 'photo' ),
				),
				'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
			) )['ID'];
		` );

		imageId = Number( wp( 'media', 'import', 'wp-content/plugins/hotspot-field-for-acf/tests/fixtures/hotspot-photo.jpg', '--alt=A living room', '--porcelain' ) );
		postId = Number( wp( 'post', 'create', '--post_type=post', '--post_status=publish', '--post_author=1', `--post_title=Hotspot e2e ${ suffix }`, '--porcelain' ) );
		wpEval( `$result = update_field( '${ imageFieldKey }', ${ imageId }, ${ postId } );` );
		pageId = Number(
			wp(
				'post',
				'create',
				'--post_type=page',
				'--post_status=publish',
				`--post_title=Hotspot render e2e ${ suffix }`,
				`--post_content=[hotspot_field name="hotspots" post_id="${ postId }"]`,
				'--porcelain'
			)
		);
	} );

	test.afterAll( () => {
		wpEval( `
			$group = acf_get_field_group( '${ fieldGroupKey }' );
			if ( $group ) { acf_delete_field_group( $group['ID'] ); }
			wp_delete_post( ${ postId || 0 }, true );
			wp_delete_post( ${ pageId || 0 }, true );
			wp_delete_attachment( ${ imageId || 0 }, true );
			$result = true;
		` );
	} );

	test( 'an editor adds Hotspots on the image, saves, and visitors see them', async ( { page } ) => {
		await openEditor( page, postId );

		const field = page.locator( '.acf-field-hotspot' );
		const image = await loadedImage( field );

		// First Hotspot: click at 25% / 50% of the image.
		await clickAt( image, 0.25, 0.5 );
		const rows = field.getByRole( 'listitem' );
		await expect( rows ).toHaveCount( 1 );
		await expect( field.getByRole( 'button', { name: 'Hotspot 1', exact: true } ) ).toBeVisible();
		await expect( field.getByRole( 'button', { name: 'Hotspot 1', exact: true } ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( rows.nth( 0 ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( rows.nth( 0 ).getByLabel( 'Label' ) ).toBeFocused();
		await page.keyboard.type( 'Lamp' );
		await rows.nth( 0 ).getByLabel( 'Description' ).fill( 'A brass lamp.\nSecond line.' );

		// Second Hotspot: appended to the list and selected.
		await clickAt( image, 0.75, 0.2 );
		await expect( rows ).toHaveCount( 2 );
		await expect( rows.nth( 1 ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( rows.nth( 0 ) ).not.toHaveAttribute( 'aria-current' );
		await expect( rows.nth( 1 ).getByLabel( 'Label' ) ).toBeFocused();
		await rows.nth( 1 ).getByLabel( 'Label' ).fill( 'Chair' );

		await expect( field.getByRole( 'button', { name: 'Hotspot 1: Lamp', exact: true } ) ).toBeVisible();
		await expect( field.getByRole( 'button', { name: 'Hotspot 2: Chair', exact: true } ) ).toBeVisible();

		await save( page );

		// The saved value, as a theme developer reads it.
		const saved = wpEval< Hotspot[] >( `$result = get_field( 'hotspots', ${ postId } );` );
		expect( saved.map( ( { label, description } ) => ( { label, description } ) ) ).toEqual( [
			{ label: 'Lamp', description: 'A brass lamp.\nSecond line.' },
			{ label: 'Chair', description: '' },
		] );
		expect( saved[ 0 ].x ).toBeCloseTo( 25, 0 );
		expect( saved[ 0 ].y ).toBeCloseTo( 50, 0 );
		expect( saved[ 1 ].x ).toBeCloseTo( 75, 0 );
		expect( saved[ 1 ].y ).toBeCloseTo( 20, 0 );
		for ( const { x, y } of saved ) {
			expect( Math.round( x * 100 ) / 100 ).toBe( x );
			expect( Math.round( y * 100 ) / 100 ).toBe( y );
		}

		// Reopening the post shows the saved Hotspots.
		await openEditor( page, postId );
		await expect( rows ).toHaveCount( 2 );
		await expect( rows.nth( 0 ).getByLabel( 'Label' ) ).toHaveValue( 'Lamp' );
		await expect( rows.nth( 0 ).getByLabel( 'Description' ) ).toHaveValue( 'A brass lamp.\nSecond line.' );
		await expect( rows.nth( 1 ).getByLabel( 'Label' ) ).toHaveValue( 'Chair' );

		// The frontend renders the image with one button per Hotspot, in order,
		// at the saved positions.
		await page.goto( `/?page_id=${ pageId }` );
		const rendered = page.locator( '.hotspot-field' );
		const renderedImage = rendered.getByRole( 'img', { name: 'A living room' } );
		await expect( renderedImage ).toBeVisible();
		const buttons = rendered.getByRole( 'button' );
		await expect( buttons ).toHaveCount( 2 );
		await expect( buttons.nth( 0 ) ).toHaveAccessibleName( 'Lamp' );
		await expect( buttons.nth( 1 ) ).toHaveAccessibleName( 'Chair' );

		const imageBox = await renderedImage.boundingBox();
		expect( imageBox ).not.toBeNull();
		for ( const [ index, hotspot ] of saved.entries() ) {
			const box = await buttons.nth( index ).boundingBox();
			expect( box ).not.toBeNull();
			const centreX = ( ( box!.x + box!.width / 2 - imageBox!.x ) / imageBox!.width ) * 100;
			const centreY = ( ( box!.y + box!.height / 2 - imageBox!.y ) / imageBox!.height ) * 100;
			expect( Math.abs( centreX - hotspot.x ) ).toBeLessThan( 1 );
			expect( Math.abs( centreY - hotspot.y ) ).toBeLessThan( 1 );
		}
	} );
} );
