#!/usr/bin/env python3
"""Validate reviewed community notes and prevent edits to already released notes."""
import argparse
import json
import os
from pathlib import Path
import re
import subprocess
import sys

VERSION = re.compile(r'(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\Z')


def version_tuple(value):
    if not isinstance(value, str) or len(value) > 40 or not VERSION.fullmatch(value):
        raise ValueError('Versions must be stable MAJOR.MINOR.PATCH without leading zeroes.')
    return tuple(map(int, value.split('.')))


def text(value, limit):
    return isinstance(value, str) and bool(value.strip()) and len(value) <= limit and not re.search(r"[\x00-\x1f\x7f<>]", value)


def check_notes(path):
    if path.stat().st_size > 32768:
        raise ValueError(f'{path}: notes exceed 32 KiB')
    data = json.loads(path.read_text())
    if not isinstance(data, dict) or set(data) != {'title', 'summary', 'sections'}:
        raise ValueError(f'{path}: expected title, summary and sections')
    if not text(data['title'], 120) or not text(data['summary'], 500):
        raise ValueError(f'{path}: title/summary is missing or too long')
    sections = data['sections']
    if not isinstance(sections, dict) or not 1 <= len(sections) <= 6:
        raise ValueError(f'{path}: expected 1–6 sections')
    for heading, items in sections.items():
        if not text(heading, 80) or not isinstance(items, list) or not 1 <= len(items) <= 10:
            raise ValueError(f'{path}: invalid section')
        if not all(text(item, 500) for item in items):
            raise ValueError(f'{path}: invalid bullet')


def git_file(revision, path):
    result = subprocess.run(['git', 'show', f'{revision}:{path}'], capture_output=True)
    return result.stdout if result.returncode == 0 else None


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--base')
    args = parser.parse_args()
    version = Path('version.txt').read_text().strip()
    current = version_tuple(version)
    manifest = json.loads(Path('.release-please-manifest.json').read_text())
    if manifest != ({'.': version} if version != '0.0.0' else {}):
        raise ValueError('Release manifest and version.txt must agree.')
    notes = Path('resources/releases')
    for path in notes.glob('*.json'):
        if version_tuple(path.stem) == (0, 0, 0):
            raise ValueError('0.0.0 is a bootstrap sentinel, not a release.')
        check_notes(path)
    if version != '0.0.0' and not (notes / f'{version}.json').is_file():
        raise ValueError(f'Add reviewed community notes at resources/releases/{version}.json before merging the release PR.')
    if args.base:
        if not re.fullmatch('[a-f0-9]{40}', args.base):
            raise ValueError('Base must be a full commit SHA.')
        subprocess.run(['git', 'cat-file', '-e', f'{args.base}^{{commit}}'], check=True, capture_output=True)
        previous_file = git_file(args.base, 'version.txt')
        previous = version_tuple(previous_file.decode().strip()) if previous_file else (0, 0, 0)
        if current < previous:
            raise ValueError('Release versions cannot go backwards.')
        for path in notes.glob('*.json'):
            if version_tuple(path.stem) <= previous and git_file(args.base, str(path)) is None:
                raise ValueError(f'{path}: cannot add backdated notes for an already released version.')
        if previous_file:
            paths = subprocess.check_output(['git', 'ls-tree', '-r', '--name-only', args.base, 'resources/releases/']).decode().splitlines()
            for name in paths:
                path = Path(name)
                if path.suffix == '.json' and version_tuple(path.stem) <= previous:
                    if not path.is_file() or path.read_bytes() != git_file(args.base, name):
                        raise ValueError(f'{name}: published notes are immutable; add a correction in the next release.')
    title = os.environ.get('PR_TITLE')
    if title and not re.match(r'^(feat|fix|perf|docs|test|refactor|build|ci|chore|revert)(\([a-zA-Z0-9_./ -]+\))?!?: .+', title):
        raise ValueError('Use a Conventional Commit PR title, e.g. feat: add measured system browsing.')
    print(f'Release metadata valid: {version}')


if __name__ == '__main__':
    try:
        main()
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        print(f'Release validation failed: {error}', file=sys.stderr)
        sys.exit(1)
