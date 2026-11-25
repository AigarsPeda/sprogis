#!/usr/bin/env python3
"""Exercise the real import endpoint on sprogis.local. Requires openpyxl and Pillow."""
import datetime
import html
import io
import json
import re
import subprocess
import uuid
from pathlib import Path
from urllib import parse, request
from zipfile import ZipFile

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
        assert all(text in guide for text in ["Post title", "Post name", "Featured image", "correct", "YYYY-MM-DD"])
        assert len(empty["Listings"].data_validations.dataValidation) == 2
        upload("downloaded-blank-template", template, (0, 0, 1), "no records")
        empty["Listings"]["A2"] = title("template")
        stream = io.BytesIO()
        empty.save(stream)
        upload("downloaded-filled-template", stream.getvalue(), (1, 0, 0))
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
        upload("overlong-price", make_book([["Post title", "Travel price"], [title("price"), "ab" * 22]]),
               (0, 0, 1), "40 characters")

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
        history = opener.open(ADMIN, timeout=30).read().decode()
        assert "View errors" in history and "one embedded picture" in history
        run_ids = json.dumps([x[0] for x in checked])
        persisted = json.loads(wp("eval", f"""
            $out=[];foreach ({run_ids} as $id)
                $out[$id]=get_post_meta($id,'_spro_import_report',true);echo wp_json_encode($out);
        """))
        for run, _, (created, skipped, errors), messages in checked:
            report = persisted[str(run)]
            assert (report["created"], report["skipped"], len(report["errors"])) == (created, skipped, errors)
            details = " ".join(report["errors"] + report["warnings"])
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
            WP_Session_Tokens::get_instance({user})->destroy('{auth[0]}');
        """)


if __name__ == "__main__":
    main()
