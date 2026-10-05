#!/usr/bin/env python3
"""
Build assets/data/india-pincodes.json — every Indian pincode → [city, GST state code].

Used by the store checkout pincode lookup (api/mobile/v1/store_checkout.php
?action=pincode). The lab file (assets/data/serviceable-pincodes.json) only
covers Thyrocare areas, so this one covers the whole country.

Source: India Post's All-India Pincode Directory, as packaged in the npm module
`india-pincode-lookup` 1.0.3 (MIT, ~155k post offices, ~19.1k pincodes). That
snapshot is from 2015, so it is corrected for later changes:
  * Telangana (2014): every 50xxxx pincode → Telangana (36), not Andhra Pradesh.
  * Ladakh (2019):    districts Leh and Kargil → Ladakh (38).
  * DNH + Daman & Diu merged (2020) → GST code 26.
Then the lab file (fetched from India Post's API in 2026) is laid on top, so
its ~4.2k pincodes use current district/state names.

"city" = the post office's DISTRICT (same convention as the lab file), taking
the most common district/state among the offices sharing a pincode.

Output (compact, ~450 KB):  { "380001": ["Ahmedabad", "24"], ... }
The state is the GST code; the API turns it into the canonical name with
App\\Services\\Store\\GstStates::name(), so the app can preselect it.

Usage:  python3 fetch_doctor/build_india_pincodes.py
        (downloads the npm tarball; or pass a local pincodes.json path)
"""

import collections
import io
import json
import os
import sys
import tarfile
import urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, 'assets', 'data', 'india-pincodes.json')
LAB = os.path.join(ROOT, 'assets', 'data', 'serviceable-pincodes.json')
TARBALL = 'https://registry.npmjs.org/india-pincode-lookup/-/india-pincode-lookup-1.0.3.tgz'

# Source spellings → GST state code (App\Services\Store\GstStates::STATES).
STATE_CODES = {
    'jammu & kashmir': '01', 'himachal pradesh': '02', 'punjab': '03', 'chandigarh': '04',
    'uttarakhand': '05', 'haryana': '06', 'delhi': '07', 'rajasthan': '08', 'uttar pradesh': '09',
    'bihar': '10', 'sikkim': '11', 'arunachal pradesh': '12', 'nagaland': '13', 'manipur': '14',
    'mizoram': '15', 'tripura': '16', 'meghalaya': '17', 'assam': '18', 'west bengal': '19',
    'jharkhand': '20', 'odisha': '21', 'chattisgarh': '22', 'chhattisgarh': '22', 'madhya pradesh': '23',
    'gujarat': '24', 'dadra & nagar haveli': '26', 'daman & diu': '26',
    'dadra and nagar haveli and daman and diu': '26', 'maharashtra': '27', 'karnataka': '29', 'goa': '30',
    'lakshadweep': '31', 'kerala': '32', 'tamil nadu': '33', 'pondicherry': '34', 'puducherry': '34',
    'andaman & nicobar islands': '35', 'telangana': '36', 'andhra pradesh': '37', 'ladakh': '38',
}


def load_source(argv):
    if len(argv) > 1:
        with open(argv[1], encoding='utf-8') as f:
            return json.load(f)
    print('Downloading', TARBALL)
    raw = urllib.request.urlopen(TARBALL, timeout=120).read()
    with tarfile.open(fileobj=io.BytesIO(raw), mode='r:gz') as tar:
        return json.load(tar.extractfile('package/pincodes.json'))


def code_for(state, district, pin):
    if pin.startswith('50'):
        return '36'                                   # Telangana
    if district.strip().lower() in ('leh', 'leh ladakh', 'kargil'):
        return '38'                                   # Ladakh
    return STATE_CODES.get(state.strip().lower())


def main():
    offices = load_source(sys.argv)
    by_pin = collections.defaultdict(list)
    for o in offices:
        pin = str(o.get('pincode', '')).strip()
        if len(pin) == 6 and pin.isdigit() and pin[0] != '0':
            by_pin[pin].append(o)

    out, unknown = {}, collections.Counter()
    for pin, rows in by_pin.items():
        district = collections.Counter(r['districtName'].strip() for r in rows).most_common(1)[0][0]
        state = collections.Counter(r['stateName'].strip() for r in rows).most_common(1)[0][0]
        code = code_for(state, district, pin)
        if code is None:
            unknown[state] += 1
            continue
        out[pin] = [district, code]

    overlaid = 0
    if os.path.exists(LAB):
        with open(LAB, encoding='utf-8') as f:
            for pin, rec in json.load(f).items():
                code = code_for(rec.get('state', ''), rec.get('city', ''), pin)
                if code and rec.get('city'):
                    out[pin] = [rec['city'].strip(), code]
                    overlaid += 1

    with open(OUT, 'w', encoding='utf-8') as f:
        json.dump(dict(sorted(out.items())), f, ensure_ascii=False, separators=(',', ':'))
    print(f'{len(out)} pincodes → {OUT} ({os.path.getsize(OUT) // 1024} KB); '
          f'{overlaid} from the lab file; unmapped states: {dict(unknown) or "none"}')


if __name__ == '__main__':
    main()
