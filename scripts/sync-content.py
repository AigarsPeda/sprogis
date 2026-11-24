#!/usr/bin/env python3
"""Pull WordPress content into Local, or preview and push one edited listing."""
import argparse
import datetime
import fcntl
import json
import os
from pathlib import Path
import shlex
import subprocess
import tempfile

SCRIPTS = Path(__file__).resolve().parent
HELPER = SCRIPTS / "lib/travel-content.php"
LOCAL_WP = str(SCRIPTS / "local-wp.sh")
HOST = os.getenv("REMOTE_HOST", "root@46.101.233.116")
REMOTE_PATH = os.getenv("REMOTE_WP_PATH", "/var/www/sprotravel")
SSH = ["ssh", "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes", "-o", "ConnectTimeout=10"]
if os.getenv("SSH_KEY"):
    SSH.extend(["-i", os.environ["SSH_KEY"]])
STATE = Path.home() / "Local Sites/sprogis/sync-backups"
BASELINE = STATE / "listing-baseline.json"


def run(command, payload=None):
    return subprocess.run(command, input=payload, stdout=subprocess.PIPE, check=True).stdout


def remote(action, data=None):
    code = ("define('DISABLE_WP_CRON', true); require 'wp-load.php'; "
            "$args=array_slice($argv,1);\n" + HELPER.read_text().removeprefix("<?php"))
    cmd = "cd " + shlex.quote(REMOTE_PATH) + " && " + shlex.join(["php", "-d", "display_errors=stderr", "-r", code, action])
    return json.loads(run([*SSH, "--", HOST, cmd], json.dumps(data).encode() if data else None))


def local(action, data=None):
    return json.loads(run([LOCAL_WP, "eval-file", str(HELPER), action],
                          json.dumps(data).encode() if data is not None else None))


def save_baseline(data):
    temp = BASELINE.with_suffix(".tmp")
    temp.write_text(json.dumps(data, ensure_ascii=False))
    temp.replace(BASELINE)


def backup_dir(prefix):
    stamp = datetime.datetime.now().strftime("%Y%m%d-%H%M%S")
    return Path(tempfile.mkdtemp(prefix=f"{prefix}-{stamp}-", dir=STATE))


def pull(apply):
    data = remote("export")
    posts = data["tables"]["posts"]
    for kind in ("travel_listing", "page", "post", "attachment"):
        count = sum(p['post_type'] == kind and p['post_status'] != 'auto-draft' for p in posts)
        print(f"{kind}: {count}")
    if not apply:
        print("Preview only. Use pull --apply to replace Local content and uploads.")
        return

    uploads = Path(run([LOCAL_WP, "eval", 'echo wp_get_upload_dir()["basedir"];']).decode())
    source = Path(data["uploads"])
    if source != Path(REMOTE_PATH) / "wp-content/uploads":
        raise ValueError(f"Unexpected remote uploads directory: {source}")
    backup = backup_dir("before-pull")
    print(f"Local backup: {backup}", flush=True)
    run([LOCAL_WP, "db", "export", str(backup / "local.sql"), "--add-drop-table", "--quiet"])
    subprocess.run(["gzip", str(backup / "local.sql")], check=True)
    with tempfile.TemporaryDirectory(dir=STATE) as temp:
        staged = Path(temp) / "uploads"
        staged.mkdir()
        subprocess.run(["rsync", "-rt", "-e", shlex.join(SSH), f"{HOST}:{source}/", str(staged) + "/"], check=True)
        for item in data["tables"]["postmeta"]:
            if item["meta_key"] == "_wp_attached_file":
                attachment = (staged / item["meta_value"]).resolve()
                if staged not in attachment.parents or not attachment.is_file():
                    raise ValueError(f"Missing source attachment: {item['meta_value']}")
        uploads.rename(backup / "uploads")
        try:
            staged.rename(uploads)
            local("import", data)
        except BaseException:
            if uploads.exists():
                uploads.rename(Path(temp) / "failed-uploads")
            (backup / "uploads").rename(uploads)
            raise

    baseline = {"remote": data["listings"], "local": local("listings")}
    save_baseline(baseline)
    print("Local content and uploads imported.")


def push(listing, apply):
    if not BASELINE.exists():
        raise ValueError("Pull first to establish the live and local versions.")
    baseline = json.loads(BASELINE.read_text())
    current = local("listings").get(listing)
    if not current or listing not in baseline["local"] or listing not in baseline["remote"]:
        raise ValueError("Choose the ID of a listing from the last pull.")
    original = baseline["local"][listing]
    fields = {k: current.get(k) for k in sorted(original.keys() | current.keys())
              if original.get(k) != current.get(k)}
    if not fields:
        print("No local changes for this listing.")
        return
    # Use the live site's canonical HTTPS origin in new links.
    for key, value in fields.items():
        if isinstance(value, str):
            fields[key] = value.replace("http://sprogis.local", "https://sprotravel.com")
    before = baseline["remote"][listing]
    change = {"id": listing, "before": before, "fields": fields}
    remote("preview", change)
    print(f"Listing {listing} ({current['post_title']}) changed fields: {', '.join(fields)}")
    if not apply:
        print("Preview only. Use push ID --apply to publish these changes.")
        return
    backup = backup_dir("before-push")
    (backup / "listing.json").write_text(json.dumps(change, ensure_ascii=False))
    remote("push", change)
    before.update(fields)
    baseline["local"][listing] = current
    save_baseline(baseline)
    print(f"Listing updated. Previous field values: {backup / 'listing.json'}")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest="command", required=True)
    for name in ("pull", "push"):
        cmd = commands.add_parser(name)
        if name == "push":
            cmd.add_argument("listing", metavar="ID")
        cmd.add_argument("--apply", action="store_true")
    args = parser.parse_args()
    os.umask(0o077)
    STATE.mkdir(parents=True, exist_ok=True)
    if run([LOCAL_WP, "option", "get", "home"]).decode().strip() != "http://sprogis.local":
        raise ValueError("Expected http://sprogis.local.")
    with (STATE / ".sync.lock").open("a") as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        if args.command == "pull":
            pull(args.apply)
        else:
            push(args.listing, args.apply)


if __name__ == "__main__":
    try:
        main()
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        raise SystemExit(str(error))
