#!/usr/bin/env python3
"""Remove Cursor co-author trailer from git commit messages (stdin -> stdout)."""
import sys

for line in sys.stdin:
    if line.rstrip() == "Co-authored-by: Cursor <cursoragent@cursor.com>":
        continue
    sys.stdout.write(line)
