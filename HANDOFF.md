**Goal**
Continue polishing the private WordPress `travel-listings` plugin, with current focus on the single listing page UI in [wp-content/plugins/travel-listings/templates/single-travel_listing.php](/Users/aigarspeda/Desktop/sprogis/wp-content/plugins/travel-listings/templates/single-travel_listing.php:1). The user wants a modern, cleaner hierarchy and tighter visual consistency between the single page and the listing/filter UI.

**Current Progress**
- The single listing page was heavily restyled inline inside [single-travel_listing.php](/Users/aigarspeda/Desktop/sprogis/wp-content/plugins/travel-listings/templates/single-travel_listing.php:1).
- The page no longer uses the old white card layout. It now has:
  - larger featured image with reduced height
  - title and meta moved into the main content flow
  - simpler sidebar with price + action buttons only
- Removed from the single page:
  - the `Travel Listing` pill
  - description card border/background
  - duplicate date/location info from the sidebar
  - `Detaļas` container entirely
- Price experiments:
  - five temporary variants were added, then removed
  - the chosen variant is the former `C` variant and is now the only one left
- Current price block behavior:
  - label and value are right-aligned
  - desktop card uses `fit-content` with `min-width`
  - mobile card stays full width
  - value is forced to stay on one line with `white-space: nowrap`
- A fresh upload package was built:
  - [travel-listings.zip](/Users/aigarspeda/Desktop/sprogis/travel-listings.zip)

**What Worked**
- Inline CSS changes in [single-travel_listing.php](/Users/aigarspeda/Desktop/sprogis/wp-content/plugins/travel-listings/templates/single-travel_listing.php:1) were the fastest way to iterate on the single page.
- The user preferred:
  - less card/shadow treatment overall
  - less duplicated information
  - cleaner, narrower price presentation
  - reusing existing surface/background colors from the meta pills
- The chosen price direction was the former variant `C`, then refined:
  - label orientation returned to normal
  - label color changed to the same accent family as the pill icons
  - width was made content-aware on desktop
  - dark/light text contrast was fixed
- The packaging script works:
  - `./scripts/build-travel-listings-zip.sh`

**What Didn't Work**
- Earlier five price variants were too visually similar; user explicitly said they were not different enough.
- Bright blue gradient price card treatment no longer matches the simplified single-page design direction.
- Duplicating date/location in both the title area and sidebar was unwanted.
- Large wrapper shadows, card borders, and oversized image area were considered too heavy.

**Next Steps**
- Verify the currently kept price block visually in both light and dark mode after the latest changes.
- Important: the user still appears unhappy with the current price block background in dark mode. They asked to “reuse background colors” and then “in dark mode also.”
  - Recheck [single-travel_listing.php](/Users/aigarspeda/Desktop/sprogis/wp-content/plugins/travel-listings/templates/single-travel_listing.php:341) onward.
  - The intent is: price card should share the same surface language as the date/location pills.
- Recheck whether the price block width now feels right:
  - desktop should auto-size to content
  - mobile should stay full width
  - no line breaks like `100` on one line and `eur` on another
- If more cleanup is needed, consider moving the single-page CSS out of the template into a dedicated stylesheet later, but only after visual approval.
- If the user asks again, rebuild the zip with:
```bash
./scripts/build-travel-listings-zip.sh
```

**Skills In Use**
- `handoff`
- `frontend-design`

