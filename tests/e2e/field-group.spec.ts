import { expect, test, wp } from './helpers';

/**
 * Deletes a field group and its fields through ACF, using WP-CLI in wp-env.
 *
 * @param id Field group post ID.
 */
function deleteFieldGroup( id: number ): void {
	wp( 'eval', `acf_delete_field_group( ${ id } );` );
}

test.describe( 'Hotspot Field in a field group', () => {
	let fieldGroupId: number | undefined;

	test.afterEach( () => {
		if ( fieldGroupId ) {
			deleteFieldGroup( fieldGroupId );
			fieldGroupId = undefined;
		}
	} );

	test( 'a site builder can add a Hotspot Field and save its Linked Image Field name', async ( {
		page,
	} ) => {
		const title = `Hotspot e2e ${ Date.now() }`;

		await page.goto( '/wp-admin/post-new.php?post_type=acf-field-group' );
		await page.getByPlaceholder( 'Field Group Title' ).fill( title );

		const field = page.locator( '.acf-field-object' ).first();
		await field.getByLabel( 'Field Label' ).fill( 'Hotspots' );

		// Field Type is an enhanced (select2) select: open it and pick "Hotspot".
		await field.locator( '.acf-field-setting-type .select2-selection' ).click();
		await page.locator( '.select2-search__field' ).fill( 'Hotspot' );
		await page.getByRole( 'option', { name: 'Hotspot', exact: true } ).click();

		const linkedImageField = field.getByLabel( 'Linked Image Field' );
		await expect( linkedImageField ).toBeVisible();
		await linkedImageField.fill( 'photo' );

		await page.getByRole( 'button', { name: 'Save Changes' } ).click();
		await expect( page.getByText( 'Field group published.' ) ).toBeVisible();
		fieldGroupId = Number( new URL( page.url() ).searchParams.get( 'post' ) ) || undefined;
		expect( fieldGroupId ).toBeDefined();

		await page.reload();
		const savedField = page.locator( '.acf-field-object' ).first();
		await expect( savedField.locator( '.acf-field-setting-type select' ) ).toHaveValue( 'hotspot' );

		await savedField.locator( '.edit-field' ).first().click();
		await expect( savedField.getByLabel( 'Linked Image Field' ) ).toHaveValue( 'photo' );
	} );
} );
