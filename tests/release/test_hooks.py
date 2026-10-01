"""Release hook regression tests in a throwaway Git repository, with fake PHP."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

SCRIPT = Path(__file__).resolve().parents[2] / 'scripts/release-deploy.sh'


class ReleaseHooksTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        (self.root / 'bootstrap').mkdir()
        self.git('init', '-q')
        self.git('config', 'user.email', 'test@example.test')
        self.git('config', 'user.name', 'Release Test')
        self.git('commit', '--allow-empty', '-qm', 'chore: bootstrap')
        self.revision = self.git('rev-parse', 'HEAD').strip()
        self.php = self.root / 'php'
        self.php.write_text('#!/bin/sh\nprintf "%s\\n" "$*" > called\nexit "${PHP_EXIT:-0}"\n')
        self.php.chmod(0o700)

    def git(self, *args):
        return subprocess.check_output(['git', *args], cwd=self.root, text=True)

    def hook(self, operation, code=0):
        return subprocess.run(['bash', str(SCRIPT), operation], cwd=self.root,
                              env={**os.environ, 'FORGE_PHP': str(self.php), 'PHP_EXIT': str(code)},
                              capture_output=True, text=True)

    def test_prepare_pins_revision_and_publish_uses_exact_commit(self):
        result = self.hook('prepare')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual((self.root / 'bootstrap/build-commit.txt').read_text(), self.revision + '\n')
        self.assertEqual((self.root / 'bootstrap/build-commit.txt').stat().st_mode & 0o777, 0o644)
        self.assertEqual(self.hook('publish').returncode, 0)
        self.assertEqual((self.root / 'called').read_text(), f'artisan universe:releases:publish --commit={self.revision}\n')
        self.assertEqual(list((self.root / 'bootstrap').iterdir()), [self.root / 'bootstrap/build-commit.txt'])

    def test_missing_or_stale_build_fails_before_publication(self):
        self.assertNotEqual(self.hook('publish').returncode, 0)
        self.assertEqual(self.hook('prepare').returncode, 0)
        self.git('commit', '--allow-empty', '-qm', 'fix: changed code')
        self.assertNotEqual(self.hook('publish').returncode, 0)
        self.assertFalse((self.root / 'called').exists())

    def test_publication_failure_propagates(self):
        self.assertEqual(self.hook('prepare').returncode, 0)
        self.assertEqual(self.hook('publish', code=17).returncode, 17)

    def test_unknown_operation_fails(self):
        self.assertNotEqual(self.hook('announce').returncode, 0)
        self.assertFalse((self.root / 'called').exists())
