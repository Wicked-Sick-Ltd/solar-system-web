#!/usr/bin/env python3
"""Rehearse release publication over real loopback HTTPS in a disposable clone.

Requires installed PHP dependencies, a built asset manifest, Git and OpenSSL.
No production configuration, shared trust-store changes, API calls or mail.
This checks the application publication contract, not Forge/FPM/backup operations.
"""
from __future__ import annotations

import base64
from contextlib import closing
import http.client
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import os
from pathlib import Path
import shlex
import shutil
import socket
import sqlite3
import ssl
import subprocess
import tempfile
import threading
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[1]


def main() -> None:
    if not __debug__:
        raise RuntimeError('Run without Python -O: rehearsal assertions must remain enabled.')
    php = shutil.which('php')
    if not php or not (ROOT / 'vendor/autoload.php').is_file() or not (ROOT / 'public/build/manifest.json').is_file():
        raise RuntimeError('Install locked PHP dependencies and run npm run build first.')
    # Do not inherit database URLs, API tokens, proxy settings or production env.
    env = {key: os.environ[key] for key in ('PATH', 'TMPDIR', 'SYSTEMROOT') if key in os.environ}
    env.update({'APP_ENV': 'local', 'APP_DEBUG': 'false', 'APP_KEY': 'base64:'+base64.b64encode(os.urandom(32)).decode(),
                'DB_CONNECTION': 'sqlite', 'DB_URL': '', 'CACHE_STORE': 'array', 'SESSION_DRIVER': 'array',
                'MAIL_MAILER': 'array', 'QUEUE_CONNECTION': 'sync', 'LOG_CHANNEL': 'single',
                'API_BASE_URL': 'http://127.0.0.1:9/api/v1', 'MAILCHIMP_API_KEY': '', 'GA_MEASUREMENT_ID': '',
                'GIT_CONFIG_GLOBAL': os.devnull, 'GIT_CONFIG_NOSYSTEM': '1', 'GIT_TERMINAL_PROMPT': '0'})
    with tempfile.TemporaryDirectory(prefix='public-universe-release-') as temporary:
        base = Path(temporary)
        checkout = base / 'app'

        def run(*args: str, success: bool = True, cwd: Path | None = None) -> str:
            result = subprocess.run(args, cwd=cwd or checkout, env=env, text=True, capture_output=True, timeout=90)
            if (result.returncode == 0) != success:
                raise RuntimeError(f'{args[0]} exited {result.returncode}: {(result.stdout + result.stderr)[-2000:]}')
            return result.stdout.strip()

        revision = run('git', 'rev-parse', 'HEAD', cwd=ROOT)
        source_dirty = bool(run('git', 'status', '--porcelain', cwd=ROOT))
        run('git', 'clone', '--quiet', '--shared', '--no-checkout', str(ROOT), str(checkout), cwd=base)
        run('git', 'checkout', '--quiet', '--detach', revision)
        run('git', 'config', 'user.name', 'Local Release Rehearsal')
        run('git', 'config', 'user.email', 'rehearsal@example.test')
        run('git', 'config', 'commit.gpgsign', 'false')
        # Copy rather than symlink vendor: Composer paths must resolve this clone's
        # application, not the original checkout or its local configuration.
        shutil.copytree(ROOT / 'vendor', checkout / 'vendor')
        shutil.copytree(ROOT / 'public/build', checkout / 'public/build')
        for directory in ('bootstrap/cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'):
            (checkout / directory).mkdir(parents=True, exist_ok=True)
        database = base / 'accounts.sqlite'
        database.touch()
        env['DB_DATABASE'] = str(database)
        certificate, key = base / 'certificate.pem', base / 'key.pem'
        run('openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1',
            '-keyout', str(key), '-out', str(certificate), '-subj', '/CN=localhost',
            '-addext', 'subjectAltName=DNS:localhost,IP:127.0.0.1')
        wrapper = base / 'php'
        wrapper.write_text('#!/bin/sh\nexec '+shlex.join([php, '-d', 'opcache.enable=0', '-d', 'opcache.enable_cli=0', '-d', f'curl.cainfo={certificate}', '-d', f'openssl.cafile={certificate}'])+' "$@"\n')
        wrapper.chmod(0o700)
        env['FORGE_PHP'] = str(wrapper)
        # Bind only loopback. The PHP port has a short allocation race; failure is
        # detected by the child status and readiness timeout, never another host.
        with socket.socket() as allocation:
            allocation.bind(('127.0.0.1', 0))
            php_port = allocation.getsockname()[1]
        state = {'mode': 'normal', 'checks': []}

        class Proxy(BaseHTTPRequestHandler):
            def log_message(self, *_args):
                pass

            def do_GET(self):
                with closing(http.client.HTTPConnection('127.0.0.1', php_port, timeout=5)) as connection:
                    connection.request('GET', self.path, headers={'Host': self.headers.get('Host', 'localhost'), 'Accept': 'application/json'})
                    response = connection.getresponse()
                    status, body = response.status, response.read()
                    headers = dict(response.getheaders())
                if self.path.startswith('/up/release?check='):
                    state['checks'].append({'path': self.path, 'cache': self.headers.get('Cache-Control', '')})
                    if state['mode'] == 'stale':
                        data = json.loads(body)
                        data['commit'] = '0'*40
                        body = json.dumps(data).encode()
                    elif state['mode'] == 'redirect':
                        status = 302
                        headers['Location'] = '/up/release'
                    elif state['mode'] == 'cached':
                        headers = {name: value for name, value in headers.items() if name.lower() != 'cache-control'}
                        headers['Cache-Control'] = 'public, max-age=600'
                self.send_response(status)
                for name, value in headers.items():
                    if name.lower() not in ('content-length', 'connection', 'transfer-encoding', 'server', 'date'):
                        self.send_header(name, value)
                self.send_header('Content-Length', str(len(body)))
                self.end_headers()
                self.wfile.write(body)

        proxy = ThreadingHTTPServer(('127.0.0.1', 0), Proxy)
        context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
        context.load_cert_chain(certificate, key)
        proxy.socket = context.wrap_socket(proxy.socket, server_side=True)
        origin = f'https://127.0.0.1:{proxy.server_port}'
        env['APP_URL'] = origin
        client = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPSHandler(context=ssl.create_default_context(cafile=str(certificate))))
        run(str(wrapper), 'artisan', 'migrate', '--force')
        server_log = (base / 'server.log').open('w')
        server = subprocess.Popen([str(wrapper), '-S', f'127.0.0.1:{php_port}', '-t', str(checkout / 'public'), str(checkout / 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], cwd=checkout / 'public', env=env, stdout=server_log, stderr=server_log)
        thread = threading.Thread(target=proxy.serve_forever, daemon=True)
        thread.start()
        checks = []

        def rows():
            with sqlite3.connect(database) as connection:
                return connection.execute('SELECT version, "commit", notes, published_at FROM community_releases ORDER BY version').fetchall()

        def activate(version: str, title_suffix: str = '') -> str:
            (checkout / 'version.txt').write_text(version+'\n')
            if version != '0.0.0':
                notes = json.loads((checkout / 'resources/releases/1.0.0.json').read_text())
                notes['title'] = 'LOCAL REHEARSAL '+version+title_suffix
                (checkout / f'resources/releases/{version}.json').write_text(json.dumps(notes))
            run('git', 'add', 'version.txt', 'resources/releases')
            run('git', 'commit', '--quiet', '--allow-empty', '-m', 'test: local release '+version)
            run('bash', 'scripts/release-deploy.sh', 'prepare')
            run(str(wrapper), 'artisan', 'config:cache')
            return run('git', 'rev-parse', 'HEAD')

        try:
            for _ in range(50):
                if server.poll() is not None:
                    raise RuntimeError('Temporary PHP server exited before readiness.')
                try:
                    with client.open(f'http://127.0.0.1:{php_port}/up', timeout=1) as response:
                        if response.status == 200:
                            break
                except OSError:
                    time.sleep(0.1)
            else:
                raise RuntimeError('Temporary PHP server did not become ready.')
            activate('0.0.0')
            with client.open(origin+'/up/release', timeout=10) as response:
                health = json.load(response)
                assert health['version'] == '0.0.0' and health['database_ready'] is True
                assert 'no-store' in response.headers.get('Cache-Control', '')
            run('bash', 'scripts/release-deploy.sh', 'publish')
            assert rows() == [], 'Bootstrap must not publish notes.'
            checks.append('bootstrap readiness over trusted loopback HTTPS; no publication')
            with sqlite3.connect(database) as connection:
                connection.execute('ALTER TABLE community_releases RENAME TO held_releases')
            run('bash', 'scripts/release-deploy.sh', 'publish', success=False)
            with sqlite3.connect(database) as connection:
                connection.execute('ALTER TABLE held_releases RENAME TO community_releases')
            assert rows() == []
            checks.append('missing release database table rejected before publication')
            first_commit = activate('1.0.0')
            for mode in ('stale', 'redirect', 'cached'):
                state['mode'] = mode
                run('bash', 'scripts/release-deploy.sh', 'publish', success=False)
                assert rows() == [], f'{mode} response must not publish notes.'
                checks.append(f'{mode} readiness response rejected without publication')
            state['mode'] = 'normal'
            run('bash', 'scripts/release-deploy.sh', 'publish')
            first_rows = rows()
            assert len(first_rows) == 1 and first_rows[0][0] == '1.0.0' and first_rows[0][1] == first_commit
            assert json.loads(first_rows[0][2])['title'] == 'LOCAL REHEARSAL 1.0.0'
            # Only this disposable fixture is backdated: a timestamp-only rewrite
            # must be observable even when both commands run in the same second.
            with sqlite3.connect(database) as connection:
                connection.execute("UPDATE community_releases SET published_at = '2001-01-01 00:00:00' WHERE version = '1.0.0'")
            first_rows = rows()
            retry_commit = activate('1.0.0', ' amended draft')
            assert retry_commit != first_commit
            run('bash', 'scripts/release-deploy.sh', 'publish')
            assert rows() == first_rows, 'Repeated publication must preserve the original record.'
            checks.append('stable publication and immutable retry using the real SQLite ledger')
            activate('1.1.0')
            run('bash', 'scripts/release-deploy.sh', 'publish')
            assert len(rows()) == 2
            for path in ('/whats-new', '/whats-new/1.1.0'):
                with client.open(origin+path, timeout=10) as response:
                    assert response.geturl() == origin+path, 'Release pages must resolve without redirects.'
                    assert 'LOCAL REHEARSAL 1.1.0' in response.read().decode(), 'New publication must be visible before rollback.'
            run('git', 'checkout', '--quiet', '--detach', first_commit)
            run('bash', 'scripts/release-deploy.sh', 'prepare')
            run(str(wrapper), 'artisan', 'config:cache')
            with client.open(origin+'/whats-new', timeout=10) as response:
                assert response.geturl() == origin+'/whats-new'
                html = response.read().decode()
            assert 'LOCAL REHEARSAL 1.0.0' in html and 'LOCAL REHEARSAL 1.1.0' not in html
            with client.open(origin+'/whats-new/1.0.0', timeout=10) as response:
                assert response.geturl() == origin+'/whats-new/1.0.0'
                assert 'LOCAL REHEARSAL 1.0.0' in response.read().decode()
            try:
                with client.open(origin+'/whats-new/1.1.0', timeout=10):
                    raise AssertionError('A newer release detail must be hidden after rollback.')
            except urllib.error.HTTPError as error:
                assert error.code == 404
            assert len(rows()) == 2, 'Code rollback must not destroy publication history.'
            checks.append('cached-build rollback hides newer notes and preserves ledger records')
            paths = [request['path'] for request in state['checks']]
            assert len(paths) >= 7 and len(paths) == len(set(paths))
            assert all('no-store' in request['cache'] for request in state['checks'])
            print(json.dumps({'source_commit': revision, 'source_worktree_dirty': source_dirty,
                              'application_source': 'Committed HEAD; installed dependencies and built assets copied locally.',
                              'result': 'passed', 'checks': checks,
                              'scope': 'Application release publication only; no Forge, production, backup, scheduler or worker changes.'}, indent=2))
        except Exception as error:
            # The log belongs to this synthetic, secret-free installation only.
            server_log.flush()
            detail = (base / 'server.log').read_text()[-2000:]
            raise RuntimeError(f'{error}\nTemporary PHP server log:\n{detail}') from error
        finally:
            server.terminate()
            try:
                server.wait(timeout=5)
            except subprocess.TimeoutExpired:
                server.kill()
                server.wait(timeout=5)
            proxy.shutdown()
            proxy.server_close()
            thread.join(timeout=5)
            server_log.close()


if __name__ == '__main__':
    main()
