import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';

// CI runs this after npm run build. Local source-only test runs may skip it.
const directory = new URL('../../public/build/', import.meta.url);
test('production galaxy entry keeps heavyweight rendering out of initial imports', t => {
    const manifestPath = new URL('manifest.json', directory);
    if (!existsSync(manifestPath)) return t.skip('Run npm run build to verify production loading budget');
    const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    const entry = manifest['resources/js/galaxy.js'];
    const renderer = 'resources/js/galaxy-renderer.js';
    assert.ok(entry.dynamicImports.includes(renderer));
    const initial = new Set();
    function visit(key) {
        if (initial.has(key)) return;
        initial.add(key);
        for (const imported of manifest[key].imports ?? []) visit(imported);
    }
    visit('resources/js/galaxy.js');
    assert.equal(initial.has(renderer), false);
    const bytes = [...initial].reduce((total, key) => total + readFileSync(new URL(manifest[key].file, directory)).length, 0);
    assert.ok(bytes < 20_000, `Initial galaxy JavaScript must stay below 20 kB; got ${bytes}`);
});
