import { defineConfig } from 'vite';
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';
const root = fileURLToPath(new URL('../../../', import.meta.url));
const hash = name => createHash('sha256').update(readFileSync(root + name)).digest('hex');
const source = 'resources/js/galaxy-renderer.js';
export default defineConfig({
    root: root + 'tools/performance/render-harness', base: './',
    define: { __HARNESS_IDENTITY__: JSON.stringify(Object.fromEntries([
        source, 'resources/js/galaxy-data.js', 'package-lock.json',
        'tools/performance/render-harness/metrics.js', 'tools/performance/render-harness/main.js',
        'tools/performance/render-harness/vite.config.js',
    ].map(name => [name, hash(name)]))) },
    build: { outDir: root + 'tmp/map-render-harness', emptyOutDir: true },
    plugins: [{ name: 'isolated-render-measurements', transform(code, id) {
        if (id !== root + source) return;
        for (const needle of ['renderer.render(scene, camera);', 'events.abort();', 'renderer?.dispose();']) {
            if (code.split(needle).length !== 2) throw new Error(`Instrumentation anchor changed: ${needle}`);
        }
        return `import { metrics } from ${JSON.stringify(root + 'tools/performance/render-harness/metrics.js')};\n` + code
            .replace('renderer.render(scene, camera);', 'metrics.render(renderer, () => renderer.render(scene, camera));')
            .replace('events.abort();', "metrics.lifecycle('dispose-start', renderer); events.abort();")
            .replace('renderer?.dispose();', "renderer?.dispose(); metrics.lifecycle('renderer-disposed', renderer);");
    } }],
});
