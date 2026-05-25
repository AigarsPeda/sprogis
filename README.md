# Sprogis

Primary documentation for the custom WordPress plugin in this repository.

## What this repo contains

This repo contains one custom plugin:

- `wp-content/plugins/travel-listings`

The plugin provides travel/event listings with:

- custom post type (`travel_listing`)
- custom taxonomy support (including category featured metadata)
- archive and single template overrides
- shortcode rendering (`travel_listings`)
- AJAX filtering and load-more behavior
- Gutenberg block (`travel-listings/listings`) that renders server-side
- multilingual frontend labels/content support (LV, EN, RU)

## Where to start

Main plugin entry point:

- `wp-content/plugins/travel-listings/travel-listings.php`

Templates:

- `wp-content/plugins/travel-listings/templates/archive-travel_listing.php`
- `wp-content/plugins/travel-listings/templates/single-travel_listing.php`

Assets:

- `wp-content/plugins/travel-listings/assets/css/travel-listings.css`
- `wp-content/plugins/travel-listings/assets/css/admin.css`
- `wp-content/plugins/travel-listings/assets/js/travel-listings.js`
- `wp-content/plugins/travel-listings/assets/js/travel-listings-block.js`

Local symlink helper script:

- `scripts/link-travel-listings-local.sh`

## How the plugin works (high-level)

The `Travel_Listings` class wires WordPress hooks in `__construct()` for:

- CPT and block registration
- frontend/admin enqueueing
- shortcode handling
- AJAX endpoints for filtering and loading more posts
- template resolution for single/archive views
- admin settings and taxonomy term metadata

Rendering path (simplified):

1. User views shortcode/block/archive page.
2. Query args are built from filters (date, price, category, language, etc.).
3. Listings are rendered from PHP templates/markup.
4. Frontend JS triggers AJAX for filter or load-more requests.
5. Server returns updated listing markup/data.

## Development workflow

There is no build step right now:

- no `package.json`
- no bundler (Vite/Webpack)
- no Composer setup

So PHP, CSS, and JS are used directly by WordPress.

## Local site integration

Your repository plugin path:

```bash
wp-content/plugins/travel-listings
```

Default Local site plugin path:

```bash
../../Local Sites/sprogis/app/public/wp-content/plugins/travel-listings
```

Use symlink helper:

```bash
./scripts/link-travel-listings-local.sh status
./scripts/link-travel-listings-local.sh link
```

Optional target override:

```bash
LOCAL_SITE_PLUGIN_DIR="/path/to/site/wp-content/plugins/travel-listings" ./scripts/link-travel-listings-local.sh link
```

If not using symlink, sync manually:

```bash
cp -R wp-content/plugins/travel-listings/. "../../Local Sites/sprogis/app/public/wp-content/plugins/travel-listings/"
```

## Cache and refresh notes

The plugin now versions enqueued assets via file modification time (`filemtime`), so CSS/JS changes should invalidate browser cache automatically.

Still use hard refresh when needed:

- `Cmd + Shift + R`

## Task guide for humans and AI agents

When implementing changes, follow this sequence:

1. Locate feature entry points in `travel-listings.php` (hook, shortcode, AJAX action, template filter).
2. Find related frontend behavior in `assets/js/travel-listings.js`.
3. Update templates in `templates/` only when markup/output structure changes.
4. Verify Local site is linked to this repo (`./scripts/link-travel-listings-local.sh status`).
5. Validate in browser:
	- listing rendering
	- filter requests
	- load-more behavior
	- single listing page
	- archive page

### Definition of done for plugin tasks

- No PHP fatal errors/warnings in changed paths.
- Filters and load-more still work for guests and logged-in users.
- Archive and single templates render correctly.
- CSS/JS updates are visible on hard refresh.
- README notes updated if behavior changed.

## Quick checks

```bash
git status -- wp-content/plugins/travel-listings
git diff -- wp-content/plugins/travel-listings
```

