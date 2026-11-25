# SproTravel project handoff

**Last checked 25 November 2026.** SproTravel is a WordPress travel and event catalogue in Latvian, English and Russian. Editors maintain offers in Apple Numbers or Excel and upload them through WordPress. Visitors browse by category, compare dates and prices, and follow the booking link or contact the organizer.

## Where the project stands

| Environment | Verified state |
| --- | --- |
| Local | The Sprogis site runs in Local at `http://sprogis.local`. Its plugin directory links to this checkout. The redesigned trip page is available in the working tree. [Preview Tomorrowland](http://sprogis.local/travel-listing/tomorrowland-belgium-2027-weekend-1/). |
| Live | The Droplet serves `https://sprotravel.com` from `/var/www/sprotravel`. The importer includes direct Numbers uploads, explicit click-to-import, long prices and paragraph handling. The live offer template and stylesheet differ from Local. [Live Tomorrowland](https://sprotravel.com/travel-listing/tomorrowland-belgium-2027-weekend-1/). |
| Content | Both databases currently have five published offers (Rome, Disneyland Paris, Madrid, Stray Kids Barcelona and Tomorrowland Belgium) and eight older listings in Trash. IDs can differ between databases. Match offers by their stable workbook Post name. |
| Git | At the time of this handoff, `main` was at `cfa5ea6`. The local detail-page redesign, its README notes, and the importer newline fix and regression tests were uncommitted. The newline fix was already deployed and the six affected live descriptions repaired. Copying only the committed checkout will miss this work. |

The current visual work is local. On 25 November 2026 the user chose design option E for the trip page, which replaced the earlier ice-blue trip page. Each trip page now has:

- a full-width trip photo with the title over it, and a white fact bar overlapping the photo (dates, nights, concert time, place and price);
- About this trip and What’s included on the left;
- a sidebar with the poster as “Trip flyer” (opens in a lightbox), Key dates, the **Rezervēt / Reserve / Забронировать** button (the page’s only main action, at the user’s request) and an “Official tickets” or “Official website” link when it leads somewhere else;
- More trips;
- a bottom price bar on phones.

The homepage hero, category tabs, search bar and cards are deliberately unchanged at the user’s request. The new footer (logo, short text, trip links, contact and company) is shared by every page. All of its text, the Reserve label and More trips are edited in **Travel Listings → Site and footer**. Footer trip links come from one WordPress menu per language (Appearance → Menus), or from categories with trips when no menu is set. Contact email, phone and company details are still empty on Local, so the “Ask SproTravel” button and those footer columns stay hidden until the user fills them in.

## Design review and open options

On 25 November 2026 the homepage catalogue and trip page were audited on Local at 1440 px and 390 px. The review and five redesign options, mocked up with the real offers, are in a private page: [SproTravel Design Options](https://claude.ai/artifact/LwqzovAwBLBYJqSFzLCgHU). The user has not chosen an option yet.

The audit found these problems, which every option fixes:

- No offer has a contact email or phone. Rome, Paris and Madrid have no website link either, so their trip pages have no action. On Tomorrowland and Stray Kids, **Reserve** opens third-party ticket sites (belgium.tomorrowland.com, livenation.es). The proposal is an "Ask SproTravel" action on every trip using site-wide contact details, and an "Official tickets" label for outbound links. The user still needs to supply those contact details.
- Featured images are portrait posters (768 × 1024) but cards crop them to about 2 : 1, showing only the top strip. The category tag then covers the poster's own logo, and the category appears twice.
- Included items, ticket-sale dates and price breakdowns exist only as Latvian text inside the poster images.
- Free-text prices fill two or three bold lines on cards and show Latvian notes on English and Russian pages. The proposal is an amount, "per person" and a short translated note.
- Aviobiļetes and Kruīzi have no offers but appear in the main tab bar, while Festivāli is under More.
- Location and category names are not translated.
- On phones the sticky booking card covers about 30% of the screen. The proposal is a bottom bar with the price and the action.
- Cards show only the start date. Trip pages have no related trips, and the footer has no contact or company details.

The options are A, Poster-aware refresh (current layout with whole posters on blurred frames); B, Flyer wall (full portrait posters on navy); C, Ticket stubs (date-first cards, boarding-pass header, key-dates timeline, cost chart; needs departure and destination per trip); D, Trip calendar (month-grouped list, article-style trip page); and E, Destination magazine (photo-led; needs a landscape photo per trip). The recommendation was B, or A if the ice-blue trip page should stay as it is.

## Where to edit

Paths in this table are relative to `wp-content/plugins/travel-listings/`.

| Work | Entry points |
| --- | --- |
| WordPress setup | `travel-listings.php` registers the `travel_listing` post type, `listing_category` taxonomy, block, shortcode and admin hooks. |
| Public pages | `includes/catalogue.php` selects `templates/travel-site.php` as the shared layout. That layout includes `templates/offer-detail.php` for a single trip. Edit `assets/css/catalogue.css` for its styling. The similarly named `templates/single-travel_listing.php` is a legacy template. |
| Browsing and search | `templates/catalogue.php`, `includes/category-filters.php` and `assets/js/catalogue.js` handle the catalogue and category-specific filters. Search metadata lives in `includes/search-metadata.php`. |
| Trip page details | `includes/trip-details.php` stores the trip photo and its crop position, included items, key dates, Reservation link and the trip’s More trips choice: Automatic (same category, then soonest), Choose trips from a searchable list, or Don’t show. It also adds the edit-screen box and formats dates, nights and euro amounts per language. `includes/site-settings.php` holds the Site and footer page, the footer and the More trips selection. |
| Workbook uploads | `includes/import-admin.php` handles the form and import reports. `assets/js/excel-import.js` handles selection, drag-and-drop and locking controls during submission. `includes/import-categories.php` handles missing categories and row retries. |
| Reading and storing rows | `includes/listing-workbook.php` reads Excel. `includes/numbers/read.py` adapts native Numbers tables and cell images for the same reader. `includes/listing-import.php` validates rows, stores translations and photos, and enforces duplicate and deletion protection. |
| Downloadable template | `assets/templates/sprotravel-listings-template.xlsx` contains Listings and Instructions, with field examples and SEO guidance. The editor’s working file on this Mac is `/Users/aigarspeda/Desktop/SproTravel_Listings_2027.numbers`. |

## Import behavior to preserve

- In **Travel Listings → Excel and Numbers import**, choosing or dropping one file reveals **Import workbook**. Clicking starts the upload and disables the page controls. The uploader sees the result immediately. WordPress keeps row and cell errors in **Import history**. [Live admin page](https://sprotravel.com/wp-admin/edit.php?post_type=travel_listing&page=spro-excel-import)
- Only **Post title** is required. Blank status creates a draft. Use human-readable headers and a stable **Post name** for each trip. WordPress stores it as `_spro_import_key`. Uploading the same key skips the row, including after publication or manual edits. Imported offers support editing and are protected against trash and permanent deletion. Omitting a row from a later workbook leaves the offer in place.
- The **Featured image** cell accepts an embedded picture or a direct image URL. For Numbers, use a single-file document with an image fill in the cell. Exporting from Numbers to Excel can discard those fills. The server converts Numbers privately using a Python virtual environment outside the web root. The README gives setup commands.
- Valid rows import even if others fail. When a category is missing, the report lets the editor select an existing category or create one if permitted. A private temporary workbook supports retrying those rows for 24 hours. Category choices apply to that upload.
- Every trip is one row on Listings. At the user’s request, the new fields are columns next to the fields they belong with:
  - **Card image**, **Trip page photo** and **Photo crop** after Featured image;
  - **Price includes** and **Key dates** after each language’s description;
  - **Reserve button link** after Travel website URL.

  Headers may carry a hint in brackets, such as “Key dates EN (one per line: date time | what happens)”. Both readers ignore a trailing bracketed hint, so the hints never break an upload. Separate Included or Key dates tables are no longer read.
- **Price includes LV/EN/RU** holds one item per line: `item | price`. `170–220` is a range, `335` a fixed price, `335+` or `from 335` means from €335, and no price shows “Included”. Lines match by position across languages, so each language needs the same number of lines. A price may appear in one language or the same in all.
- **Key dates LV/EN/RU** holds one date per line: `13.02.2027 17:00 CET | Worldwide ticket sale` or `16.07.2027 – 18.07.2027 | Festival weekend`. ISO dates also work. Lines match across languages by date and time, not by position.
- These cells load into the trip automatically on upload. For a new trip, a mistake in them is a row error and the trip is not created. For a trip that already exists, the row is skipped as before, but its included items and key dates are added if the trip has none of that kind yet. The report then says “added to N existing trips”, and WordPress edits are never overwritten.
- The import report names the cell and line of each problem: a price that isn’t a number, languages with different line counts, a date that doesn’t exist, or a line without `|`.
- Row 2 of the downloaded template is a filled-in example: the existing Tomorrowland trip in all three languages, with its poster URL, an Unsplash photo, included items and key dates, on a pale yellow background. Its Post name exists on Local and live, so uploading the template unchanged skips it. Uploading it unchanged to the live site would only add Tomorrowland’s included items and key dates, because live has none yet.
- In Excel every new column shows a hint when selected. Columns with fixed values (dates, Post status, Photo crop, price badge, Reserve button link) also show an error window. Numbers ignores these checks but shows the header comments.
- Each trip has three pictures:
  - **Featured image:** the poster. It appears as the Trip flyer lightbox, and on the homepage card when there is no card image.
  - **Card image** (`_travel_card_image_id`): only the homepage card and More trips. Landscape, about 2 : 1.
  - **Trip page photo** (`_travel_photo_id`): the trip page header.

  On Local, each trip’s card image is its Unsplash trip page photo. That changes how the homepage cards look, but not their layout.
- `scripts/update-listings-template.py` always builds the template from the original in commit `cfa5ea6`, using `scripts/template-example.json` for the example row, so rerunning it gives the same file. To change the layout, edit the script and run it again.
- Preserve long price labels, including ranges and ticket qualifications. The listing wraps the full text. Convert literal newline escapes in translated descriptions and excerpts to real breaks on import. Existing offers are skipped on re-upload, so an importer fix alone cannot repair text already stored.

## Continuing work

1. Open the repository at `/Users/aigarspeda/Desktop/sprogis` and run `git status --short --branch` and `git diff`. Preserve the existing working-tree changes. For a UI task, compare the local preview with the live page before choosing a template or planning a deployment.
2. Start **Sprogis** in the Local app. From the repo, `./scripts/local-wp.sh option get home` confirms the active site. PHP, CSS and JS are served directly. For importer changes, run the following against Local. The suite submits real uploads and cleans up its test records. The last run, on 25 November 2026, verified 41 uploads, including the downloaded template with its example row and one-per-line Price includes and Key dates cells from Excel and Numbers.

   ```bash
   "/Users/aigarspeda/Local Sites/sprogis/app/spro-numbers/bin/python" scripts/test-excel-import.py
   ```

3. When the user requests a release, review which changes belong on the Droplet, compare deployed files, and build from the intended checkout with `./scripts/build-travel-listings-zip.sh /tmp/travel-listings.zip`. An existing ZIP can be older than the source. Code deployment and content synchronization are separate operations. The README describes the sync script’s preview and apply modes. A pull with `--apply` replaces local content.

Nothing from the option E work is deployed. Before a release, the user needs to fill in contact and company details in Site and footer, and the live trips need their photos, included items and key dates added. On Local, the five trips have Unsplash photos (free under the Unsplash License; each photo’s source is in its media caption) plus included items and key dates taken from their own descriptions. Price notes and place names on the English and Russian pages are still Latvian because those fields are not translated. The user previously chose to upload their production workbook themselves. The earlier replacement of eight listings was a completed, one-time task.

For more detail, see [README.md](README.md) for operations and environment setup, [PRODUCT.md](PRODUCT.md) for product scope, and [DESIGN.md](DESIGN.md) for the catalogue’s design baseline. The trip-page decisions above are newer than that baseline. Skills used here include `frontend-design` and `webapp-testing` for UI work, `spreadsheets:Spreadsheets` for workbook inspection, and `handoff` for this file.
