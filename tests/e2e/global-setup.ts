import { request, type FullConfig } from '@playwright/test';

/**
 * Logs in as the wp-env administrator once and stores the session cookies for
 * all tests. Logging in over HTTP skips interstitials such as the periodic
 * admin email confirmation screen.
 */
export default async function globalSetup( config: FullConfig ): Promise< void > {
	const { baseURL, storageState } = config.projects[ 0 ].use;
	const context = await request.newContext( { baseURL } );

	// Sets the test cookie WordPress expects before accepting a login.
	await context.get( '/wp-login.php' );
	const response = await context.post( '/wp-login.php', {
		form: {
			log: 'admin',
			pwd: 'password',
			testcookie: '1',
			redirect_to: '/wp-admin/',
		},
	} );

	const cookies = ( await context.storageState() ).cookies;
	if ( ! response.ok() || ! cookies.some( ( cookie ) => cookie.name.startsWith( 'wordpress_logged_in_' ) ) ) {
		throw new Error( `Logging in to ${ baseURL } as admin failed (HTTP ${ response.status() }).` );
	}

	await context.storageState( { path: storageState as string } );
	await context.dispose();
}
