"""Isolated loopback fixture: no outbound requests, private/random listening port."""
import gzip
import json
from http.server import BaseHTTPRequestHandler, HTTPServer


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path.startswith('/redirect/'):
            self.send_response(302)
            self.send_header('Location', '/small/v1/forecast')
            self.end_headers()
            return
        body = json.dumps({'padding': 'x' * 200000}).encode()
        compressed = self.path.startswith('/gzip/')
        if compressed:
            body = gzip.compress(body)
        self.send_response(200)
        self.send_header('Content-Type', 'application/json')
        if compressed:
            self.send_header('Content-Encoding', 'gzip')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        try:
            self.wfile.write(body)
        except (BrokenPipeError, ConnectionResetError):
            # The bounded client deliberately disconnects before reading the full body.
            pass

    def log_message(self, *_args):
        pass


server = HTTPServer(('127.0.0.1', 0), Handler)
print(server.server_address[1], flush=True)
server.serve_forever()
