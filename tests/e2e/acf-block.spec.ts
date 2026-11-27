import { type Locator, type Page } from '@playwright/test';
import {
	expect,
	test,
	blockPreview,
	clickAt,
	createPostWithBlocks,
	editorCanvas,
	expectUniqueIds,
	loadedImage,
	openBlockEditor,
	selectBlock,
	TEST_BLOCKS,
	wp,
	wpEval,
} from './helpers';

const suffix = Date.now();
const fieldGroupKey = `group_e2e_block_post_photo_${ suffix }`;

const images: Record< 'block' | 'other' | 'post', number > = { block: 0, other: 0, post: 0 };
const stems: Record< number, RegExp > = {};
const posts: number[] = [];

/**
 * Asserts that a Hotspot Field shows an attachment's image (in any size).
 *
 * @param field        The Hotspot Field.
 * @param attachmentId Attachment ID.
 */
async function expectImage( field: Locator, attachmentId: number ): Promise< Locator > {
	const image = await loadedImage( field );
	await expect( image ).toHaveAttribute( 'src', stems[ attachmentId ] );
	return image;
}

/**
 * The buttons of a rendered Hotspot Field: accessible name and position, in
 * percent, as its style places them.
 *
 * @param rendered Rendered Hotspot Field, or what contains it.
 */
function renderedHotspots( rendered: Locator ): Promise< Array< { name: string; left: number; top: number } > > {
	return rendered.locator( '.hotspot-field button' ).evaluateAll( ( buttons: HTMLElement[] ) =>
		buttons.map( ( button ) => ( {
			name: button.textContent?.trim() ?? '',
			left: parseFloat( button.style.left ),
			top: parseFloat( button.style.top ),
		} ) )
	);
}

/**
 * Asserts, until it holds, that a rendered Hotspot Field shows Hotspots with
 * these names, in order, near these positions.
 *
 * @param rendered Rendered Hotspot Field, or what contains it.
 * @param expected Names and positions, in percent.
 */
async function expectRendered( rendered: Locator, expected: Array< { name: string; left: number; top: number } > ): Promise< void > {
	let actual: Awaited< ReturnType< typeof renderedHotspots > > = [];
	const matches = async (): Promise< boolean > => {
		actual = await renderedHotspots( rendered );
		return (
			actual.length === expected.length &&
			actual.every( ( hotspot, index ) => hotspot.name === expected[ index ].name && Math.abs( hotspot.left - expected[ index ].left ) < 1 && Math.abs( hotspot.top - expected[ index ].top ) < 1 )
		);
	};

	await expect.poll( matches, { message: `rendered Hotspots match ${ JSON.stringify( expected ) }` } ).toBe( true ).catch( ( error: Error ) => {
		throw new Error( `${ error.message }\nLast seen: ${ JSON.stringify( actual ) }` );
	} );
}

/**
 * The position of a marker, in percent, from its style.
 *
 * @param marker Marker.
 */
function markerPosition( marker: Locator ): Promise< { left: number; top: number } > {
	return marker.evaluate( ( node: HTMLElement ) => ( { left: parseFloat( node.style.left ), top: parseFloat( node.style.top ) } ) );
}

/**
 * Saves the post in the block editor and waits until it is saved.
 *
 * @param page Playwright page.
 */
async function saveBlocks( page: Page ): Promise< void > {
	await page.getByRole( 'region', { name: 'Editor top bar' } ).getByRole( 'button', { name: /^(Save|Update)$/ } ).click();
	await page.waitForFunction( () => {
		const editor = ( window as any ).wp.data.select( 'core/editor' );
		return ! editor.isSavingPost() && ! editor.isEditedPostDirty();
	} );
}

test.describe( 'Hotspot Field in ACF blocks', () => {
	// Tall enough for the block settings sidebar to show the whole field.
	test.use( { viewport: { width: 1400, height: 1600 } } );

	test.beforeAll( () => {
		// A top-level image field named like the blocks' Linked Image Field,
		// with another image: the blocks must not use it.
		wpEval( `
			$result = acf_import_field_group( array(
				'key'      => '${ fieldGroupKey }',
				'title'    => 'Block e2e post photo ${ suffix }',
				'fields'   => array(
					array( 'key' => 'field_e2e_block_post_photo_${ suffix }', 'label' => 'Post photo', 'name' => 'photo', 'type' => 'image', 'return_format' => 'id' ),
				),
				'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ) ),
			) )['ID'];
		` );

		for ( const name of Object.keys( images ) as Array< keyof typeof images > ) {
			images[ name ] = Number( wp( 'media', 'import', 'wp-content/plugins/hotspot-field-for-acf/tests/fixtures/hotspot-photo.jpg', `--title=${ name } ${ suffix }`, `--alt=${ name } image`, '--porcelain' ) );
			const url = wpEval< string >( `$result = wp_get_attachment_url( ${ images[ name ] } );` );
			const stem = url.replace( /\.jpe?g$/, '' ).replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
			stems[ images[ name ] ] = new RegExp( `^${ stem }(-\\d+x\\d+)?\\.jpe?g$` );
		}
	} );

	test.afterAll( () => {
		wpEval( `
			$group = acf_get_field_group( '${ fieldGroupKey }' );
			if ( $group ) { acf_delete_field_group( $group['ID'] ); }
			foreach ( array( ${ [ 0, ...posts ].join( ', ' ) } ) as $id ) { wp_delete_post( $id, true ); }
			foreach ( array( ${ Object.values( images ).join( ', ' ) } ) as $id ) { wp_delete_attachment( $id, true ); }
			$result = true;
		` );
	} );

	/**
	 * Creates a post holding blocks, with the post's own `photo` set to
	 * another image than the blocks'.
	 *
	 * @param blocks Blocks, as createPostWithBlocks() takes them.
	 */
	function createPost( blocks: Parameters< typeof createPostWithBlocks >[ 1 ] ): number {
		const postId = createPostWithBlocks( `Block e2e ${ suffix }`, blocks );
		posts.push( postId );
		wpEval( `$result = update_field( 'field_e2e_block_post_photo_${ suffix }', ${ images.post }, ${ postId } );` );
		return postId;
	}

	for ( const { version, name } of TEST_BLOCKS ) {
		test.describe( `ACF block version ${ version }`, () => {
			test( 'an editor places, moves, reorders and removes Hotspots on the block’s image; the preview follows and visitors see the result', async ( { page } ) => {
				const postId = createPost( [ { name, image: images.block, hotspots: [] } ] );
				await openBlockEditor( page, postId );
				const preview = blockPreview( page, 0 );

				// The block's own Linked Image Field, not the post's field of
				// the same name.
				const field = await selectBlock( page, 0 );
				const image = await expectImage( field, images.block );
				const rows = field.getByRole( 'listitem' );
				const marker = ( label: string ) => field.getByRole( 'button', { name: label, exact: true } );

				// Clicking the image adds Hotspots, which the preview shows.
				await clickAt( image, 0.25, 0.5 );
				await expect( rows ).toHaveCount( 1 );
				await expect( rows.nth( 0 ).getByLabel( 'Label' ) ).toBeFocused();
				await page.keyboard.type( 'Lamp' );
				await clickAt( image, 0.75, 0.2 );
				await expect( rows ).toHaveCount( 2 );
				await rows.nth( 1 ).getByLabel( 'Label' ).fill( 'Chair' );
				await expectRendered( preview, [
					{ name: 'Lamp', left: 25, top: 50 },
					{ name: 'Chair', left: 75, top: 20 },
				] );

				// Arrow keys move the selected marker; Shift moves it further.
				const lamp = await markerPosition( marker( 'Hotspot 1: Lamp' ) );
				await marker( 'Hotspot 1: Lamp' ).focus();
				await page.keyboard.press( 'ArrowRight' );
				await page.keyboard.press( 'Shift+ArrowDown' );
				await expect.poll( () => markerPosition( marker( 'Hotspot 1: Lamp' ) ) ).toEqual( { left: lamp.left + 0.5, top: lamp.top + 5 } );
				await expect( marker( 'Hotspot 1: Lamp' ) ).toBeFocused();

				// Dragging moves a marker.
				const chair = await markerPosition( marker( 'Hotspot 2: Chair' ) );
				const imageBox = ( await image.boundingBox() )!;
				const chairBox = ( await marker( 'Hotspot 2: Chair' ).boundingBox() )!;
				await page.mouse.move( chairBox.x + chairBox.width / 2, chairBox.y + chairBox.height / 2 );
				await page.mouse.down();
				await page.mouse.move( chairBox.x + chairBox.width / 2 - imageBox.width * 0.1, chairBox.y + chairBox.height / 2 + imageBox.height * 0.3, { steps: 5 } );
				await page.mouse.up();
				const dragged = await markerPosition( marker( 'Hotspot 2: Chair' ) );
				expect( dragged.left ).toBeCloseTo( chair.left - 10, 0 );
				expect( dragged.top ).toBeCloseTo( chair.top + 30, 0 );

				await expectRendered( preview, [
					{ name: 'Lamp', left: lamp.left + 0.5, top: lamp.top + 5 },
					{ name: 'Chair', left: dragged.left, top: dragged.top },
				] );

				// Up/Down reorder the Hotspots; Remove removes one.
				await rows.nth( 1 ).getByRole( 'button', { name: 'Move Hotspot 2: Chair up' } ).click();
				await expect( rows.nth( 0 ).getByLabel( 'Label' ) ).toHaveValue( 'Chair' );
				await expectRendered( preview, [
					{ name: 'Chair', left: dragged.left, top: dragged.top },
					{ name: 'Lamp', left: lamp.left + 0.5, top: lamp.top + 5 },
				] );
				await rows.nth( 1 ).getByRole( 'button', { name: 'Remove Hotspot 2: Lamp' } ).click();
				await expect( rows ).toHaveCount( 1 );
				await expectRendered( preview, [ { name: 'Chair', left: dragged.left, top: dragged.top } ] );

				// The preview shows the Hotspots on the block's image, with the
				// frontend's styles.
				const previewImage = preview.getByRole( 'img', { name: 'block image' } );
				await expect( previewImage ).toHaveAttribute( 'src', stems[ images.block ] );
				const previewBox = ( await previewImage.boundingBox() )!;
				const buttonBox = ( await preview.getByRole( 'button', { name: 'Chair' } ).boundingBox() )!;
				expect( ( ( buttonBox.x + buttonBox.width / 2 - previewBox.x ) / previewBox.width ) * 100 ).toBeCloseTo( dragged.left, 0 );
				expect( ( ( buttonBox.y + buttonBox.height / 2 - previewBox.y ) / previewBox.height ) * 100 ).toBeCloseTo( dragged.top, 0 );

				// Saved, visitors see the block's image with its Hotspot.
				await saveBlocks( page );
				await page.goto( `/?p=${ postId }` );
				const rendered = page.locator( '.hotspot-test-block' );
				await expect( rendered.getByRole( 'img', { name: 'block image' } ) ).toBeVisible();
				await expectRendered( rendered, [ { name: 'Chair', left: dragged.left, top: dragged.top } ] );
			} );

			test( 'two blocks on a page each use their own image, and their previews keep ids unique', async ( { page } ) => {
				const hotspots = [ { x: 30, y: 40, label: 'Lamp' } ];
				const postId = createPost( [
					{ name, image: images.block, hotspots },
					{ name, image: images.other, hotspots },
				] );
				await openBlockEditor( page, postId );

				await expectRendered( blockPreview( page, 0 ), [ { name: 'Lamp', left: 30, top: 40 } ] );
				await expectRendered( blockPreview( page, 1 ), [ { name: 'Lamp', left: 30, top: 40 } ] );
				await expectUniqueIds( page.frame( { name: 'editor-canvas' } )!, '.hotspot-field' );

				await expectImage( await selectBlock( page, 0 ), images.block );
				await expectImage( await selectBlock( page, 1 ), images.other );

				// A change in one block refreshes its preview, which keeps its
				// ids apart from the other block's.
				const field = await selectBlock( page, 1 );
				await clickAt( await loadedImage( field ), 0.6, 0.6 );
				await field.getByRole( 'listitem' ).nth( 1 ).getByLabel( 'Label' ).fill( 'Rug' );
				await expectRendered( blockPreview( page, 1 ), [
					{ name: 'Lamp', left: 30, top: 40 },
					{ name: 'Rug', left: 60, top: 60 },
				] );
				await expectRendered( blockPreview( page, 0 ), [ { name: 'Lamp', left: 30, top: 40 } ] );
				await expectUniqueIds( page.frame( { name: 'editor-canvas' } )!, '.hotspot-field' );

				// Each preview's buttons open their own block's Popovers.
				for ( const index of [ 0, 1 ] ) {
					const button = blockPreview( page, index ).getByRole( 'button', { name: 'Lamp' } );
					const target = await button.getAttribute( 'popovertarget' );
					await expect( blockPreview( page, index ).locator( `[id="${ target }"]` ) ).toHaveCount( 1 );
				}
			} );
		} );
	}

	test( 'ACF block version 3: the expanded editor places and moves Hotspots, and the preview follows', async ( { page } ) => {
		const postId = createPost( [ { name: 'acf/hotspot-test-v3', image: images.block, hotspots: [ { x: 20, y: 30, label: 'Lamp' } ] } ] );
		await openBlockEditor( page, postId );
		await selectBlock( page, 0 );

		await page.getByRole( 'button', { name: 'Open Expanded Editor' } ).click();
		const dialog = page.getByRole( 'dialog' );
		const field = dialog.locator( '.acf-field-hotspot' );
		const image = await expectImage( field, images.block );
		// The dialog slides into place: click once the image stands still.
		await expect
			.poll( async () => {
				const before = await image.boundingBox();
				await page.waitForTimeout( 200 );
				return JSON.stringify( before ) === JSON.stringify( await image.boundingBox() );
			} )
			.toBe( true );

		await clickAt( image, 0.7, 0.6 );
		const rows = field.getByRole( 'listitem' );
		await expect( rows ).toHaveCount( 2 );
		await page.keyboard.type( 'Chair' );

		const lamp = field.getByRole( 'button', { name: 'Hotspot 1: Lamp', exact: true } );
		await lamp.focus();
		await page.keyboard.press( 'ArrowRight' );
		await expect.poll( () => markerPosition( lamp ) ).toEqual( { left: 20.5, top: 30 } );
		await expect( dialog ).toBeVisible();

		// The preview behind the dialog follows, and keeps what was done
		// once the dialog is closed.
		const expected = [
			{ name: 'Lamp', left: 20.5, top: 30 },
			{ name: 'Chair', left: 70, top: 60 },
		];
		await expectRendered( blockPreview( page, 0 ), expected );
		await dialog.getByRole( 'button', { name: 'Done' } ).click();
		await expect( dialog ).toBeHidden();
		await expectRendered( blockPreview( page, 0 ), expected );

		// The block settings sidebar shows the same Hotspots.
		const sidebarField = await selectBlock( page, 0 );
		await expect( sidebarField.getByRole( 'listitem' ) ).toHaveCount( 2 );
		await expect( sidebarField.getByRole( 'listitem' ).nth( 1 ).getByLabel( 'Label' ) ).toHaveValue( 'Chair' );
		expect( await editorCanvas( page ).locator( '.hotspot-field' ).count() ).toBe( 1 );
	} );
} );
