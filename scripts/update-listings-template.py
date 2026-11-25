#!/usr/bin/env python3
"""Add the trip page fields to the downloadable listings template. Running it again changes nothing.

Every trip stays on one row of the Listings sheet. New columns sit next to the fields they belong with:
Trip page photo and Photo crop after Featured image, Price includes and Key dates after each language's
description, and Reserve button link after Travel website URL. Headers carry a short hint in brackets,
which the importer ignores. Row 2 is a filled-in example: the existing Tomorrowland trip, which an
upload skips because its Post name already exists. Every new column shows a hint when selected, and
columns with fixed values show an error window for anything the importer would reject.

The script always starts from the original template in commit cfa5ea6, so running it again gives the
same file. To change the layout, edit this script and run it.

Run with the Local Numbers environment, which has openpyxl:
    "/Users/aigarspeda/Local Sites/sprogis/app/spro-numbers/bin/python" scripts/update-listings-template.py
"""
import datetime
import io
import json
import subprocess
from copy import copy
from pathlib import Path

from openpyxl import load_workbook
from openpyxl.comments import Comment
from openpyxl.styles import Alignment, PatternFill
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.datavalidation import DataValidation

ROOT = Path(__file__).resolve().parents[1]
TEMPLATE = ROOT / "wp-content/plugins/travel-listings/assets/templates/sprotravel-listings-template.xlsx"
EXAMPLE = json.loads((Path(__file__).with_name("template-example.json")).read_text())
LAST_ROW = 1000
ORIGINAL = "cfa5ea6"

original = subprocess.run(["git", "show", f"{ORIGINAL}:{TEMPLATE.relative_to(ROOT).as_posix()}"], cwd=ROOT, check=True, capture_output=True).stdout
book = load_workbook(io.BytesIO(original))
listings = book["Listings"]
guide = book["Instructions"]

header_style = copy(listings["A1"]._style)
header_height = listings.row_dimensions[1].height
existing = {}
for cell in listings[1]:
    if cell.value:
        width = listings.column_dimensions[cell.column_letter].width
        existing[cell.value] = (cell.comment.text if cell.comment else "", width)

LINE_PRICE = "One item per line: the item, then | and the price in euros. 170–220 is a range, 335 a fixed price, 335+ means from €335. Leave out the price to show “Included”. Line 1 in every language is the same item."
LINE_DATES = "One date per line: the date, an optional end date and time, then | and what happens. Example: 13.02.2027 17:00 CET | Worldwide ticket sale. For several days: 16.07.2027 – 18.07.2027 | Festival weekend."
NEW = {
    "Card image (homepage card, landscape)": ("Optional. The picture on this trip’s homepage card and in More trips, about twice as wide as tall (for example 1200 × 600). Paste a direct image URL or put one picture in this cell. Blank shows the Featured image (poster).", 34),
    "Trip page photo (wide landscape)": ("Optional. A wide landscape photo across the top of the trip page. Paste a direct image URL or put one picture in this cell. The Featured image stays the poster, shown as the trip flyer.", 34),
    "Photo crop (top / center / bottom)": ("Optional. Which part of the trip page photo stays visible when it is cropped. Blank means center.", 20),
    "Reserve button link": ("Optional. Where the Reserve button on the trip page leads, for example a booking form. Blank uses the trip’s contact email or phone, then the site-wide contact details.", 34),
}
LANGUAGE = {"LV": "Latvian", "EN": "English", "RU": "Russian"}
for code, name in LANGUAGE.items():
    NEW[f"Price includes {code} (one per line: item | price)"] = (f"Optional, in {name}. What the price covers, shown on the trip page. {LINE_PRICE}", 46)
    NEW[f"Key dates {code} (one per line: date time | what happens)"] = (f"Optional, in {name}. Ticket sales, registration, the event and travel days, shown on the trip page in date order. {LINE_DATES}", 50)

order = ["Post title", "Categories", "Travel date from", "Travel date to", "Travel price", "Travel location", "Post status",
         "Featured image", "Card image (homepage card, landscape)", "Trip page photo (wide landscape)", "Photo crop (top / center / bottom)"]
for code in LANGUAGE:
    order += [f"Travel title {code}", f"Travel excerpt {code}", f"Travel description {code}",
              f"Price includes {code} (one per line: item | price)", f"Key dates {code} (one per line: date time | what happens)"]
order += ["Travel contact email", "Travel contact phone", "Travel website URL", "Reserve button link", "Travel price on image", "Post name"]
assert set(existing) <= set(order), set(existing) - set(order)

# Rebuild the header row in the new order, keeping each existing column's note and width.
for cell in list(listings[1]):
    cell.value, cell.comment = None, None
listings.data_validations.dataValidation = []
letters = {}
for index, name in enumerate(order, start=1):
    letter = get_column_letter(index)
    letters[name.split(" (")[0]] = letter
    note, width = existing.get(name) or NEW[name]
    if name == "Featured image":
        note = "Optional. The trip’s poster. It is shown as the Trip flyer on the trip page, and on the homepage card when Card image is blank. Paste a direct image URL or put one picture in this cell."
    cell = listings.cell(1, index, name)
    cell._style = copy(header_style)
    comment = Comment(note, "SproTravel")
    comment.width, comment.height = 170, 110
    cell.comment = comment
    listings.column_dimensions[letter].width = width or 18
listings.row_dimensions[1].height = header_height
listings.freeze_panes = "B2"


def check(name, prompt, error=None, **rule):
    """A hint when a cell in this column is selected and, with a rule, an error window for rejected values."""
    letter = letters[name]
    validation = DataValidation(allow_blank=True, showInputMessage=True, promptTitle=name[:32], prompt=prompt[:255],
                                showErrorMessage=bool(error), errorTitle=name[:32] if error else None, error=error, **rule)
    validation.add(f"{letter}2:{letter}{LAST_ROW}")
    listings.add_data_validation(validation)


check("Post status", "Optional. Choose draft, pending, private or publish. Blank creates a draft.",
      "Choose draft, pending, private or publish, or leave the cell blank.", type="list", formula1='"draft,pending,private,publish"')
check("Travel price on image", "Optional. Choose 1 to display the price badge or 0 to hide it. Blank hides the badge.",
      "Choose 1 or 0, or leave the cell blank.", type="list", formula1='"0,1"')
check("Featured image", "Optional. The poster: shown as the Trip flyer, and on the homepage card when Card image is blank. A direct image URL or one picture.")
check("Card image", "Optional. The homepage card picture, about twice as wide as tall. A direct image URL or one picture. Blank shows the poster.")
check("Trip page photo", "Optional. Paste a direct image URL (https://…) or put one picture in this cell. Use a wide landscape photo.")
check("Photo crop", "Optional. Choose top, center or bottom. Blank means center.",
      "Choose top, center or bottom from the list, or leave the cell blank.", type="list", formula1='"top,center,bottom"')
reserve = letters["Reserve button link"]
check("Reserve button link", "Optional. A complete address such as https://example.com/book.",
      "Paste a complete address starting with https:// or http://, or leave the cell blank.", type="custom",
      formula1=f'OR({reserve}2="",LEFT({reserve}2,8)="https://",LEFT({reserve}2,7)="http://")')
for code in LANGUAGE:
    check(f"Price includes {code}", "One item per line (Option+Return in Numbers, Alt+Enter in Excel). Item | price, e.g. Flight Riga – Brussels | 170–220. 335+ means from €335.")
    check(f"Key dates {code}", "One date per line (Option+Return in Numbers, Alt+Enter in Excel). Date time | what happens, e.g. 13.02.2027 17:00 CET | Worldwide ticket sale.")
for name in ("Travel date from", "Travel date to"):
    check(name, "Optional. A date such as 2027-03-12.", "Enter a real date such as 2027-03-12, or leave the cell blank.",
          type="date", operator="greaterThan", formula1="36526")

# Row 2: a filled-in example from an existing trip.
values = {
    "Post title": EXAMPLE["post title"], "Categories": EXAMPLE["categories"],
    "Travel date from": datetime.date.fromisoformat(EXAMPLE["date_from"]), "Travel date to": datetime.date.fromisoformat(EXAMPLE["date_to"]),
    "Travel price": EXAMPLE["price"], "Travel location": EXAMPLE["location"], "Post status": EXAMPLE["status"],
    "Featured image": EXAMPLE["featured image"], "Card image": EXAMPLE["card image"], "Trip page photo": EXAMPLE["trip page photo"], "Photo crop": EXAMPLE["photo crop"],
    "Travel contact email": EXAMPLE["contact_email"] or None, "Travel contact phone": EXAMPLE["contact_phone"] or None,
    "Travel website URL": EXAMPLE["website_url"] or None, "Reserve button link": None,
    "Travel price on image": int(EXAMPLE["price_on_image"] or 0), "Post name": EXAMPLE["post name"],
}
for code in LANGUAGE:
    lang = code.lower()
    values[f"Travel title {code}"] = EXAMPLE[f"title {lang}"]
    values[f"Travel excerpt {code}"] = EXAMPLE[f"excerpt {lang}"]
    values[f"Travel description {code}"] = EXAMPLE[f"description {lang}"]
    values[f"Price includes {code}"] = EXAMPLE[f"price includes {lang}"]
    values[f"Key dates {code}"] = EXAMPLE[f"key dates {lang}"]
example_fill = PatternFill("solid", fgColor="FFF8E1")
for name, value in values.items():
    cell = listings[f"{letters[name]}2"]
    cell.value = value
    cell.fill = example_fill
    cell.alignment = Alignment(vertical="top", wrap_text=True)
    if isinstance(value, datetime.date):
        cell.number_format = "yyyy-mm-dd"
listings.row_dimensions[2].height = 150
note = Comment("Example: the existing Tomorrowland trip, filled in the way a trip should be. Its Post name already exists, so an upload skips this row. Replace it with your own trip or leave it.", "SproTravel")
note.width, note.height = 170, 90
listings["A2"].comment = note

# Instructions: point to the example row and explain the new columns, in the guide's own styles.
guide["A2"] = ("Row 2 on Listings is a filled-in example: the existing Tomorrowland trip. Uploading skips it because its Post name already exists. "
               "Enter one trip per row from row 3, or overwrite the example. Only Post title is required. Leave unknown values blank and use draft for offers awaiting verification.")
section, label, text, example = guide["A18"], guide["A4"], guide["B4"], guide["C4"]
row = guide.max_row + 1


def style_like(cell, source):
    for attr in ("font", "fill", "border", "alignment", "protection"):
        setattr(cell, attr, copy(getattr(source, attr)))


def section_row(title):
    global row
    guide.merge_cells(start_row=row, start_column=1, end_row=row, end_column=3)
    guide.cell(row, 1, title)
    for col in range(1, 4):
        style_like(guide.cell(row, col), section)
    guide.row_dimensions[row].height = guide.row_dimensions[18].height
    row += 1


def field_row(name, what, sample=""):
    global row
    for col, value in enumerate((name, what, sample or None), start=1):
        style_like(guide.cell(row, col, value), (label, text, example)[col - 1])
    guide.row_dimensions[row].height = max(30, 15 * max(len(what) // 70 + 1, len(sample) // 38 + 1, sample.count("\n") + 1) + 6)
    row += 1


section_row("Trip pictures and trip page fields")
field_row("Three pictures", "Featured image is the poster, shown as the Trip flyer. Card image is the picture on the homepage card and in More trips; blank shows the poster. Trip page photo runs across the top of the trip page. Each takes a direct image URL or one picture in the cell.", "")
field_row("Card image", "A landscape picture about twice as wide as it is tall, for example 1200 × 600. Keep text out of it: the card shows the title, dates and price below.",
          "https://images.unsplash.com/photo-1565777645223-b05b83b8bc42?w=1200")
field_row("Trip page photo", "A wide landscape photo across the top of the trip page. Paste a direct image URL or put one picture in the cell. The Featured image stays the poster, shown as the trip flyer.",
          "https://images.unsplash.com/photo-1565777645223-b05b83b8bc42?w=2400")
field_row("Photo crop", "Which part of the photo stays visible when the header crops it. Blank means center.", "top, center or bottom")
field_row("Price includes LV / EN / RU", LINE_PRICE + " Start a new line with Option+Return in Numbers or Alt+Enter in Excel.",
          "Flight Riga – Brussels – Riga | 170–220\n3-day festival ticket | 335+\nBreakfast")
field_row("Key dates LV / EN / RU", LINE_DATES + " Dates may be written 13.02.2027 or 2027-02-13. Lines in different languages are matched by their date, so their order does not matter.",
          "02.12.2026 15:00 CET | Pre-registration opens\n16.07.2027 – 18.07.2027 | Festival weekend")
field_row("Reserve button link", "Where the Reserve button leads. Blank uses the trip’s contact email or phone, then the site-wide contact details.", "https://example.com/book/tomorrowland")
field_row("Checks", "Excel shows a hint when you select a cell and an error window for values with a fixed format. After upload, the import report names the cell and line of anything it could not read, for example a price that is not a number or a date that does not exist.", "")
field_row("Trips imported earlier", "A row whose Post name already exists is skipped. Its Price includes and Key dates are added only if that trip has none yet, so edits made in WordPress are never overwritten.", "")

book.save(TEMPLATE)
print("Updated", TEMPLATE.name, "with", len(order), "columns")
