#!/usr/bin/env python3
"""Remove delivery-history wording ("COPS Phase 5a:", "Bundle 7 -", "(Phase 7b)") from PHP comments.

  python clean_history_comments.py --root <api-root> [--apply] [--dirs app,routes,database,config,tests,bootstrap]

Only comments are edited. Every changed file is proven to have an identical code token stream before and after (code-digest.php),
so behaviour cannot change. Comments that mention a phase in a way this tool does not understand are listed for manual review.
"""
import argparse
import re
import subprocess
import sys
from pathlib import Path

LEAD = re.compile(r"(?P<start>(?://|#|/\*\*|/\*|^\s*\*|^\s*\|)\s*(?:-{2,}\s*)?)(?:COPS )?(?:Phase \d+[a-z]?|Bundle \d+)(?: (?:wiring|seeds?|foundation|hardening|\w+ing))?(?: \([^)]*\))?\s*[:\-–—]?\s*(?P<next>\S)", re.M)
PAREN = re.compile(r"\s*\((?:COPS )?(?:Phase \d+[a-z]?|Bundle \d+)\)")
TAIL = re.compile(r",\s*(?:COPS )?Phase \d+[a-z]?\)")
MENTION = re.compile(r"\b(?:COPS Phase|Phase \d+[a-z]?\b|Bundle \d+)")


def fix_line(line):
    new = LEAD.sub(lambda m: m.group('start') + m.group('next').upper(), line)
    new = PAREN.sub('', new)
    new = TAIL.sub(')', new)
    # a trailing comment that is now only a word or two after the code (e.g. `// Leave`) says nothing: drop it
    m = re.match(r"^(\s*\S.*?[;,{(\[])\s+//\s*(\w+(?: \w+)?)\s*$", new)
    if m and new != line and not re.search(r"['\"]\s*//", m.group(1)):
        new = m.group(1)
    return new


def digests(files):
    out = {}
    for i in range(0, len(files), 150):
        r = subprocess.run(['php', str(Path(__file__).with_name('code-digest.php')), *map(str, files[i:i + 150])], text=True, capture_output=True)
        if r.returncode != 0:
            sys.exit(f"php failed: {r.stderr}")
        for line in r.stdout.splitlines():
            h, f = line.split('  ', 1)
            out[f] = h
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--root', required=True)
    ap.add_argument('--apply', action='store_true')
    ap.add_argument('--dirs', default='app,routes,database,config,tests,bootstrap')
    a = ap.parse_args()
    root = Path(a.root)
    files = [p for d in a.dirs.split(',') if (root / d).is_dir() for p in sorted((root / d).rglob('*.php'))]
    before = digests(files)
    changed, lines_changed, left = [], 0, []
    for f in files:
        text = f.read_text(encoding='utf-8')
        out = []
        for line in text.split('\n'):
            if MENTION.search(line):
                new = fix_line(line)
                if new != line:
                    lines_changed += 1
                line = new
            out.append(line)
        new_text = '\n'.join(out)
        if new_text != text:
            changed.append((f, text, new_text))
    if a.apply:
        for f, _, new_text in changed:
            f.write_text(new_text, encoding='utf-8')
        after = digests([f for f, _, _ in changed])
        bad = [f for f in after if after[f] != before[str(f)]]
        if bad:
            for f, old, _ in changed:
                f.write_text(old, encoding='utf-8')
            sys.exit(f"ABORTED and restored: the code of {len(bad)} file(s) would have changed: {bad[:3]}")
    for f in files:
        for i, line in enumerate((f.read_text(encoding='utf-8') if a.apply else dict((c[0], c[2]) for c in changed).get(f, f.read_text(encoding='utf-8'))).split('\n'), 1):
            if MENTION.search(line):
                left.append(f"{f.relative_to(root)}:{i}: {line.strip()[:120]}")
    print(f"{'edited' if a.apply else 'would edit'} {lines_changed} comment line(s) in {len(changed)} file(s); code identical in every file" if a.apply
          else f"would edit {lines_changed} comment line(s) in {len(changed)} file(s) (run with --apply)")
    print(f"{len(left)} comment line(s) still mention a phase or bundle (review by hand):")
    for l in left[:40]:
        print("  " + l)


if __name__ == '__main__':
    main()
