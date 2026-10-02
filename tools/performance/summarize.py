"""Summarize recorded request-kernel samples; does not execute an application."""
import json
import statistics
import sys

report = json.load(open(sys.argv[1], encoding='utf-8'))
print('| Route workload | API cold/fresh | Response bytes | DOM elements | Kernel median ms cold/fresh | Peak PHP MiB |')
print('| --- | ---: | ---: | ---: | ---: | ---: |')
for name, route in report['routes'].items():
    cold = route['measurements']['cold_application_cache']
    fresh = route['measurements']['fresh_application_cache']
    samples = cold + fresh
    calls = f"{max(s['upstream_calls'] for s in cold)}/{max(s['upstream_calls'] for s in fresh)}"
    medians = f"{statistics.median(s['kernel_ms'] for s in cold):.2f}/{statistics.median(s['kernel_ms'] for s in fresh):.2f}"
    print(f"| {name} | {calls} | {max(s['response_bytes'] for s in samples):,} | "
          f"{max(s['dom_elements'] for s in samples):,} | {medians} | "
          f"{max(s['php_peak_used_bytes'] for s in samples) / 1048576:.1f} |")
