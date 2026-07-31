#!/usr/bin/env python3
"""
Enrich serviceable-pincodes.csv -> serviceable-pincodes.json

Reads the bare pincode list (one 6-digit pincode per line) and looks each one
up against the public India Post postal API to attach the official District
(used as "city") and State. Output is keyed by pincode for O(1) runtime lookup:

    { "380001": { "city": "Ahmedabad", "state": "Gujarat" }, ... }

Re-runnable: caches into the JSON, so a re-run only fetches missing/failed pins.
Mirrors fetch_doctor/enrich_pincodes.php (the PHP version is the server-side
equivalent for regenerating on the box).

Usage:  python3 fetch_doctor/enrich_pincodes.py
"""
import csv
import json
import os
import re
import sys
import time
import urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
CSV_PATH = os.path.join(HERE, "..", "document", "serviceable-pincodes.csv")
JSON_PATH = os.path.join(HERE, "..", "assets", "data", "serviceable-pincodes.json")
API_TMPL = "https://api.postalpincode.in/pincode/{}"
SLEEP = 0.35        # gentler pacing so the free API doesn't throttle the burst
RETRIES = 3         # transient failures (throttling/timeouts) get retried
RETRY_WAIT = 2.0    # backoff between retries, seconds
SAVE_EVERY = 50
PIN_RE = re.compile(r"^[1-9][0-9]{5}$")


def load_pincodes():
    pins = []
    seen = set()
    with open(CSV_PATH, newline="") as f:
        for row in f:
            pin = re.sub(r"\D", "", row.strip())
            if PIN_RE.match(pin) and pin not in seen:
                seen.add(pin)
                pins.append(pin)
    return pins


def load_existing():
    if os.path.isfile(JSON_PATH):
        try:
            with open(JSON_PATH) as f:
                data = json.load(f)
            if isinstance(data, dict):
                return data
        except (ValueError, OSError):
            pass
    return {}


def save_json(data):
    ordered = {k: data[k] for k in sorted(data)}
    with open(JSON_PATH, "w") as f:
        json.dump(ordered, f, ensure_ascii=False, indent=2)


def lookup_once(pin):
    """Single attempt. Returns:
        {"city":..,"state":..}  on success,
        "notfound"              when the API definitively has no record,
        None                    on a transient failure (timeout/non-200/throttle).
    """
    req = urllib.request.Request(
        API_TMPL.format(pin),
        headers={"User-Agent": "eClinicPro-pincode-enrich/1.0"},
    )
    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            if resp.status != 200:
                return None
            body = resp.read().decode("utf-8")
    except Exception:
        return None
    try:
        arr = json.loads(body)
        rec = arr[0]
    except (ValueError, IndexError, KeyError):
        return None
    status = rec.get("Status")
    offices = rec.get("PostOffice") or []
    if status == "Success" and offices:
        office = offices[0]
        city = (office.get("District") or "").strip()
        state = (office.get("State") or "").strip()
        if city and state:
            return {"city": city, "state": state}
        return None
    # "No records found" — a real, permanent negative for this pincode.
    if status == "Error":
        return "notfound"
    return None


def lookup(pin):
    """Retry transient failures with backoff; pass through definitive results."""
    for attempt in range(RETRIES):
        res = lookup_once(pin)
        if res is not None:          # success dict or "notfound"
            return res
        if attempt < RETRIES - 1:
            time.sleep(RETRY_WAIT)
    return None                      # still transient after retries


NOTFOUND_PATH = os.path.join(HERE, "..", "document", "serviceable-pincodes-notfound.txt")


def load_notfound():
    """Pincodes India Post has no record for — skipped on re-runs (permanent)."""
    s = set()
    if os.path.isfile(NOTFOUND_PATH):
        with open(NOTFOUND_PATH) as f:
            for line in f:
                p = line.strip()
                if p:
                    s.add(p)
    return s


def save_notfound(s):
    with open(NOTFOUND_PATH, "w") as f:
        f.write("\n".join(sorted(s)) + ("\n" if s else ""))


def main():
    pins = load_pincodes()
    out = load_existing()
    notfound = load_notfound()
    total = len(pins)
    fetched = 0
    new_notfound = 0
    failed = []
    sys.stderr.write(f"Enriching {total} pincodes ({len(out)} cached, {len(notfound)} known-notfound)...\n")
    sys.stderr.flush()

    for done, pin in enumerate(pins, 1):
        if pin in out and out[pin].get("city") and out[pin].get("state"):
            continue
        if pin in notfound:
            continue  # permanent negative — don't re-query
        res = lookup(pin)
        if res is None:
            failed.append(pin)          # transient — retry on next run
            time.sleep(SLEEP)
            continue
        if res == "notfound":
            notfound.add(pin)
            new_notfound += 1
            time.sleep(SLEEP)
            continue
        out[pin] = res
        fetched += 1
        time.sleep(SLEEP)
        if fetched % SAVE_EVERY == 0:
            save_json(out)
            save_notfound(notfound)
            sys.stderr.write(f"  [{done}/{total}] fetched={fetched} notfound={new_notfound} failed={len(failed)}\n")
            sys.stderr.flush()

    save_json(out)
    save_notfound(notfound)
    sys.stderr.write(
        f"\nDone. total={total}, serviceable-in-json={len(out)}, "
        f"newly-fetched={fetched}, notfound={len(notfound)}, transient-failed={len(failed)}\n"
    )
    if failed:
        sys.stderr.write("Transient failures (re-run to retry): " + ",".join(failed[:40])
                         + ("…" if len(failed) > 40 else "") + "\n")


if __name__ == "__main__":
    main()
