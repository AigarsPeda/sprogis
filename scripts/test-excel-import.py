#!/usr/bin/env python3
"""Exercise the real import endpoint on sprogis.local. Requires openpyxl, Pillow and numbers-parser."""
import datetime
import html
import io
import json
import re
import subprocess
import tempfile
import uuid
from pathlib import Path
from urllib import parse, request
from zipfile import ZipFile

from numbers_parser import Document
from numbers_parser.cell import BackgroundImage
from openpyxl import Workbook, load_workbook
from openpyxl.drawing.image import Image
from openpyxl.utils.datetime import CALENDAR_MAC_1904
from PIL import Image as PILImage

ROOT = Path(__file__).resolve().parents[1]
LOCAL = "http://sprogis.local"
ADMIN = LOCAL + "/wp-admin/edit.php?post_type=travel_listing&page=spro-excel-import"


def wp(*args):
    return subprocess.check_output([str(ROOT / "scripts/local-wp.sh"), *args], text=True).strip()


def make_book(rows, configure=None):
    book = Workbook()
    sheet = book.active
    sheet.title = "Listings"
    for row in rows:
        sheet.append(row)
    if configure:
        configure(book, sheet)
    stream = io.BytesIO()
    book.save(stream)
    return stream.getvalue()


def main():
    assert wp("option", "get", "home") == LOCAL
    prefix = "qa-import-" + uuid.uuid4().hex[:12] + "-"
    user = int(wp("user", "list", "--role=administrator", "--field=ID").splitlines()[0])
    auth = json.loads(wp("eval", f"""
        $id={user}; $exp=time()+1800; $token=WP_Session_Tokens::get_instance($id)->create($exp);
        echo wp_json_encode([$token,AUTH_COOKIE.'='.wp_generate_auth_cookie($id,$exp,'auth',$token)
            .'; '.LOGGED_IN_COOKIE.'='.wp_generate_auth_cookie($id,$exp,'logged_in',$token)]);
    """))
    opener = request.build_opener(request.ProxyHandler({}))
    opener.addheaders = [("Cookie", auth[1])]
    checked = []

    def title(key):
        return prefix + key

    def upload(label, contents, counts, *messages, extension="xlsx"):
        filename = prefix + label + "." + extension
        boundary = "----" + uuid.uuid4().hex
        body = (f'--{boundary}\r\nContent-Disposition: form-data; name="workbook"; filename="{filename}"'
                '\r\nContent-Type: application/octet-stream\r\n\r\n').encode() + contents + f'\r\n--{boundary}--\r\n'.encode()
        post = request.Request(action, data=body, headers={"Content-Type": "multipart/form-data; boundary=" + boundary})
        with opener.open(post, timeout=60) as response:
            run = int(parse.parse_qs(parse.urlsplit(response.url).query)["run"][0])
            page = html.unescape(response.read().decode())
        notice = page.split('class="notice ', 1)[1].split('</div>', 1)[0]
        expected = f"{counts[0]} created, {counts[1]} skipped, {counts[2]} error"
        for text in (expected, *messages):
            assert text in notice, (label, text, notice)
        checked.append((run, filename, counts, messages))
        print("PASS", label, expected, flush=True)

    try:
        page = opener.open(ADMIN, timeout=30).read().decode()
        action = html.unescape(re.search(r'<form id="spro-import-form"[^>]+action="([^"]+)"', page)[1])
        link = re.search(r'href="([^"]+)" download>Download Excel template</a>', page)[1]
        template = opener.open(html.unescape(link), timeout=30).read()
        empty = load_workbook(io.BytesIO(template))
        assert empty.sheetnames == ["Listings", "Instructions"]
        guide = " ".join(str(c.value) for row in empty["Instructions"] for c in row if c.value)
        assert all(text in guide for text in ["Post title", "Post name", "Featured image", "correct", "YYYY-MM-DD",
                                              "Trip page photo", "Price includes LV / EN / RU", "Key dates LV / EN / RU", "Row 2 on Listings"])
        sheet = empty["Listings"]
        names = [c.value for c in sheet[1]]
        assert names.index("Price includes EN (one per line: item | price)") == names.index("Travel description EN") + 1
        assert names.index("Trip page photo (wide landscape)") == names.index("Featured image") + 1
        assert all(v.showInputMessage and v.prompt for v in sheet.data_validations.dataValidation)
        example_key = sheet.cell(2, names.index("Post name") + 1).value
        assert example_key == "tomorrowland-belgium-2027-weekend-1"
        # The example row is an existing trip, so uploading the template unchanged creates nothing.
        upload("downloaded-template-with-example", template, (0, 1, 0))
        for cell in sheet[2]:
            cell.value = None
        sheet["A2"] = title("template")
        stream = io.BytesIO()
        empty.save(stream)
        upload("downloaded-filled-template", stream.getvalue(), (1, 0, 0))
        for cell in sheet[2]:
            cell.value = None
        stream = io.BytesIO()
        empty.save(stream)
        upload("downloaded-blank-template", stream.getvalue(), (0, 0, 1), "no records")
        headers = ["Post title", "Post name", "Travel date from", "Travel date to", "Post status",
                   "Categories", "Travel contact email", "Travel website URL", "Travel price on image", "Featured image"]
        mixed = [headers, [title("minimal")], [None, title("broken"), "31.02.2027", "12/03/27", "live",
                 "no such category", "bad-address", "example.org", "sometimes", "photo.jpg"],
                 [], [title("normal"), title("normal"), datetime.datetime(2027, 3, 12, 15, 30), "15.03.2027",
                 None, "Sports, ; ", None, None, True]]
        upload("mixed", make_book(mixed), (2, 0, 1), "Row 3", "A3 (Post title)", "C3 (Travel date from)",
               "D3 (Travel date to)", "E3 (Post status)", "F3 (Categories)", "G3 (Travel contact email)",
               "H3 (Travel website url)", "I3 (Travel price on image)", "J3 (Featured image)")
        mixed[2] = [title("repaired"), title("broken"), "2027-03-12", "2027-03-15"]
        upload("corrected", make_book(mixed), (1, 2, 0))
        upload("repeat", make_book(mixed), (0, 3, 0))
        upload("partial-workbook", make_book([headers, mixed[1]]), (0, 1, 0))
        upload("duplicate-key", make_book([headers, mixed[1], [title("different"), title("minimal")]]),
               (0, 2, 0), "repeats Post name", "row 2")
        upload("empty", b"", (0, 0, 1), "0 bytes")
        upload("renamed", b"title,price\nRome,250", (0, 0, 1), "unencrypted .xlsx")
        upload("wrong-format", b"title,price", (0, 0, 1), "require conversion", extension="csv")
        upload("no-table", make_book([["Title", "Price"], ["Rome", 250]]), (0, 0, 1), "Post title")
        upload("headers-only", make_book([headers]), (0, 0, 1), "no records")
        upload("duplicate-header", make_book([["Post title", "Post_title"], [title("no-create")]]),
               (0, 0, 1), "A1", "B1")
        upload("misspelled-header", make_book([["Post title", "Post statues"], [title("no-create"), "private"]]),
               (0, 0, 1), "B1", "Post status")
        upload("extra-column", make_book([["Notes", "  POST\u00a0 TITLE ", "Post name"],
               ["Internal note", title("minimal"), title("minimal")]]), (0, 1, 0), "Column A", "ignored")
        upload("unnamed-column", make_book([["Post title", None], [title("no-create"), "lost price"]]),
               (0, 0, 1), "B2 has data")
        upload("blank-header-row", make_book([[], headers, mixed[1]]), (0, 1, 0))
        upload("two-listing-sheets", make_book([headers, [title("no-create")]],
               lambda b, s: b.copy_worksheet(s)), (0, 0, 1), "Both", "export the sheets separately")
        upload("merged", make_book([headers, mixed[1], mixed[2]], lambda b, s: s.merge_cells("A2:A3")),
               (0, 0, 1), "Unmerge cells A2:A3")
        upload("formulas", make_book([["Post title", "Travel price"], [title("formula"), "=1+1"],
               [title("error"), "#DIV/0!"]]), (0, 0, 2), "B2", "Recalculate", "B3", "#DIV/0!")
        upload("reversed-dates", make_book([headers, [title("range"), None, "2027-03-15", "2027-03-12"]]),
               (0, 0, 1), "D2 (Travel date to)")
        upload("text-year", make_book([headers, [title("year"), None, "2027"]]), (0, 0, 1), "C2")
        upload("excel-1900", make_book([headers, [title("excel-bug"), None, 60]]), (0, 0, 1), "C2", "calendar")
        upload("excel-1904", make_book([headers, [title("mac"), title("mac"), datetime.datetime(2028, 2, 29, 12)]],
               lambda b, s: setattr(b, "epoch", CALENDAR_MAC_1904)), (1, 0, 0))
        prose = "Pirmā.\\n\\nOtra.\\r\\nTrešā.\nNoslēgums."
        formatted = "Pirmā.\n\nOtra.\nTrešā.\nNoslēgums."
        upload("line-breaks", make_book([["Post title", "Travel description LV", "Travel excerpt RU"],
               [title("lines"), prose, prose]]), (1, 0, 0))
        fields = wp("eval", f"""
            $id=spro_import_existing('{title("lines")}','_spro_import_key');
            echo wp_json_encode([get_post_meta($id,'_travel_description_lv',true),get_post_meta($id,'_travel_excerpt_ru',true)]);
        """)
        assert json.loads(fields) == [formatted, formatted]
        long_price = "≈640–915 €/personai (aplēse; biļete €85 sākumcena)"
        upload("long-price", make_book([["Post title", "Travel price"], [title("price"), long_price]]), (1, 0, 0))
        assert wp("eval", f"""
            $id=spro_import_existing('{title("price")}','_spro_import_key');
            echo spro_price($id);
        """) == long_price

        # Trip page details: photo, crop and reserve link columns, and one-per-line Price includes and Key dates cells.
        trip = title("details")

        def photo(book, sheet):
            stream = io.BytesIO()
            PILImage.new("RGB", (40, 20), "blue").save(stream, "PNG")
            stream.seek(0)
            sheet.add_image(Image(stream), "C2")

        detail_headers = ["Post title", "Post name", "Trip page photo (wide landscape)", "Photo crop (top / center / bottom)", "Reserve button link",
                          "Price includes LV (one per line: item | price)", "Price includes EN (one per line: item | price)",
                          "Key dates LV (one per line: date time | what happens)", "Key dates EN (one per line: date time | what happens)"]
        detail_row = [trip, trip, None, "Top", "https://example.org/book",
                      "Lidojums | 170–220,50\nBrokastis", "Flight | €170 - 220.50\nBreakfast\n",
                      "16.07.2027 – 18.07.2027 | Festivāls\n2.12.2026 15:00 cet | Reģistrācija",
                      "2026-12-02 15:00 CET | Pre-registration opens\n13.02.2027 17:00 | Worldwide sale\n16.07.2027-18.07.2027 | Festival weekend"]
        upload("details", make_book([detail_headers, detail_row], photo), (1, 0, 0))
        stored = json.loads(wp("eval", f"""
            $id=spro_import_existing('{trip}','_spro_import_key');
            echo wp_json_encode([spro_trip_included($id), spro_trip_key_dates($id), (bool)spro_trip_photo($id),
                get_post_meta($id,'_travel_photo_position',true), get_post_meta($id,'_travel_reserve_url',true)]);
        """))
        assert [(r["item"]["lv"], r["item"]["en"], r["from"], r["to"]) for r in stored[0]] == [
            ("Lidojums", "Flight", 170, 220.5), ("Brokastis", "Breakfast", None, None)], stored[0]
        assert [(r["date"], r["to"], r["time"], r["label"]["lv"], r["label"]["en"]) for r in stored[1]] == [
            ("2026-12-02", "", "15:00 CET", "Reģistrācija", "Pre-registration opens"), ("2027-02-13", "", "17:00", "", "Worldwide sale"),
            ("2027-07-16", "2027-07-18", "", "Festivāls", "Festival weekend")], stored[1]
        assert stored[2:] == [True, "top", "https://example.org/book"]
        # A repeat upload never overwrites details already stored.
        changed = [*detail_row[:5], "Cits | 1", "Other | 1", "", ""]
        upload("details-repeat", make_book([detail_headers, changed]), (0, 1, 0))
        assert json.loads(wp("eval", f"echo wp_json_encode(count(spro_trip_included(spro_import_existing('{trip}','_spro_import_key'))));")) == 2
        # An existing trip without details gets them from a later upload.
        upload("details-later-base", make_book([["Post title", "Post name"], [title("later"), title("later")]]), (1, 0, 0))
        upload("details-later", make_book([["Post title", "Post name", "Key dates EN"], [title("later"), title("later"), "01.03.2027 | Sale"]]),
               (0, 1, 0), "added to 1 existing trip")
        upload("details-errors", make_book([["Post title", "Price includes LV", "Price includes EN", "Key dates EN (one per line)"],
               [title("details-bad"), "A | 1\nB", "A | lots", "31.02.2027 | Sale\nTomorrow | Party\n01.03.2027"]]),
               (0, 0, 1), "C2 (Price includes en)", "same number of lines", "D2 (Key dates en)", "Line 1: the date does not exist",
               "Line 2: start with a date", "Line 3: write the date, then |")

        upload("hyperlink-label", make_book([["Post title", "Travel website URL"], [title("link"), "Trip website"]],
               lambda b, s: setattr(s["B2"], "hyperlink", "https://example.org/trip")), (1, 0, 0))
        lock_test = """global $wpdb; $key='spro-import-'.md5(DB_NAME.':'.$wpdb->posts);
            echo (int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $key)).PHP_EOL;
            flush(); fgets(STDIN); $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key));"""
        with subprocess.Popen([str(ROOT / "scripts/local-wp.sh"), "eval", lock_test], stdin=subprocess.PIPE,
                              stdout=subprocess.PIPE, text=True) as holder:
            try:
                assert holder.stdout.readline().strip() == "1", "Local importer is busy"
                upload("overlapping-upload", make_book(mixed), (0, 0, 1), "Another import is running")
            finally:
                holder.communicate(input="release\n", timeout=20)
        upload("retry-after-busy", make_book(mixed), (0, 3, 0))

        def pictures(book, sheet):
            for i in range(3):
                stream = io.BytesIO()
                PILImage.new("RGB", (32, 20), "green").save(stream, "PNG")
                stream.seek(0)
                sheet.add_image(Image(stream), "B2" if i < 2 else "B3")

        damaged = io.BytesIO()
        image_rows = [["Post title", "Featured image"], [title("crowded")], [title("photo")]]
        images = make_book(image_rows, pictures)
        with ZipFile(io.BytesIO(images)) as original, ZipFile(damaged, "w") as output:
            for part in original.infolist():
                output.writestr(part, b"Unreadable photograph" if part.filename.startswith("xl/media/") else original.read(part))
        upload("damaged-photo", damaged.getvalue(), (0, 0, 2), "B3 (Featured image)", "JPEG")
        upload("pictures", images,
               (1, 0, 1), "B2 (Featured image)", "one embedded picture")
        upload("fake-numbers", b"invalid", (0, 0, 1), "Numbers file", extension="numbers")
        # A native cell fill must survive a category retry without a second upload.
        with tempfile.TemporaryDirectory() as folder:
            native = Document(sheet_name="Listings", num_rows=4, num_cols=4)
            table = native.sheets[0].tables[0]
            festival = title("festival")
            sample = [
                ["Post title", "Post name", "Categories", "Featured image"],
                [title("native"), title("native"), festival + ", alternate-sport"],
                [title("minimal"), title("minimal")],
                [title("bad-native"), title("bad-native"), festival],
            ]
            for r, values in enumerate(sample):
                for c, value in enumerate(values):
                    table.write(r, c, value)
            table.write(0, 5, "Travel description EN")
            table.write(1, 5, prose)
            table.write(3, 4, "invalid")
            table.write(0, 4, "Travel date from")
            picture = io.BytesIO()
            PILImage.new("RGB", (30, 24), "green").save(picture, "PNG")
            style = native.add_style(name="Test image", bg_image=BackgroundImage(picture.getvalue(), "photo.png"))
            table.set_cell_style("D2", style)
            path = Path(folder) / "sample.numbers"
            native.save(path)
            contents = path.read_bytes()
        # A Numbers row with one-per-line Key dates and a hint after the header name.
        with tempfile.TemporaryDirectory() as folder:
            native_details = Document(sheet_name="Listings", num_rows=2, num_cols=3)
            grid = native_details.sheets[0].tables[0]
            for c, value in enumerate(["Post title", "Post name", "Key dates EN (one per line: date time | what happens)"]):
                grid.write(0, c, value)
            for c, value in enumerate([title("native-dates"), title("native-dates"), "24.07.2027 19:30 | Concert\n13.02.2027 | Sale"]):
                grid.write(1, c, value)
            path = Path(folder) / "dates.numbers"
            native_details.save(path)
            upload("native-details", path.read_bytes(), (1, 0, 0), extension="numbers")
        native_dates = json.loads(wp("eval", f"""
            echo wp_json_encode(spro_trip_key_dates(spro_import_existing('{title("native-dates")}','_spro_import_key')));
        """))
        assert [(r["date"], r["time"], r["label"]["en"]) for r in native_dates] == [("2027-02-13", "", "Sale"), ("2027-07-24", "19:30", "Concert")]
        upload("native", contents, (0, 1, 2), "Unknown category", extension="numbers")
        run, name, _, _ = checked[-1]
        def resolve(choices):
            page = opener.open(ADMIN + "&run=" + str(run)).read().decode()
            form = re.search(r'<form [^>]*action="([^"]+)"[^>]*>(?:(?!</form>).)*?value="spro_resolve_categories"(?:(?!</form>).)*?</form>', page, re.S)
            nonce = re.search(r'name="_wpnonce" value="([^"]+)"', form[0])[1]
            body = {"action": "spro_resolve_categories", "run": run, "_wpnonce": nonce, **choices}
            return opener.open(form[1], parse.urlencode(body).encode()).read().decode()

        sports = wp("term", "get", "listing_category", "sports", "--by=slug", "--field=term_id")
        result_page = resolve({"category[0]": "create", "category[1]": sports})
        assert "1 created, 1 skipped, 1 error" in result_page
        checked[-1] = (run, name, (1, 1, 1), ["E4", "date"])
        native_meta = json.loads(wp("eval", f"""
            $id=spro_import_existing('{title("native")}','_spro_import_key');
            echo wp_json_encode([get_post_status($id), has_term('sports','listing_category',$id),
                has_term('{festival}','listing_category',$id), (bool)get_post_thumbnail_id($id),
                metadata_exists('post',{run},'_spro_pending_categories'), get_post_meta($id,'_travel_description_en',true)]);
        """))
        assert native_meta == ["draft", True, True, True, False, formatted]
        upload("native-repeat", contents, (0, 2, 1), "E4", extension="numbers")
        history = opener.open(ADMIN, timeout=30).read().decode()
        assert "View errors" in history and "E4" in history
        run_ids = json.dumps([x[0] for x in checked])
        persisted = json.loads(wp("eval", f"""
            $out=[];foreach ({run_ids} as $id)
                $out[$id]=get_post_meta($id,'_spro_import_report',true);echo wp_json_encode($out);
        """))
        for run, _, (created, skipped, errors), messages in checked:
            report = persisted[str(run)]
            assert (report["created"], report["skipped"], len(report["errors"])) == (created, skipped, errors)
            details = " ".join(report["errors"] + report["warnings"]) + f" added to {report.get('details', 0)} existing trip"
            assert all(text in details for text in messages)
        result = wp("eval", f"""
            wp_set_current_user({user});
            $id=spro_import_existing('{title("normal")}','_spro_import_key');
            $minimal=spro_import_existing('{title("minimal")}','_spro_import_key');
            $mac=spro_import_existing('{title("mac")}','_spro_import_key');
            $photo=spro_import_existing('{title("photo")}','_spro_import_key');
            $link=spro_import_existing('{title("link")}','_spro_import_key');
            echo wp_json_encode([get_post_status($minimal),get_post_meta($id,'_travel_date_from',true),
                get_post_meta($id,'_travel_date_to',true),get_post_meta($mac,'_travel_date_from',true),
                (bool)get_post_thumbnail_id($photo),current_user_can('delete_post',$minimal),get_post_meta($link,'_travel_website_url',true)]);
        """)
        assert json.loads(result) == ["draft", "2027-03-12", "2027-03-15", "2028-02-29", True, False, "https://example.org/trip"]
        print(f"Verified immediate results and persistent reports for {len(checked)} uploads.")
    finally:
        # Clean only this invocation's tagged files, listings, attachments and local login.
        wp("eval", f"""
            global $wpdb;
            if (home_url()!=='{LOCAL}') throw new Exception('Unexpected site');
            $ids=$wpdb->get_col($wpdb->prepare("SELECT ID FROM {{$wpdb->posts}} WHERE post_type IN ('spro_import_run','travel_listing') AND post_title LIKE %s",$wpdb->esc_like('{prefix}').'%'));
            foreach($ids as $id){{
                foreach(get_children(['post_parent'=>$id,'post_type'=>'attachment','post_status'=>'any','fields'=>'ids']) as $attachment)
                    wp_delete_attachment($attachment,true);
                delete_post_meta($id,'_spro_import_key');wp_delete_post($id,true);
            }}
            $term = get_term_by('slug','{title("festival")}','listing_category');
            if($term) wp_delete_term($term->term_id,'listing_category');
            WP_Session_Tokens::get_instance({user})->destroy('{auth[0]}');
        """)


if __name__ == "__main__":
    main()
