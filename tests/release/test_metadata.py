import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

SCRIPT = Path(__file__).resolve().parents[2] / 'scripts/check-release.py'
NOTES = {'title': 'Practice', 'summary': 'Improve together.', 'sections': {'Features': ['A new feature.']}}


class MetadataTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        (self.root / 'resources/releases').mkdir(parents=True)
        self.git('init', '-q')
        self.git('config', 'user.email', 'test@example.test')
        self.git('config', 'user.name', 'Release Test')
        self.version('1.0.0')
        self.notes('1.0.0')
        self.git('add', '.')
        self.git('commit', '-qm', 'feat: initial release')
        self.base = self.git('rev-parse', 'HEAD').strip()

    def git(self, *args):
        return subprocess.check_output(['git', *args], cwd=self.root, text=True)

    def version(self, version):
        (self.root / 'version.txt').write_text(version + '\n')
        (self.root / '.release-please-manifest.json').write_text(json.dumps({'.': version}))

    def notes(self, version):
        (self.root / f'resources/releases/{version}.json').write_text(json.dumps(NOTES))

    def check(self, ok=True, title='feat: new practice'):
        result = subprocess.run(['python3', str(SCRIPT), '--base', self.base], cwd=self.root,
                                env={**os.environ, 'PR_TITLE': title}, capture_output=True, text=True)
        self.assertEqual(result.returncode, 0 if ok else 1, result.stdout + result.stderr)

    def test_next_release_requires_reviewed_notes(self):
        self.version('1.1.0')
        self.check(False)
        self.notes('1.1.0')
        self.check()

    def test_old_notes_cannot_be_edited_or_deleted(self):
        old = self.root / 'resources/releases/1.0.0.json'
        old.write_text(json.dumps({**NOTES, 'title': 'Changed'}))
        self.check(False)
        old.unlink()
        self.check(False)

    def test_version_regressions_and_manifest_mismatches_fail(self):
        self.version('0.9.0')
        self.notes('0.9.0')
        self.check(False)
        self.version('1.0.0')
        (self.root / '.release-please-manifest.json').write_text('{".": "2.0.0"}')
        self.check(False)

    def test_bad_titles_and_malformed_notes_fail(self):
        self.check(False, title='Update things')
        self.check(title='chore(main): release 1.0.0')
        (self.root / 'resources/releases/2.0.0.json').write_text(json.dumps({**NOTES, 'sections': {'Features': 'not a list'}}))
        self.check(False)

    def test_invalid_version_paths_are_rejected(self):
        self.version('01.0.0')
        self.check(False)

    def test_bootstrap_allows_draft_but_not_a_sentinel_release(self):
        self.base = self.git('hash-object', '-t', 'tree', '/dev/null').strip()  # not a commit
        self.check(False)
        self.git('checkout', '--orphan', 'bootstrap')
        self.version('0.0.0')
        (self.root / '.release-please-manifest.json').write_text('{}')
        self.git('add', '.')
        self.git('commit', '-qm', 'chore: bootstrap')
        self.base = self.git('rev-parse', 'HEAD').strip()
        self.check()
        self.notes('0.0.0')
        self.check(False)

    def test_missing_base_cannot_bypass_immutability(self):
        self.base = 'f' * 40
        self.check(False)

    def test_backdated_release_is_rejected(self):
        self.notes('0.9.0')
        self.check(False)

    def test_future_drafts_are_editable(self):
        self.notes('2.0.0')
        self.check()
        (self.root / 'resources/releases/2.0.0.json').write_text(json.dumps({**NOTES, 'title': 'Draft revision'}))
        self.check()

    def test_controls_markup_and_size_limits_fail(self):
        for title in ['Bad\\nTitle', '<b>HTML</b>', 'x' * 121, '', '   ', 'control' + chr(0)]:
            with self.subTest(title=title):
                # Use an actual newline, not literal backslash-n.
                title = title.replace('\\n', '\n')
                (self.root / 'resources/releases/2.0.0.json').write_text(json.dumps({**NOTES, 'title': title}))
                self.check(False)
        (self.root / 'resources/releases/2.0.0.json').write_text(' ' * 32769)
        self.check(False)
