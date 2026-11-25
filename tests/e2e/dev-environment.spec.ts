import { expect, test } from '@playwright/test';

/**
 * wp-env mounts the whole repository as the plugin directory, so the dev-only
 * .htaccess must keep secrets and dotfiles off the web. Only status codes are
 * checked: response bodies are never read, so a regression cannot leak the
 * licence key into test output.
 */
const pluginUrl = '/wp-content/plugins/hotspot-field-for-acf/';

test.describe( 'Development environment', () => {
	for ( const path of [ '.env', '%2eenv', 'includes/../.env', '.git/config', '.wp-env.json', '.wp-env-plugins/advanced-custom-fields-pro/acf.php' ] ) {
		test( `does not serve ${ path }`, async ( { request } ) => {
			const response = await request.get( pluginUrl + path, { maxRedirects: 0 } );
			const status = response.status();
			await response.dispose();

			expect( [ 403, 404 ] ).toContain( status );
		} );
	}

	test( 'still serves the plugin itself', async ( { request } ) => {
		const response = await request.get( pluginUrl + 'readme.txt', { maxRedirects: 0 } );
		const status = response.status();
		await response.dispose();

		expect( status ).toBe( 200 );
	} );
} );
