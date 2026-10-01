import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const blade = readFileSync(new URL('../../resources/views/livewire/search-page.blade.php', import.meta.url), 'utf8');
const script = blade.match(/@script\s*<script>([\s\S]*?)<\/script>\s*@endscript/)[1];

test('live search titles stay literal and a detached search cannot overwrite another page title', () => {
    const root = { isConnected: true }, document = { title: 'Search: Saturn · Public Universe' };
    let update;
    runInNewContext(script, { document, $wire: { $el: root, on(name, callback) {
        assert.equal(name, 'search-title-updated'); update = callback;
    } } });
    update({ title: 'Search: <script>alert(1)</script> · Public Universe' });
    assert.equal(document.title, 'Search: <script>alert(1)</script> · Public Universe');
    update({ title: 'Search · Public Universe' });
    assert.equal(document.title, 'Search · Public Universe');
    root.isConnected = false;
    document.title = 'Galaxy explorer · Public Universe';
    update({ title: 'Search: late result · Public Universe' });
    assert.equal(document.title, 'Galaxy explorer · Public Universe');
});
