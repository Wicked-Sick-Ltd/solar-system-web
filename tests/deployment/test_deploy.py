"""Release-order checks using fake executables; never invokes a real deploy."""

import os
from pathlib import Path
import subprocess
import tempfile
import unittest


SCRIPT = Path(__file__).resolve().parents[2] / "deploy.sh"


class DeployTests(unittest.TestCase):
    def run_deploy(self, fail="", backup=True):
        with tempfile.TemporaryDirectory(prefix="release test ") as directory:
            root = Path(directory)
            binary = root / "bin"
            binary.mkdir()
            site = root / "site"
            (site / "storage/framework").mkdir(parents=True)
            log = root / "commands.log"
            for tool in ["php", "composer", "npm", "git", "sudo", "flock", "backup"]:
                path = binary / tool
                path.write_text(
                    '#!/bin/sh\n'
                    'command="$(basename "$0") $*"\n'
                    'printf "%s\\n" "$command" >> "$COMMAND_LOG"\n'
                    '[ "$command" != "$FAIL_COMMAND" ]\n'
                )
                path.chmod(0o700)
            env = {
                **os.environ,
                "PATH": f"{binary}:{os.environ['PATH']}",
                "FORGE_SITE_PATH": str(site),
                "FORGE_SITE_BRANCH": "reviewed-branch",
                "FORGE_PHP": str(binary / "php"),
                "FORGE_COMPOSER": str(binary / "composer"),
                "FORGE_PHP_FPM": "php8.4-fpm",
                "COMMAND_LOG": str(log),
                "FAIL_COMMAND": fail,
            }
            env.pop("ACCOUNT_BACKUP_HOOK", None)
            if backup:
                env["ACCOUNT_BACKUP_HOOK"] = str(binary / "backup")
            result = subprocess.run(
                ["bash", str(SCRIPT)], env=env, capture_output=True, text=True
            )
            return result, log.read_text().splitlines() if log.exists() else []

    def test_requires_backup_configuration_before_mutations(self):
        result, commands = self.run_deploy(backup=False)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(commands, [])

    def test_release_order(self):
        result, commands = self.run_deploy()
        self.assertEqual(result.returncode, 0, result.stderr)
        ordered = [
            "php artisan down --retry=60 --render=errors::503",
            "backup ",
            "git pull --ff-only origin reviewed-branch",
            "composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader",
            "npm run build",
            "php artisan config:clear",
            "php artisan migrate --force",
            "php artisan config:cache",
            "php artisan queue:restart",
            "sudo -n service php8.4-fpm reload",
            "php artisan up",
            "php artisan solar:warm-cache",
        ]
        positions = [commands.index(command) for command in ordered]
        self.assertEqual(positions, sorted(positions))
        self.assertNotIn("php artisan optimize:clear", commands)

    def test_failures_leave_maintenance_enabled(self):
        for failing in ["backup ", "npm run build", "php artisan migrate --force"]:
            with self.subTest(failing=failing):
                result, commands = self.run_deploy(fail=failing)
                self.assertNotEqual(result.returncode, 0)
                self.assertIn("php artisan down --retry=60 --render=errors::503", commands)
                self.assertNotIn("php artisan up", commands)
                self.assertIn("site remains in maintenance", result.stderr)
                if failing == "backup ":
                    self.assertFalse(any(c.startswith("git ") for c in commands))

    def test_cache_warming_failure_does_not_fail_release(self):
        result, commands = self.run_deploy(fail="php artisan solar:warm-cache")
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("php artisan up", commands)


if __name__ == "__main__":
    unittest.main()
