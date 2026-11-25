import { expect, test, type Page } from '@playwright/test';

const acfRow = ( page: Page ) => page.locator( 'tr[data-slug="advanced-custom-fields-pro"]' );

test.describe( 'Without ACF', () => {
	test.afterEach( async ( { page } ) => {
		await page.goto( '/wp-admin/plugins.php' );
		const activate = acfRow( page ).getByRole( 'link', { name: /^Activate/ } );
		if ( await activate.isVisible() ) {
			await activate.click();
			await expect( acfRow( page ).getByRole( 'link', { name: /^Deactivate/ } ) ).toBeVisible();
		}
	} );

	test( 'the plugin shows an admin notice instead of breaking the site', async ( { page } ) => {
		const notice = page.locator( '.notice-error' ).filter( { hasText: 'Hotspot Field for ACF requires Advanced Custom Fields' } );

		await page.goto( '/wp-admin/plugins.php' );
		await expect( notice ).toHaveCount( 0 );

		await acfRow( page ).getByRole( 'link', { name: /^Deactivate/ } ).click();

		await expect( notice ).toBeVisible();
		await expect( page.locator( 'tr[data-slug="hotspot-field-for-acf"]' ) ).toHaveClass( /\bactive\b/ );

		const response = await page.goto( '/' );
		expect( response?.status() ).toBe( 200 );
		await expect( page.locator( 'body' ) ).not.toContainText( /critical error|Fatal error/i );
	} );
} );
