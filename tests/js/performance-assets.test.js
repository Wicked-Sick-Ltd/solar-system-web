import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { measureAssets, enforceAssetBudgets } from '../../tools/performance/assets.mjs';

test('initial asset measurement deduplicates shared imports and excludes the renderer until activation', () => {
    const root = mkdtempSync(join(tmpdir(), 'universe-assets-'));
    try {
        const directory = join(root, 'public/build'); mkdirSync(directory, { recursive: true });
        writeFileSync(join(directory, 'manifest.json'), JSON.stringify({
            'resources/js/galaxy.js': { file: 'main.js', isEntry: true, imports: ['shared', 'second'], dynamicImports: ['resources/js/galaxy-renderer.js'] },
            shared: { file: 'shared.js' }, second: { file: 'second.js', imports: ['shared'] },
            'resources/js/galaxy-renderer.js': { file: 'renderer.js', isDynamicEntry: true },
        }));
        for (const [file, length] of [['main.js', 10], ['shared.js', 20], ['second.js', 30], ['renderer.js', 1000]]) writeFileSync(join(directory, file), 'x'.repeat(length));
        const report = measureAssets(root);
        assert.equal(report.entries['resources/js/galaxy.js'].bytes, 60);
        assert.equal(report.entries['resources/js/galaxy-renderer.js'].bytes, 1000);
        const budgets = { 'resources/js/galaxy.js': { bytes: 60, gzip_bytes: 1000 } };
        enforceAssetBudgets(report, budgets);
        assert.throws(() => enforceAssetBudgets(report, { 'livewire_runtime': { bytes: 9999, gzip_bytes: 9999 } }), /missing/);
        assert.throws(() => enforceAssetBudgets(report, { 'resources/js/galaxy.js': { bytes: 59, gzip_bytes: 1000 } }), /exceeds/);
        report.entries['resources/js/galaxy.js'].imports.push('resources/js/galaxy-renderer.js');
        assert.throws(() => enforceAssetBudgets(report, budgets), /deferred/);
    } finally { rmSync(root, { recursive: true, force: true }); }
});
