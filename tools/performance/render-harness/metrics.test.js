import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createMetrics } from './metrics.js';
function setup({ supported = true } = {}) {
    let time = 0, id = 0, disjoint = false, lost = false, ready = false;
    const jobs = new Map(), deleted = [], ext = { TIME_ELAPSED_EXT: 1, GPU_DISJOINT_EXT: 2 };
    const gl = { CURRENT_QUERY: 3, QUERY_RESULT_AVAILABLE: 4, QUERY_RESULT: 5,
        getExtension: () => supported ? ext : null, getQuery: () => null, createQuery: () => ({ id: ++id }),
        beginQuery() {}, endQuery() {}, deleteQuery: query => deleted.push(query), isContextLost: () => lost,
        getParameter: () => { const value = disjoint; disjoint = false; return value; },
        getQueryParameter: (_, kind) => kind === 4 ? ready : 2500000 };
    const renderer = { getContext: () => gl, info: { memory: { geometries: 4, textures: 0 }, programs: [{}] } };
    const metrics = createMetrics({ now: () => time, schedule: fn => { const key = ++id; jobs.set(key, fn); return key; }, cancel: key => jobs.delete(key) });
    return { metrics, renderer, deleted, jobs, advance: value => { time += value; },
        draw: () => metrics.render(renderer, () => { time += 3; }),
        poll: () => { const tasks = [...jobs.values()]; jobs.clear(); tasks.forEach(fn => fn()); },
        ready: () => { ready = true; }, disjoint: () => { disjoint = true; }, lost: () => { lost = true; } };
}
test('CPU submission and asynchronous GPU duration remain different measurements', () => {
    const h = setup(); h.draw();
    assert.equal(h.metrics.snapshot().draws[0].cpuCallMs, 3);
    assert.equal(h.metrics.snapshot().draws[0].gpuMs, null);
    h.ready(); h.poll();
    assert.equal(h.metrics.snapshot().draws[0].gpuMs, 2.5);
    assert.equal(h.metrics.snapshot().draws[0].gpuStatus, 'measured');
    assert.equal(h.jobs.size, 0);
});
test('one disjoint signal invalidates all outstanding queries even when the flag clears on read', () => {
    const h = setup(); h.draw(); h.draw(); h.ready(); h.disjoint(); h.poll();
    assert.deepEqual(h.metrics.snapshot().draws.map(row => [row.gpuStatus, row.gpuMs]), [['disjoint', null], ['disjoint', null]]);
    assert.equal(h.deleted.length, 2); assert.equal(h.jobs.size, 0);
});
test('unsupported timing is unknown rather than zero and never polls', () => {
    const h = setup({ supported: false }); h.draw();
    assert.equal(h.metrics.snapshot().draws[0].gpuStatus, 'unsupported');
    assert.equal(h.metrics.snapshot().draws[0].gpuMs, null); assert.equal(h.jobs.size, 0);
});
test('timeouts and context loss release pending queries without a continuing idle loop', () => {
    for (const reason of ['timeout', 'context_lost']) {
        const h = setup(); h.draw(); if (reason === 'timeout') h.advance(5001); else h.lost(); h.poll();
        assert.equal(h.metrics.snapshot().draws[0].gpuStatus, reason); assert.equal(h.jobs.size, 0); assert.equal(h.deleted.length, 1);
    }
});
test('dispose cancels measurement polling and records counters without claiming memory bytes', () => {
    const h = setup(); h.draw(); h.metrics.lifecycle('dispose-start', h.renderer);
    h.renderer.info.memory.geometries = 0; h.renderer.info.programs = [];
    h.metrics.lifecycle('renderer-disposed', h.renderer);
    assert.equal(h.jobs.size, 0); assert.equal(h.metrics.snapshot().draws[0].gpuStatus, 'disposed_before_result');
    assert.equal(h.metrics.snapshot().lifecycle[1].geometries, 0); assert.equal(h.metrics.snapshot().lifecycle[1].programs, 0);
});
test('measurement retention and GPU work are bounded while actual rendering continues', () => {
    const h = setup(); let renders = 0;
    for (let i = 0; i < 300; i++) h.metrics.render(h.renderer, () => { renders++; });
    assert.equal(renders, 300); assert.equal(h.metrics.snapshot().draws.length, 200);
    assert.equal(h.metrics.snapshot().totalRenderCalls, 300); assert.equal(h.metrics.snapshot().pendingGpuQueries, 16);
    h.metrics.lifecycle('dispose-start', h.renderer); assert.equal(h.jobs.size, 0);
});
test('failed rendering remains a failure rather than successful timing evidence', () => {
    const h = setup(); assert.throws(() => h.metrics.render(h.renderer, () => { throw new Error('draw failed'); }), /draw failed/);
    assert.equal(h.metrics.snapshot().draws[0].renderFailed, true);
    h.metrics.lifecycle('dispose-start', h.renderer);
});
