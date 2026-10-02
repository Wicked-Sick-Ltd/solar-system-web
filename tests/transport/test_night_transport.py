"""Real loopback HTTP transport checks, with no astronomy/network dependency."""
import gzip
import json
import os
from pathlib import Path
import subprocess
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import unittest

ROOT = Path(__file__).resolve().parents[2]
FIXTURE = (ROOT / "tests/fixtures/observing/night.json").read_bytes()


class Handler(BaseHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def do_POST(self):
        raw = self.rfile.read(int(self.headers.get("Content-Length", "0")))
        self.server.received.append(json.loads(raw))
        mode = self.path.split("/")[1]
        if mode == "redirect":
            self.send_response(307)
            self.send_header("Location", "/valid/observing/night")
            self.end_headers()
            return
        body = FIXTURE if mode == "valid" else b" " * 1600000 + FIXTURE
        self.send_response(200)
        self.send_header("Content-Type", "application/json")
        if mode == "gzip":
            body = gzip.compress(body)
            self.send_header("Content-Encoding", "gzip")
        # Deliberately omit Content-Length: the decoded sink must enforce its
        # own bound for streaming and compressed responses.
        self.end_headers()
        try:
            for index in range(0, len(body), 8192):
                self.wfile.write(body[index:index + 8192])
        except (BrokenPipeError, ConnectionResetError):
            # The bounded client deliberately disconnects before reading the full body.
            pass


PHP = r"""
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['services.solar.base_url' => getenv('NIGHT_TEST_URL')]);
$query = App\Services\Observing\NightRequest::parse([
    'date' => '2026-10-01', 'timezone' => 'Europe/London', 'lat' => 51.5, 'lon' => -0.12,
    'targets' => ['moon', 'saturn'], 'min_altitude_deg' => 20,
    'sun_altitude_deg' => -12, 'min_moon_separation_deg' => 0,
]);
try {
    $plan = app(App\Services\Observing\NightPlanner::class)->calculate($query);
    echo json_encode(['targets' => count($plan['targets'])]);
} catch (App\Services\SolarApi\Exceptions\SolarApiException $e) {
    echo json_encode(['unavailable' => true]);
}
"""


class TransportTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        cls.server.received = []
        cls.thread = threading.Thread(target=cls.server.serve_forever, daemon=True)
        cls.thread.start()

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()
        cls.server.server_close()
        cls.thread.join(timeout=2)

    def invoke(self, mode):
        self.server.received.clear()
        env = {**os.environ, "APP_ENV": "testing", "CACHE_STORE": "array", "SESSION_DRIVER": "array",
               "DB_CONNECTION": "sqlite", "DB_DATABASE": ":memory:",
               "NIGHT_TEST_URL": f"http://127.0.0.1:{self.server.server_port}/{mode}"}
        result = subprocess.run(["php", "-r", PHP], cwd=ROOT, env=env, capture_output=True, text=True, timeout=15)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(len(self.server.received), 1, "Do not retry or forward private observing requests")
        self.assertEqual(self.server.received[0]["lat"], 51.5)
        self.assertIsNone(self.server.received[0]["horizon_mask"])
        return json.loads(result.stdout)

    def test_complete_valid_body_reaches_scientific_validation(self):
        self.assertEqual(self.invoke("valid"), {"targets": 2})

    def test_oversized_uncompressed_stream_is_stopped(self):
        self.assertEqual(self.invoke("oversize"), {"unavailable": True})

    def test_compressed_small_wire_body_cannot_bypass_decoded_limit(self):
        self.assertEqual(self.invoke("gzip"), {"unavailable": True})

    def test_redirects_never_forward_the_private_post(self):
        self.assertEqual(self.invoke("redirect"), {"unavailable": True})


if __name__ == "__main__":
    unittest.main()
