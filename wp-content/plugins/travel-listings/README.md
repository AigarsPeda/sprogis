# Travel Listings

Custom WordPress plugin for travel/event listings with:

- custom post type
- archive and single templates
- frontend filters
- infinite scroll
- Gutenberg block support
- multilingual labels/content support

## Development workflow

This plugin does not have a build step right now.

There is:

- no `package.json`
- no bundler like Vite or Webpack
- no Composer setup

That means:

- PHP changes are used directly by WordPress
- CSS changes are used directly by WordPress
- JS changes are used directly by WordPress

## Important paths

Source folder you were editing:

```bash
/Users/aigarspeda/Desktop/sprogis/wp-content/plugins/travel-listings
```

Live Local site plugin folder:

```bash
/Users/aigarspeda/Local Sites/sprogis/app/public/wp-content/plugins/travel-listings
```

`sprogis.local` loads the plugin from the Local site folder, not the Desktop folder.

## Quickest way to see changes

If you edit files in the Desktop folder, copy them into the Local site plugin folder:

```bash
cp -R /Users/aigarspeda/Desktop/sprogis/wp-content/plugins/travel-listings/. "/Users/aigarspeda/Local Sites/sprogis/app/public/wp-content/plugins/travel-listings/"
```

Then hard refresh the browser.

## Best setup for immediate updates

Use a symlink so the Local site plugin folder points to your Desktop source folder.

There is now a helper script in the repo:

```bash
./scripts/link-travel-listings-local.sh status
./scripts/link-travel-listings-local.sh link
```

What it does:

- `status` shows whether the Local plugin path already points to this repo
- `link` backs up the current Local plugin folder and replaces it with a symlink

Default target path:

```bash
/Users/aigarspeda/Local Sites/sprogis/app/public/wp-content/plugins/travel-listings
```

If you need a different Local site path, override it like this:

```bash
LOCAL_SITE_PLUGIN_DIR="/path/to/site/wp-content/plugins/travel-listings" ./scripts/link-travel-listings-local.sh link
```

After that, edits in the Desktop folder will appear immediately on the Local site.

## Browser refresh

After changing files:

- for PHP: normal refresh is usually enough
- for CSS/JS: use hard refresh if the browser cached assets

Mac browser hard refresh options usually include:

- `Cmd + Shift + R`
- opening DevTools and disabling cache while DevTools is open

## Why CSS/JS may look stale

The plugin currently enqueues assets with a fixed version number:

- `travel-listings.css` => `1.0.0`
- `travel-listings.js` => `1.0.0`

Because of that, the browser may keep cached CSS/JS even after file changes.

## Recommended improvement for development

During development, replace fixed asset versions with `filemtime(...)` so CSS/JS cache-bust automatically when files change.

Example idea:

```php
filemtime(plugin_dir_path(__FILE__) . 'assets/css/travel-listings.css')
```

and:

```php
filemtime(plugin_dir_path(__FILE__) . 'assets/js/travel-listings.js')
```

## Build command

There is no build command for this plugin at the moment.

If you only change:

- PHP
- CSS
- JS

then the workflow is:

1. Edit files.
2. Sync to the Local plugin folder, unless using a symlink.
3. Refresh the browser.

## Useful checks

See whether the plugin files are changed:

```bash
git status -- wp-content/plugins/travel-listings
```

See the current diff:

```bash
git diff -- wp-content/plugins/travel-listings
```

## Next improvement ideas

- symlink the Local plugin folder to the Desktop source folder
- switch asset versions to `filemtime(...)`
- add a small dev script for syncing the plugin automatically
