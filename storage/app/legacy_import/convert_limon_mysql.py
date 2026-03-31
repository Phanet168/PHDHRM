# -*- coding: utf-8 -*-
"""
Convert legacy Khmer Limon text in selected MySQL columns to Unicode Khmer.

Usage:
  py -2.7-32 storage\app\legacy_import\convert_limon_mysql.py           # dry-run
  py -2.7-32 storage\app\legacy_import\convert_limon_mysql.py --apply   # write updates
  py -2.7-32 storage\app\legacy_import\convert_limon_mysql.py --apply --include-legacy-departments
      # also convert departments.department_name where location_code starts with LEGACY-WP-
"""

import io
import os
import re
import sys
import datetime

try:
    import pymysql
except ImportError:
    sys.stderr.write('Missing dependency: pymysql for Python 2.7\n')
    sys.exit(2)

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


def load_env(env_path):
    out = {}
    with io.open(env_path, 'r', encoding='utf-8') as f:
        for raw in f:
            line = raw.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            key, value = line.split('=', 1)
            key = key.strip()
            value = value.strip()
            if (value.startswith('"') and value.endswith('"')) or (value.startswith("'") and value.endswith("'")):
                value = value[1:-1]
            out[key] = value
    return out


KHMER_RE = re.compile(u'[\u1780-\u17FF]')


def has_khmer_unicode(text):
    return KHMER_RE.search(text) is not None


def convert_limon(text, legacy_data):
    # Skip already-unicode Khmer text.
    if has_khmer_unicode(text):
        return text, False, 'already_unicode'

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


def main():
    apply_changes = '--apply' in sys.argv
    include_legacy_departments = '--include-legacy-departments' in sys.argv

    env = load_env(os.path.join(BASE_DIR, '.env'))
    host = env.get('DB_HOST', '127.0.0.1').strip('"')
    port = int((env.get('DB_PORT') or '3306').strip('"'))
    database = env.get('DB_DATABASE', '').strip('"')
    username = env.get('DB_USERNAME', '').strip('"')
    password = env.get('DB_PASSWORD', '')

    if not database:
        sys.stderr.write('DB_DATABASE is missing in .env\n')
        return 2

    fd = FontDataXML.FontData()
    legacy_data = fd.legacyData('limon')

    targets = [
        ('professional_skills', 'id', ['name_km', 'shortcut_km'], None),
        ('positions', 'id', ['position_name_km'], None),
        ('gov_pay_levels', 'id', ['level_name_km'], None),
        ('gov_salary_scales', 'id', ['name_km'], None),
    ]

    if include_legacy_departments:
        targets.append(
            ('departments', 'id', ['department_name'], "`location_code` LIKE 'LEGACY-WP-%'")
        )

    conn = pymysql.connect(
        host=host,
        user=username,
        passwd=password,
        db=database,
        port=port,
        charset='utf8mb4',
        use_unicode=True,
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=False,
    )

    total_scanned = 0
    total_changed = 0
    per_table = {}
    samples = []

    try:
        cur = conn.cursor()

        for table, pk, columns, where_sql in targets:
            per_table.setdefault(table, {'scanned': 0, 'changed': 0})
            select_sql = 'SELECT `%s`, %s FROM `%s`' % (pk, ', '.join(['`%s`' % c for c in columns]), table)
            if where_sql:
                select_sql += ' WHERE ' + where_sql
            cur.execute(select_sql)
            rows = cur.fetchall()

            for row in rows:
                row_id = row[pk]
                updates = {}

                for col in columns:
                    val = row.get(col)
                    if val is None:
                        continue
                    if not isinstance(val, unicode):
                        try:
                            val = unicode(val, 'utf-8')
                        except Exception:
                            try:
                                val = unicode(val, 'cp1252')
                            except Exception:
                                continue

                    text = val.strip()
                    if text == u'':
                        continue

                    total_scanned += 1
                    per_table[table]['scanned'] += 1

                    converted, changed, reason = convert_limon(text, legacy_data)
                    if not changed:
                        continue

                    updates[col] = converted
                    if len(samples) < 12:
                        samples.append({
                            'table': table,
                            'id': row_id,
                            'column': col,
                            'from': text,
                            'to': converted,
                        })

                if updates:
                    total_changed += len(updates)
                    per_table[table]['changed'] += len(updates)
                    if apply_changes:
                        set_sql = ', '.join(['`%s`=%%s' % c for c in updates.keys()])
                        sql = 'UPDATE `%s` SET %s WHERE `%s`=%%s' % (table, set_sql, pk)
                        params = list(updates.values()) + [row_id]
                        cur.execute(sql, params)

        if apply_changes:
            conn.commit()
        else:
            conn.rollback()

    finally:
        conn.close()

    now = datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    sys.stdout.write('Mode: %s\n' % ('APPLY' if apply_changes else 'DRY-RUN'))
    sys.stdout.write('Time: %s\n' % now)
    sys.stdout.write('Scanned fields: %d\n' % total_scanned)
    sys.stdout.write('Changed fields: %d\n' % total_changed)
    for table in sorted(per_table.keys()):
        meta = per_table[table]
        sys.stdout.write('  - %s: scanned=%d changed=%d\n' % (table, meta['scanned'], meta['changed']))

    if samples:
        sys.stdout.write('Sample conversions:\n')
        for s in samples:
            line = u"  * %s#%s %s: '%s' -> '%s'\n" % (s['table'], s['id'], s['column'], s['from'], s['to'])
            sys.stdout.write(line.encode('utf-8'))

    return 0


if __name__ == '__main__':
    sys.exit(main())
