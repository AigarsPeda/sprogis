"""Adapt a single-file Numbers listing table for the shared WordPress workbook reader."""
import datetime
import io
import re
from pathlib import Path
import resource
import signal
import sys
import tempfile
from zipfile import BadZipFile, ZipFile

from numbers_parser import Document
from numbers_parser.cell import ErrorCell
from xlsxwriter import Workbook
from xlsxwriter.exceptions import UndefinedImageSize, UnsupportedImageFormat
from xlsxwriter.utility import xl_rowcol_to_cell


class WorkbookError(ValueError):
    pass


def header(value):
    text = " ".join(str(value or "").replace("_", " ").replace("\ufeff", "").lower().split())
    # A hint in brackets after the name is for people only, as in the PHP reader.
    return re.sub(r"\s*\([^()]*\)$", "", text).strip()


def listing_table(document):
    selected = None
    for sheet in document.sheets:
        for table in sheet.tables:
            if table.num_rows * table.num_cols > 100_000:
                raise WorkbookError("This Numbers table is too large. Split it into smaller files.")
            for row in table.rows():
                headers = [header(cell.value) for cell in row]
                if not any(headers):
                    continue
                if "post title" in headers:
                    if selected:
                        raise WorkbookError("Two Numbers tables contain Post title. Keep one listing table per upload.")
                    selected = table, headers
                break
    if selected is None:
        raise WorkbookError("No listing table found. Put Post title in the first nonempty row of a Numbers table.")
    return selected


def write_cell(sheet, cell, field):
    row, col, value = cell.row, cell.col, cell.value
    address = xl_rowcol_to_cell(row, col)
    if field in ("featured image", "trip page photo"):
        picture = cell.style.bg_image
        if picture:
            try:
                sheet.insert_image(row, col, "picture", {"image_data": io.BytesIO(picture.data)})
            except (ValueError, TypeError, OSError, UndefinedImageSize, UnsupportedImageFormat) as exc:
                raise WorkbookError(f"{address}: use a JPEG or PNG cell image fill, or a direct image URL.") from exc
    links = getattr(cell, "hyperlinks", None)
    if links and field in ("travel website url", "featured image", "trip page photo", "reserve button link"):
        value = links[0][1]
    if isinstance(cell, ErrorCell) or (cell.is_formula and value is None):
        # Feed the cell error into the same row-level reporting used by Excel.
        sheet.write_formula(row, col, "=NA()", None, "#VALUE!")
    elif isinstance(value, datetime.datetime):
        sheet.write_string(row, col, value.date().isoformat())
    elif isinstance(value, bool):
        sheet.write_boolean(row, col, value)
    elif value is not None:
        if isinstance(value, (float, int)):
            value = cell.formatted_value
        if sheet.write_string(row, col, str(value)) < 0:
            raise WorkbookError(f"{address}: shorten the cell text to 32,767 characters.")


def convert(source, output):
    with ZipFile(source) as archive:
        entries = archive.infolist()
        if len(entries) > 4096 or sum(e.file_size for e in entries) > 96 * 1024**2 or any(e.file_size > 16 * 1024**2 for e in entries):
            raise WorkbookError("This Numbers file is too large to process. Split it into smaller files.")
        if any(e.filename.lower().endswith(".zip") for e in entries):
            raise WorkbookError("Save this workbook in Numbers as a current Single File document and upload it again.")
        if "Index/Document.iwa" not in archive.namelist():
            raise WorkbookError("Save an unencrypted Numbers document as a Single File, then upload it again.")
    # PHP upload paths have no extension. The Numbers reader requires its native suffix.
    with tempfile.TemporaryDirectory() as folder:
        path = Path(folder, "upload.numbers")
        path.symlink_to(Path(source).resolve())
        document = Document(path)
        table, headers = listing_table(document)
        if table.merge_ranges:
            raise WorkbookError(f"Unmerge {table.merge_ranges[0]} in the Numbers listing table and upload again.")
        with Workbook(output, {"in_memory": True}) as book:
            sheet = book.add_worksheet("Listings")
            for row in table.rows():
                for cell in row:
                    write_cell(sheet, cell, headers[cell.col])


if __name__ == "__main__":
    signal.alarm(25)
    if sys.platform.startswith("linux"):
        resource.setrlimit(resource.RLIMIT_AS, (320 * 1024**2, 320 * 1024**2))
    signal.alarm(25)
    try:
        convert(sys.argv[1], sys.argv[2])
    except WorkbookError as exc:
        print(str(exc))
        sys.exit(1)
    except (OSError, BadZipFile, ValueError, KeyError, IndexError, RuntimeError, MemoryError):
        print("Could not read this Numbers file. Open it in Numbers, remove any password, and save a fresh Single File copy.")
        sys.exit(1)
