"""Inspect a historical dump without printing account data or executing its SQL."""
import argparse
import hashlib
import json
from pathlib import Path
import re


def inventory(source):
    raw = source.read_bytes()
    sql = raw.decode('utf-8')
    tables = {}
    for match in re.finditer(r'CREATE TABLE `([^`]+)`\s*\((.*?)\) ENGINE=', sql, re.S):
        name, body = match.groups()
        columns = re.findall(r'^\s*`([^`]+)`\s+', body, re.M)
        relations = [dict(columns=a, table=b, references=c) for a, b, c in
                     re.findall(r'FOREIGN KEY\s*\(([^)]+)\) REFERENCES `([^`]+)`\s*\(([^)]+)\)', body)]
        tables[name] = dict(columns=columns, relations=relations)
    if not tables:
        raise ValueError('No MariaDB tables found')
    for table in tables.values():
        for relation in table['relations']:
            if relation['table'] not in tables:
                raise ValueError('Foreign key references an absent table')
    return dict(source_sha256=hashlib.sha256(raw).hexdigest(), tables=tables)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('source', type=Path)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    result = inventory(args.source)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    with args.output.open('x') as output:
        output.write(json.dumps(result, indent=2) + '\n')
    args.output.chmod(0o600)
    print(f"Inventoried {len(result['tables'])} tables; no database modified.")
