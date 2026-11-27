# Hotspot Field for ACF

An [Advanced Custom Fields](https://www.advancedcustomfields.com/) field type that lets editors mark points of interest (Hotspots) on an image, and lets visitors open each Hotspot to read about it.

Requires WordPress 6.8+, PHP 8.3+ and ACF (or ACF Pro) 6.0+. Domain terms are defined in [`GLOSSARY.md`](GLOSSARY.md).

## Development

### Requirements

- Docker
- Node.js 22 and npm
- PHP 8.3+ and Composer (for PHPCS and the PHPUnit dependencies)
- An ACF Pro licence key in a git-ignored `.env` file at the repository root:

  ```
  ACF_PRO_LICENSE=your-licence-key
  ```

  The key is only read by `scripts/install-acf-pro.mjs` and is never printed. In CI it comes from the `ACF_PRO_LICENSE` repository secret.

### Setup

```sh
composer install
npm install
npx playwright install chromium
```

### Environment

The environment is [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (see `.wp-env.json`): WordPress, PHP 8.3, ACF Pro and Plugin Check, with this plugin mapped to `wp-content/plugins/hotspot-field-for-acf` and activated. It also activates the test-only plugin in `tests/fixtures/hotspot-test-blocks`, which registers two ACF blocks holding a Hotspot Field (one per ACF block version, 2 and 3) for the Playwright suite; PHPUnit loads the same blocks.

| Command | What it does |
| --- | --- |
| `npm run env:start` | Downloads ACF Pro into `.wp-env-plugins/` (first run only) and starts WordPress |
| `npm run env:stop` | Stops the environment |
| `npm run env:destroy` | Removes the environment's containers and data |

wp-env mounts the whole repository as the plugin directory, so the dev-only `.htaccess` makes Apache refuse every path that starts with a dot (`.env`, `.git`, `.wp-env-plugins`, …); `tests/e2e/dev-environment.spec.ts` checks this. The file is left out of the distributable plugin.

The development site is at <http://localhost:8888> (user `admin`, password `password`). PHPUnit runs on the separate tests site (port 8889). Set `WP_ENV_PHP_VERSION=8.4` (for example) before `npm run env:start` to use another PHP version.

### Checks

| Command | What it runs |
| --- | --- |
| `npm run lint:php` | PHPCS with WordPress Coding Standards and PHPCompatibilityWP (`composer lint:fix` fixes what it can) |
| `npm run plugin-check` | Builds `build/hotspot-field-for-acf` (git-tracked files minus `.distignore`) and runs WordPress Plugin Check on it; fails on any error or warning |
| `npm run test:php` | PHPUnit inside the wp-env tests container, in three runs: `default` (ACF Pro active), `without-acf` and `old-acf` (ACF reporting 5.12.6) |
| `npm run test:e2e` | Playwright (`tests/e2e`) against the development site |
| `npm test` | All of the above |

The environment must be running for everything except `lint:php`.

GitHub Actions (`.github/workflows/ci.yml`) runs PHPCS, Plugin Check, PHPUnit on PHP 8.3, 8.4, 8.5 and 8.6, and Playwright on every pull request and on `main`. Runs without access to the `ACF_PRO_LICENSE` secret (pull requests from forks or Dependabot) only run PHPCS and skip the other jobs with a warning.

## Licence

GPLv2 or later.
