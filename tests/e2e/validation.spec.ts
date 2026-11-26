import { expect, test } from '@playwright/test';
import { clickAt, loadedImage, openEditor, save, saveBlocked, wp, wpEval, type Hotspot } from './helpers';

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
		const image = await loadedImage( field );
		const rows = field.getByRole( 'listitem' );

		await clickAt( image, 0.25, 0.5 );
		await rows.nth( 0 ).getByLabel( 'Label' ).fill( 'Lamp' );
		await clickAt( image, 0.75, 0.2 );
		await expect( rows ).toHaveCount( 2 );

		await saveBlocked( page );
		await expect( field.getByText( 'Hotspot 2 needs a Label.' ) ).toBeVisible();
		await expect( page.getByText( /Validation failed/ ).first() ).toBeVisible();

		const missing = rows.nth( 1 ).getByLabel( 'Label' );
		await expect( rows.nth( 1 ).getByText( rowError ) ).toBeVisible();
		await expect( missing ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( missing ).toHaveAccessibleDescription( rowError );
		await expect( rows.nth( 0 ).getByText( rowError ) ).toBeHidden();
		await expect( rows.nth( 0 ).getByLabel( 'Label' ) ).not.toHaveAttribute( 'aria-invalid', 'true' );

		// The invalid row does not look like the selected one: its whole border is dashed.
		await expect( rows.nth( 1 ) ).toHaveCSS( 'border-top-style', 'dashed' );
		await expect( rows.nth( 0 ) ).toHaveCSS( 'border-top-style', 'solid' );

		expect( isSaved( postId ), 'the blocked save stored Hotspots' ).toBe( false );

		// Giving the Hotspot a Label clears its row's message and lets the save through.
		await missing.fill( 'Chair' );
		await expect( rows.nth( 1 ).getByText( rowError ) ).toBeHidden();
		await expect( missing ).not.toHaveAttribute( 'aria-invalid', 'true' );

		await save( page );
		expect( savedHotspots( postId ).map( ( { label } ) => label ) ).toEqual( [ 'Lamp', 'Chair' ] );
	} );

	test( 'row messages stay with their Hotspot when the list is reordered or a Hotspot is removed', async ( { page } ) => {
		const postId = createPost( 'reorder', false );
		await openEditor( page, postId );

		const field = page.locator( '.acf-field-hotspot' );
		const image = await loadedImage( field );
		const rows = field.getByRole( 'listitem' );

		// Lamp, (no Label) "Near the window", Rug, (no Label) "By the door".
		const add = async ( x: number, label: string, description: string ): Promise< void > => {
			await clickAt( image, x, 0.5 );
			const row = rows.last();
			await row.getByLabel( 'Label' ).fill( label );
			await row.getByLabel( 'Description' ).fill( description );
		};
		await add( 0.2, 'Lamp', 'On the table' );
		await add( 0.4, '', 'Near the window' );
		await add( 0.6, 'Rug', 'Under the sofa' );
		await add( 0.8, '', 'By the door' );
		await expect( rows ).toHaveCount( 4 );

		await saveBlocked( page );
		await expect( field.getByText( 'Hotspots 2 and 4 need a Label.' ) ).toBeVisible();

		/**
		 * Checks which rows say their Hotspot needs a Label, by Description,
		 * and that each message is wired to the Label input of its own row.
		 *
		 * @param expected Description and whether its row is invalid, in list order.
		 */
		const expectRows = async ( expected: Array< [ string, boolean ] > ): Promise< void > => {
			await expect( rows ).toHaveCount( expected.length );
			for ( const [ index, [ description, invalid ] ] of expected.entries() ) {
				const row = rows.nth( index );
				const label = row.getByLabel( 'Label' );
				await expect( row.getByLabel( 'Description' ) ).toHaveValue( description );
				await expect( row.getByText( `Hotspot ${ index + 1 }`, { exact: true } ) ).toBeVisible();
				if ( invalid ) {
					await expect( row.getByText( rowError ) ).toBeVisible();
					await expect( label ).toHaveAttribute( 'aria-invalid', 'true' );
					await expect( label ).toHaveAccessibleDescription( rowError );
					// The message it points to is the one in its own row.
					const describedBy = await label.getAttribute( 'aria-describedby' );
					await expect( row.locator( `[id="${ describedBy }"]` ) ).toHaveText( rowError );
					await expect( row ).toHaveCSS( 'border-top-style', 'dashed' );
				} else {
					await expect( row.getByText( rowError ) ).toBeHidden();
					await expect( label ).not.toHaveAttribute( 'aria-invalid', 'true' );
					await expect( label ).not.toHaveAttribute( 'aria-describedby' );
					await expect( row ).toHaveCSS( 'border-top-style', 'solid' );
				}
			}
			// Every message has an ID of its own.
			const ids = await field.locator( '.hotspot-field-editor__row-error' ).evaluateAll( ( nodes ) => nodes.map( ( node ) => node.id ) );
			expect( new Set( ids ).size ).toBe( ids.length );
		};

		await expectRows( [
			[ 'On the table', false ],
			[ 'Near the window', true ],
			[ 'Under the sofa', false ],
			[ 'By the door', true ],
		] );

		await field.getByRole( 'button', { name: 'Move Hotspot 2 up' } ).click();
		await expectRows( [
			[ 'Near the window', true ],
			[ 'On the table', false ],
			[ 'Under the sofa', false ],
			[ 'By the door', true ],
		] );
		// The field's message numbered the Hotspots as they were: it goes, the rows keep theirs.
		await expect( field.getByText( 'Hotspots 2 and 4 need a Label.' ) ).toHaveCount( 0 );
		await expect( field.locator( '.acf-notice.-error' ) ).toHaveCount( 0 );

		await field.getByRole( 'button', { name: 'Move Hotspot 3: Rug down' } ).click();
		await expectRows( [
			[ 'Near the window', true ],
			[ 'On the table', false ],
			[ 'By the door', true ],
			[ 'Under the sofa', false ],
		] );

		await field.getByRole( 'button', { name: 'Remove Hotspot 1', exact: true } ).click();
		await expectRows( [
			[ 'On the table', false ],
			[ 'By the door', true ],
			[ 'Under the sofa', false ],
		] );

		// Selected and invalid: the selected row's solid accent on the left, dashed elsewhere.
		await field.getByRole( 'button', { name: 'Hotspot 2', exact: true } ).focus();
		await expect( rows.nth( 1 ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( rows.nth( 1 ) ).toHaveCSS( 'border-left-style', 'solid' );
		await expect( rows.nth( 1 ) ).toHaveCSS( 'border-top-style', 'dashed' );

		await rows.nth( 1 ).getByLabel( 'Label' ).fill( 'Door' );
		await expectRows( [
			[ 'On the table', false ],
			[ 'By the door', false ],
			[ 'Under the sofa', false ],
		] );

		await save( page );
		expect( savedHotspots( postId ).map( ( { label, description } ) => [ label, description ] ) ).toEqual( [
			[ 'Lamp', 'On the table' ],
			[ 'Door', 'By the door' ],
			[ 'Rug', 'Under the sofa' ],
		] );
	} );

	test( 'a required Hotspot Field with no Hotspots blocks the save', async ( { page } ) => {
		const postId = createPost( 'required', true );
		await openEditor( page, postId );

		const field = page.locator( '.acf-field-hotspot' );
		const image = await loadedImage( field );

		await saveBlocked( page );
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
		await loadedImage( field );

		await save( page );
		await expect( field.locator( '.acf-notice.-error' ) ).toHaveCount( 0 );
		expect( isSaved( postId ) ).toBe( true );
		expect( savedHotspots( postId ) ).toEqual( [] );
	} );
} );
