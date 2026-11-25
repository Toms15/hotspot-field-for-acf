import { defineConfig, devices } from '@playwright/test';

/**
 * Browser tests run against the wp-env development site (`npm run env:start`).
 * PHPUnit uses the separate tests site, so the two suites never share data.
 */
export default defineConfig( {
	testDir: './tests/e2e',
	outputDir: './test-results',
	fullyParallel: false,
	workers: 1,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [ [ 'list' ], [ 'html', { open: 'never' } ] ] : 'list',
	globalSetup: './tests/e2e/global-setup.ts',
	use: {
		baseURL: process.env.WP_BASE_URL ?? 'http://localhost:8888',
		storageState: './test-results/.auth/admin.json',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
