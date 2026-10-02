import { readFileSync, existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gzipSync } from 'node:zlib';
import { createHash } from 'node:crypto';

export function measureAssets(root) {
    const directory = resolve(root, 'public/build');
    const manifestBytes = readFileSync(resolve(directory, 'manifest.json'));
    const manifest = JSON.parse(manifestBytes);
    const seenFile = new Map();
    const measure = file => {
        if (!seenFile.has(file)) {
            const content = readFileSync(file);
            seenFile.set(file, { bytes: content.length, gzip_bytes: gzipSync(content, { level: 9 }).length, sha256: createHash('sha256').update(content).digest('hex') });
        }
        return seenFile.get(file);
    };
    const closure = entry => {
        const visited = new Set();
        const visit = key => {
            if (visited.has(key)) return;
            if (!manifest[key]) throw new Error(`Missing manifest import ${key}`);
            visited.add(key);
            for (const imported of manifest[key].imports ?? []) visit(imported);
        };
        visit(entry);
        return [...visited];
    };
    const entries = {};
    for (const [key, item] of Object.entries(manifest)) {
        if (!item.isEntry && !item.isDynamicEntry) continue;
        const imports = closure(key);
        const files = [...new Set(imports.flatMap(name => [manifest[name].file, ...(manifest[name].css ?? [])]))];
        entries[key] = {
            dynamic: item.isDynamicEntry === true, imports, files,
            bytes: files.reduce((total, file) => total + measure(resolve(directory, file)).bytes, 0),
            gzip_bytes: files.reduce((total, file) => total + measure(resolve(directory, file)).gzip_bytes, 0),
            dynamic_imports: item.dynamicImports ?? [],
        };
    }
    const runtimeFile = resolve(root, 'vendor/livewire/livewire/dist/livewire.min.js');
    const runtime = existsSync(runtimeFile) ? measure(runtimeFile) : null;
    const fonts = [...new Set(Object.values(manifest).map(value => value.file).filter(file => file.endsWith('.woff2')))];
    return {
        schema_version: 1, node_version: process.version,
        scope: 'Production file bytes and per-file gzip level9, not a measured browser transfer. Static imports included once per entry; dynamic imports excluded until activation. Livewire includes Alpine. Font inventory is not a claim all fonts download.',
        manifest_sha256: createHash('sha256').update(manifestBytes).digest('hex'),
        runtime, entries,
        woff2_inventory: fonts.map(file => ({ file, ...measure(resolve(directory, file)) })),
    };
}

export function enforceAssetBudgets(report, budgets) {
    for (const [key, budget] of Object.entries(budgets)) {
        const entry = key === 'livewire_runtime' ? report.runtime : report.entries[key];
        if (!entry) throw new Error(`Required built asset missing: ${key}`);
        for (const metric of ['bytes', 'gzip_bytes']) {
            if (entry[metric] > budget[metric]) throw new Error(`${key} ${metric}=${entry[metric]} exceeds ${budget[metric]}`);
        }
    }
    const galaxy = report.entries['resources/js/galaxy.js'];
    const renderer = 'resources/js/galaxy-renderer.js';
    if (!galaxy.dynamic_imports.includes(renderer) || galaxy.imports.includes(renderer)) throw new Error('Galaxy renderer must remain a deferred dynamic import.');
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
    const report = measureAssets(root);
    const budgets = JSON.parse(readFileSync(resolve(root, 'tests/fixtures/performance/asset-budgets.json')));
    enforceAssetBudgets(report, budgets);
    process.stdout.write(JSON.stringify(report, null, 2) + '\n');
}
