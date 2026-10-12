#!/usr/bin/env python3
"""
Fills "Actual Result" and "Status" of the test cases from a PHPUnit run.

    php artisan test --log-junit storage/logs/junit.xml
    python3 docs/fill_results.py                 # or: python3 docs/fill_results.py path/to/junit.xml

Writes docs/test-results.csv and docs/test-results.md.
"""
import csv
import sys
import xml.etree.ElementTree as ET
from collections import Counter
from pathlib import Path

here = Path(__file__).resolve().parent
junit = Path(sys.argv[1]) if len(sys.argv) > 1 else here.parent / 'storage' / 'logs' / 'junit.xml'

if not junit.exists():
    sys.exit(f"Cannot find {junit}. Run: php artisan test --log-junit storage/logs/junit.xml")

# result of every test, keyed by "ClassName::method"
results = {}
for case in ET.parse(junit).getroot().iter('testcase'):
    cls = (case.get('class') or case.get('classname') or '').replace('.', '\\').split('\\')[-1]
    name = (case.get('name') or '').split(' ')[0]
    failure = case.find('failure')
    error = case.find('error')
    skipped = case.find('skipped')
    if failure is not None or error is not None:
        node = failure if failure is not None else error
        text = ' '.join((node.get('message') or node.text or 'failed').split())[:240]
        results[f'{cls}::{name}'] = ('Fail', text)
    elif skipped is not None:
        results[f'{cls}::{name}'] = ('Skipped', ' '.join((skipped.get('message') or 'skipped').split())[:240])
    else:
        results[f'{cls}::{name}'] = ('Pass', 'Behaved as expected')

with open(here / 'test-cases.csv', newline='', encoding='utf-8-sig') as fh:
    rows = list(csv.DictReader(fh))

counts = Counter()
for row in rows:
    status, actual = results.get(row['Automated test'], ('Not run', 'Not found in this test run'))
    row['Status'], row['Actual Result'] = status, actual
    counts[status] += 1

with open(here / 'test-results.csv', 'w', newline='', encoding='utf-8-sig') as fh:
    writer = csv.DictWriter(fh, fieldnames=list(rows[0].keys()))
    writer.writeheader()
    writer.writerows(rows)

lines = ['# Test results', '', 'Source: ' + str(junit.name), '',
         '| Status | Count |', '|---|---|'] + [f'| {k} | {v} |' for k, v in counts.most_common()] + ['']
failed = [r for r in rows if r['Status'] == 'Fail']
if failed:
    lines += ['## Failed cases', '', '| ID | Scenario | What happened |', '|---|---|---|']
    lines += [f"| {r['Test Case ID']} | {r['Scenario']} | {r['Actual Result'].replace('|', '/')} |" for r in failed]
open(here / 'test-results.md', 'w', encoding='utf-8').write('\n'.join(lines) + '\n')

print(dict(counts))
print('Written: docs/test-results.csv and docs/test-results.md')
