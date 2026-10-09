"""Release-order checks using fake executables; never invokes a real deploy."""

import os
from pathlib import Path
import subprocess
import shutil
import tempfile
import unittest


SCRIPT = Path(__file__).resolve().parents[2] / "deploy.sh"


class DeployTests(unittest.TestCase):
    def run_deploy(self, fail="", backup=True, revision="a" * 40, dirty=False, head=None, real_git=False, leftover_framework_lock=False, composer_form="path"):
        with tempfile.TemporaryDirectory(prefix="release test ") as directory:
            root = Path(directory)
            binary = root / "bin"
            binary.mkdir()
            site = root / "site"
            (site / "storage/framework").mkdir(parents=True)
            (site / "bootstrap").mkdir()
            (site / "scripts").mkdir()
            shutil.copy(SCRIPT.parent / "scripts/release-deploy.sh", site / "scripts/release-deploy.sh")
            log = root / "commands.log"
            for tool in ["php", "composer", "npm", "git", "sudo", "flock", "backup"]:
                if tool == "git" and real_git:
                    continue
                path = binary / tool
                path.write_text(
                    '#!/bin/sh\n'
                    'command="$(basename "$0") $*"\n'
                    'printf "%s\\n" "$command" >> "$COMMAND_LOG"\n'
                    '[ "$command" != "$FAIL_COMMAND" ] || exit 1\n'
                    'if [ "$(basename "$0")" = git ]; then\n'
                    '  case "$1" in\n'
                    '    rev-parse) printf "%s\\n" "$MOCK_HEAD";;\n'
                    '    status) [ "$MOCK_DIRTY" != 1 ] || printf " M tracked-file\\n";;\n'
                    '  esac\n'
                    'fi\n'
                    'if [ "$command" = "php artisan config:cache" ]; then\n'
                    '  [ "$(cat bootstrap/build-commit.txt)" = "$RELEASE_COMMIT" ] || exit 1\n'
                    'fi\n'
                    'exit 0\n'
                )
                path.chmod(0o700)
            # The site temp path contains a space. Composer is split on
            # whitespace, so its command has to live on a path without spaces.
            composer_dir = tempfile.mkdtemp(prefix="composermock")
            self.addCleanup(shutil.rmtree, composer_dir, True)
            php84 = Path(composer_dir) / "php8.4"
            composer_bin = Path(composer_dir) / "composer"
            shutil.copy(binary / "php", php84)
            shutil.copy(binary / "composer", composer_bin)
            php84.chmod(0o700)
            composer_bin.chmod(0o700)
            if composer_form == "path":
                forge_composer = str(composer_bin)
            elif composer_form == "php":
                forge_composer = f"{php84} {composer_bin}"
            elif composer_form == "glob":
                forge_composer = f"{php84} *"
            else:
                raise AssertionError(composer_form)
            if real_git:
                def git(*args):
                    return subprocess.check_output(["git", *args], cwd=site, text=True).strip()
                git("init", "-q", "-b", "reviewed-branch")
                git("config", "user.email", "test@example.test")
                git("config", "user.name", "Release Test")
                if leftover_framework_lock:
                    # A checkout from before deploy.lock was ignored. A blanket
                    # /storage/ rule would hide the clean-checkout failure.
                    (site / ".gitignore").write_text("/bootstrap/\n")
                    (site / "storage/framework/.gitignore").write_text(
                        "compiled.php\n"
                        "config.php\n"
                        "down\n"
                        "events.scanned.php\n"
                        "maintenance.php\n"
                        "routes.php\n"
                        "routes.scanned.php\n"
                        "schedule-*\n"
                        "services.json\n"
                    )
                else:
                    (site / ".gitignore").write_text("/storage/\n/bootstrap/\n")
                git("add", ".")
                git("commit", "-qm", "chore: initial")
                initial = git("rev-parse", "HEAD")
                (site / "marker").write_text("reviewed")
                git("add", ".")
                git("commit", "-qm", "feat: reviewed")
                revision = git("rev-parse", "HEAD")
                (site / "marker").write_text("not yet reviewed")
                git("commit", "-qam", "feat: branch advanced")
                origin = root / "origin.git"
                subprocess.run(["git", "clone", "--bare", str(site), str(origin)], check=True, capture_output=True)
                git("remote", "add", "origin", str(origin))
                git("checkout", "--detach", "-q", initial)
                if leftover_framework_lock:
                    (site / "storage/framework/deploy.lock").write_text("")
            env = {
                **os.environ,
                "PATH": f"{binary}:{os.environ['PATH']}",
                "FORGE_SITE_PATH": str(site),
                "FORGE_SITE_BRANCH": "reviewed-branch",
                "FORGE_PHP": str(binary / "php"),
                "FORGE_COMPOSER": forge_composer,
                "FORGE_PHP_FPM": "php8.4-fpm",
                "COMMAND_LOG": str(log),
                "FAIL_COMMAND": fail,
                "RELEASE_COMMIT": revision,
                "MOCK_HEAD": head or revision,
                "MOCK_DIRTY": "1" if dirty else "0",
            }
            env.pop("ACCOUNT_BACKUP_HOOK", None)
            if backup:
                env["ACCOUNT_BACKUP_HOOK"] = str(binary / "backup")
            result = subprocess.run(
                ["bash", str(SCRIPT)], env=env, capture_output=True, text=True
            )
            commands = log.read_text().splitlines() if log.exists() else []
            if real_git:
                self.assertEqual(git("rev-parse", "HEAD"), revision)
                self.assertEqual((site / "marker").read_text(), "reviewed")
            if leftover_framework_lock:
                self.assertFalse((site / "storage/framework/deploy.lock").exists(), result.stderr)
                self.assertTrue(Path(f"{site}.deploy.lock").is_file(), result.stderr)
                status = subprocess.check_output(
                    ["git", "status", "--porcelain", "--untracked-files=normal"],
                    cwd=site,
                    text=True,
                )
                self.assertEqual(status, "", result.stderr)
            return result, commands

    def test_requires_backup_configuration_before_mutations(self):
        result, commands = self.run_deploy(backup=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(commands, [])

    def test_real_git_pins_reviewed_commit_even_after_branch_advances(self):
        result, commands = self.run_deploy(real_git=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertTrue(any("universe:releases:publish" in c for c in commands))

    def test_deploy_lock_does_not_dirty_a_tracked_framework_directory(self):
        ignore = (SCRIPT.parent / "storage/framework/.gitignore").read_text().splitlines()
        self.assertIn("deploy.lock", ignore)
        result, commands = self.run_deploy(real_git=True, leftover_framework_lock=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertNotIn("clean checkout", result.stderr)
        self.assertTrue(any("universe:releases:publish" in c for c in commands))

    def test_forge_composer_includes_the_site_php_binary(self):
        result, commands = self.run_deploy(composer_form="php")
        self.assertEqual(result.returncode, 0, result.stderr)
        install = [
            command for command in commands
            if command.endswith("install --no-dev --no-interaction --prefer-dist --optimize-autoloader")
        ]
        self.assertEqual(len(install), 1, commands)
        self.assertTrue(install[0].startswith("php8.4 /"), install[0])
        self.assertIn("/composer ", install[0])

    def test_forge_composer_splits_without_expanding_globs(self):
        result, commands = self.run_deploy(composer_form="glob")
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn(
            "php8.4 * install --no-dev --no-interaction --prefer-dist --optimize-autoloader",
            commands,
        )

    def test_release_order(self):
        result, commands = self.run_deploy()
        self.assertEqual(result.returncode, 0, result.stderr)
        ordered = [
            "php artisan down --retry=60 --render=errors::503",
            "backup ",
            "git checkout --detach " + "a" * 40,
            "composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader",
            "npm run build",
            "php artisan config:clear",
            "php artisan migrate --force",
            "php artisan config:cache",
            "php artisan queue:restart",
            "sudo -n service php8.4-fpm reload",
            "php artisan up",
            "php artisan solar:warm-cache",
            "php artisan universe:releases:publish --commit=" + "a" * 40,
        ]
        positions = [commands.index(command) for command in ordered]
        self.assertEqual(positions, sorted(positions))
        self.assertNotIn("php artisan optimize:clear", commands)

    def test_failures_leave_maintenance_enabled(self):
        for failing in ["backup ", "npm run build", "php artisan migrate --force", "php artisan config:cache", "php artisan queue:restart", "sudo -n service php8.4-fpm reload", "php artisan up"]:
            with self.subTest(failing=failing):
                result, commands = self.run_deploy(fail=failing)
                self.assertNotEqual(result.returncode, 0)
                self.assertIn("php artisan down --retry=60 --render=errors::503", commands)
                if failing != "php artisan up":
                    self.assertNotIn("php artisan up", commands)
                self.assertFalse(any("universe:releases:publish" in c for c in commands))
                self.assertIn("site remains in maintenance", result.stderr)
                if failing == "backup ":
                    self.assertFalse(any(c.startswith("git checkout") for c in commands))

    def test_cache_warming_failure_does_not_fail_release(self):
        result, commands = self.run_deploy(fail="php artisan solar:warm-cache")
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("php artisan up", commands)

    def test_invalid_or_unreviewed_revision_never_enters_maintenance(self):
        for revision in ["", "main", "a" * 39, "a" * 40 + ";false"]:
            result, commands = self.run_deploy(revision=revision)
            self.assertNotEqual(result.returncode, 0)
            self.assertEqual(commands, [])
        for kwargs in [dict(dirty=True), dict(fail="git merge-base --is-ancestor " + "a" * 40 + " FETCH_HEAD")]:
            result, commands = self.run_deploy(**kwargs)
            self.assertNotEqual(result.returncode, 0)
            self.assertFalse(any("artisan down" in c for c in commands))

    def test_checkout_mismatch_stops_before_installation(self):
        result, commands = self.run_deploy(head="b" * 40)
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(any(c.startswith("composer") for c in commands))
        self.assertFalse(any("universe:releases:publish" in c for c in commands))

    def test_readiness_or_publication_failure_reports_active_code(self):
        result, commands = self.run_deploy(fail="php artisan universe:releases:publish --commit=" + "a" * 40)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("php artisan up", commands)
        self.assertIn("Code is active but release publication failed", result.stderr)
        self.assertNotIn("site remains in maintenance", result.stderr)


if __name__ == "__main__":
    unittest.main()
