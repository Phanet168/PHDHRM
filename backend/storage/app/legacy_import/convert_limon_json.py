# -*- coding: utf-8 -*-
"""
Convert legacy Khmer Limon text from exported JSON files into Khmer Unicode.

Default behavior is SAFE mode:
- Only attempts conversion on Khmer-likely fields.
- Converts only values that are not already Khmer Unicode and contain extended Latin chars.

Usage:
  py -2.7-32 storage\\app\\legacy_import\\convert_limon_json.py
  py -2.7-32 storage\\app\\legacy_import\\convert_limon_json.py --apply
  py -2.7-32 storage\\app\\legacy_import\\convert_limon_json.py --apply --aggressive
"""

import io
import os
import re
import sys
import json
import datetime

# Load KhmerConverter modules (legacy -> unicode)
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
LOCAL_MODULE_DIR = os.path.join(BASE_DIR, 'storage', 'app', 'legacy_import', 'khmer_converter_modules')
TMP_MODULE_DIR = os.path.join(BASE_DIR, 'storage', 'tmp', 'KhmerConverter', 'modules')
MODULE_DIR = LOCAL_MODULE_DIR if os.path.isdir(LOCAL_MODULE_DIR) else TMP_MODULE_DIR
if not os.path.isdir(MODULE_DIR):
    sys.stderr.write('KhmerConverter modules not found. Checked:\n  - %s\n  - %s\n' % (LOCAL_MODULE_DIR, TMP_MODULE_DIR))
    sys.exit(2)

sys.path.insert(0, MODULE_DIR)
os.chdir(MODULE_DIR)

import FontDataXML
import unicodeProcess
import unicodeReorder


KHMER_RE = re.compile(u'[\u1780-\u17FF]')
EXTENDED_LATIN_RE = re.compile(u'[\u0080-\u00FF]')

FILES_TO_SCAN = [
    'workplace_old.json',
    'place_type.json',
    'province.json',
    'op_district.json',
    'skill.json',
    'position.json',
    'pay_level.json',
    'ssl_type.json',
    'ssl_values.json',
    'staff.json',
    'work_history.json',
    'status_history.json',
    'work_status.json',
    'marital_status.json',
    'tbl_cadre.json',
]

# Khmer-expected fields from legacy DB exports.
KHMER_FIELD_EXACT = set([
    'WorkPlaceK', 'SkillK', 'ShortCutK', 'PositionK', 'PayLevelK', 'sslNameK',
    'NameK', 'PBDetail', 'PADetail', 'OtherInfo', 'EducationLevel',
    'WorkStatusK', 'ProvinceK', 'OpDistrictK', 'PlaceTypeK', 'MaritalStatusK', 'CadreK',
])


def has_khmer_unicode(text):
    return KHMER_RE.search(text) is not None


def has_extended_latin(text):
    return EXTENDED_LATIN_RE.search(text) is not None


def is_khmer_field(key):
    if key in KHMER_FIELD_EXACT:
        return True
    key_lower = key.lower()
    if key_lower.endswith('k'):
        return True
    if '_km' in key_lower:
        return True
    return False


def convert_limon(text, legacy_data):
    try:
        legacy_bytes = text.encode('cp1252')
    except Exception:
        return text, False, 'not_cp1252'

    try:
        converted = unicodeReorder.reorder(unicodeProcess.process(legacy_bytes, legacy_data))
    except Exception:
        return text, False, 'convert_error'

    if converted == text:
        return text, False, 'no_change'

    return converted, True, 'converted'


def load_json(path):
    with io.open(path, 'r', encoding='utf-8-sig') as f:
        return json.loads(f.read())


def save_json(path, data):
    with io.open(path, 'w', encoding='utf-8') as f:
        payload = json.dumps(data, ensure_ascii=False, indent=2, sort_keys=False)
        f.write(payload)


def main():
    apply_changes = '--apply' in sys.argv
    aggressive = '--aggressive' in sys.argv

    source_dir = os.path.join(BASE_DIR, 'storage', 'app', 'legacy_import')
    target_dir = os.path.join(source_dir, 'unicode')
    if not os.path.isdir(target_dir):
        os.makedirs(target_dir)

    fd = FontDataXML.FontData()
    legacy_data = fd.legacyData('limon')

    files_scanned = 0
    fields_scanned = 0
    fields_changed = 0
    samples = []

    for filename in FILES_TO_SCAN:
        src_path = os.path.join(source_dir, filename)
        if not os.path.isfile(src_path):
            continue

        data = load_json(src_path)
        rows = data if isinstance(data, list) else [data]
        file_changed = False

        for row in rows:
            if not isinstance(row, dict):
                continue

            for key, value in row.items():
                if value is None:
                    continue

                if not isinstance(value, unicode):
                    try:
                        value = unicode(value, 'utf-8')
                    except Exception:
                        continue

                text = value.strip()
                if text == u'':
                    continue

                if not is_khmer_field(key):
                    continue

                if has_khmer_unicode(text):
                    continue

                # SAFE mode: only convert strings that look like legacy-encoded text.
                if (not aggressive) and (not has_extended_latin(text)):
                    continue

                fields_scanned += 1
                converted, changed, _ = convert_limon(text, legacy_data)
                if not changed:
                    continue

                row[key] = converted
                fields_changed += 1
                file_changed = True

                if len(samples) < 15:
                    samples.append({
                        'file': filename,
                        'field': key,
                        'from': text,
                        'to': converted,
                    })

        files_scanned += 1
        out_path = os.path.join(target_dir, filename if apply_changes else filename + '.preview')
        save_json(out_path, rows if isinstance(data, list) else rows[0])

        if apply_changes and file_changed:
            # Keep original UTF-8 with BOM behavior out of source; write clean UTF-8.
            save_json(src_path, rows if isinstance(data, list) else rows[0])

    now = datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    sys.stdout.write('Mode: %s\n' % ('APPLY' if apply_changes else 'PREVIEW'))
    sys.stdout.write('Aggressive: %s\n' % ('YES' if aggressive else 'NO'))
    sys.stdout.write('Time: %s\n' % now)
    sys.stdout.write('Files scanned: %d\n' % files_scanned)
    sys.stdout.write('Fields scanned: %d\n' % fields_scanned)
    sys.stdout.write('Fields changed: %d\n' % fields_changed)
    sys.stdout.write('Output dir: %s\n' % target_dir)

    if samples:
        sys.stdout.write('Sample conversions:\n')
        for s in samples:
            line = u"  * %s :: %s: '%s' -> '%s'\n" % (s['file'], s['field'], s['from'], s['to'])
            sys.stdout.write(line.encode('utf-8'))

    return 0


if __name__ == '__main__':
    sys.exit(main())
