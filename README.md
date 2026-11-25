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
- `wp-content/plugins/travel-listings/templates/offer-detail.php` (individual offer inside the shared `travel-site.php` layout)

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

Build a private plugin zip for upload:

```bash
./scripts/build-travel-listings-zip.sh
```

Optional custom output path:

```bash
./scripts/build-travel-listings-zip.sh /tmp/travel-listings.zip
```

Optional target override:

```bash
LOCAL_SITE_PLUGIN_DIR="/path/to/site/wp-content/plugins/travel-listings" ./scripts/link-travel-listings-local.sh link
```

If not using symlink, sync manually:

```bash
cp -R wp-content/plugins/travel-listings/. "../../Local Sites/sprogis/app/public/wp-content/plugins/travel-listings/"
```

On individual offers, a sticky summary keeps the location, dates, price and primary link in view while visitors scroll. On phones it sits above the article. Set **Website URL** to the final offer or booking page. If the URL is empty, the page also recognizes an official link labelled at the end of an imported plain-text description. Email and phone remain available for direct enquiries.

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


## Syncing SproTravel content

### Importing a listings workbook

In WordPress admin, open **Travel Listings → Excel and Numbers import**. Drop one `.xlsx` or single-file `.numbers` workbook onto the upload area or choose it with the file picker. Select **Import workbook** to start. The page controls are disabled while WordPress processes the file. Editors and administrators with media upload access can use this page. The result and row-level errors stay in **Import history**.

Use **Download Excel template** on the import page. Fill in **Listings** from row 2. The **Instructions** tab gives field examples and explains how to correct an upload. Only Post title is required. The importer reads the title, categories (existing names or slugs, comma-separated), travel dates, price, location, status, LV/EN/RU listing text, contact details, display setting and featured image. Dates can be real Excel dates, YYYY-MM-DD or DD.MM.YYYY. An empty status defaults to draft.

Reordered headers and leading blank rows are supported. Keep the listing table on one visible sheet. Other visible sheets without listing headers and hidden sheets are ignored. Within the listing table, hidden and filtered data rows are imported too. Ambiguous headers, multiple listing tables and merged table cells produce a workbook error before any rows are imported. Unknown extra columns generate warnings. Invalid data rows show their cell references and correction instructions. Other valid rows are imported. For a missing category, open the import report, choose an existing category or create the workbook category, and select **Apply categories and retry rows**. WordPress retains the pending workbook in its private temporary directory for up to 24 hours. The choices apply to that import.

After changing the template fields, run `scripts/update-listings-template.py` with the same Python environment. It starts from the original template in commit `cfa5ea6` and adds the Card image, Trip page photo, Photo crop, Price includes, Key dates and Reserve button link columns, cell hints and error checks, and the example row from `scripts/template-example.json`. Rerunning it gives the same file.

To run the upload regression suite against the running local site, use `python3 scripts/test-excel-import.py` with openpyxl, Pillow and numbers-parser installed. It exercises the real admin endpoint using temporary test listings and removes its own data and login session afterward.

Keep **Post name** stable for each trip, including when editing the listing title or WordPress permalink. When this field is empty, the importer derives a key from the title and start date. Previously imported keys are skipped. Removing a row from a subsequent workbook leaves the listing intact. Imported listings can be edited, but WordPress blocks trash and permanent deletion. A row that fails can be corrected and uploaded again.

For **Featured image**, embed a picture in Excel, anchor a picture in that row and column, or paste a direct HTTP(S) image URL. WordPress saves the image to its Media Library. In Numbers, place a photo inside the Featured image cell as an image fill, or paste a direct image URL. Floating Numbers canvas objects do not belong to a listing row. Save and upload the .numbers file directly to preserve cell fills.

The Numbers reader requires Python 3.11 or newer and PHP `proc_open`. Set it up once outside the public WordPress directory. From the WordPress root on Linux or macOS run:

```bash
python3 -m venv ../spro-numbers
../spro-numbers/bin/pip install -r wp-content/plugins/travel-listings/includes/numbers/requirements.txt
```

On Ubuntu install `python3-venv` first. For a different virtual environment, set `SPRO_NUMBERS_PYTHON` in wp-config.php to its absolute Python executable path. The server reads Numbers locally. Excel uploads use the existing PHP reader.

### Synchronizing Local and the live site

Start Sprogis in Local, then run these commands from the repository on this Mac.
The scripts use the existing SSH identity for the SproTravel Droplet and its WordPress installation at `/var/www/sprotravel`. Set `REMOTE_HOST`, `REMOTE_WP_PATH`, or `SSH_KEY` to override the connection. The Droplet needs PHP and rsync. The Local installation supplies WP-CLI.

```bash
./scripts/sync-content.py pull
./scripts/sync-content.py pull --apply
```

The first command previews the live content counts. The second replaces Local's posts, pages, listings, media library, taxonomies, comments and uploads, and copies homepage settings. It localizes URLs and preserves WordPress record IDs, including image references in blocks. Local keeps its users, active plugins and theme configuration. Posts whose live author has no matching local login are assigned to a local administrator.

Each applied pull saves a full local database dump and the previous uploads directory under `~/Local Sites/sprogis/sync-backups/before-pull-*`. To restore a backup, set `backup` to the printed directory and run the following from the repository. This restores Local to that point in time. Pull again before pushing edits from a restored site.

```bash
gzip -dc "$backup/local.sql.gz" | ./scripts/local-wp.sh db import -
rsync -rt --delete "$backup/uploads/" "$HOME/Local Sites/sprogis/app/public/wp-content/uploads/"
./scripts/local-wp.sh cache flush
```

To publish edits to an existing travel listing, find its local ID and preview the changes. For example, listing 51 is Koncerti.

```bash
./scripts/local-wp.sh post list --post_type=travel_listing --fields=ID,post_title
./scripts/sync-content.py push 51
./scripts/sync-content.py push 51 --apply
```

A push updates only the selected listing's changed title, text, status, slug, travel fields, categories or featured image. The category or image must already exist on the Droplet. The script checks edited fields against their values at the last pull and saves their previous values locally before writing. Code releases use the separate plugin ZIP workflow above.
