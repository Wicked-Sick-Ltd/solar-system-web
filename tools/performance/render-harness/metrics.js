export function createMetrics({ now = () => performance.now(), schedule = fn => setTimeout(fn, 25), cancel = clearTimeout, changed = () => {} } = {}) {
    const draws = [], lifecycle = [], pending = new Set();
    let poll = null, total = 0, phase = 'initial', stopped = false;
    const memory = renderer => ({ geometries: renderer?.info?.memory?.geometries ?? null,
        textures: renderer?.info?.memory?.textures ?? null, programs: renderer?.info?.programs?.length ?? null });
    function finish(item, status, value = null) {
        item.row.gpuStatus = status;
        item.row.gpuMs = value;
        item.gl.deleteQuery(item.query);
        pending.delete(item);
    }
    function notify() { changed(); }
    function pollResults() {
        poll = null;
        for (const item of pending) {
            const { gl, ext, query } = item;
            if (gl.isContextLost()) finish(item, 'context_lost');
            else if (gl.getParameter(ext.GPU_DISJOINT_EXT)) {
                // A disjoint event invalidates every outstanding query in this context.
                for (const other of [...pending]) if (other.gl === gl) finish(other, 'disjoint');
            } else if (gl.getQueryParameter(query, gl.QUERY_RESULT_AVAILABLE)) {
                const ns = gl.getQueryParameter(query, gl.QUERY_RESULT);
                finish(item, Number.isFinite(ns) && ns >= 0 ? 'measured' : 'invalid', Number.isFinite(ns) && ns >= 0 ? ns / 1e6 : null);
            } else if (now() - item.started >= 5000) finish(item, 'timeout');
        }
        notify();
        if (pending.size) poll = schedule(pollResults);
    }
    return {
        phase(value) { phase = value; },
        snapshot() { return { phase, totalRenderCalls: total, retainedLimit: 200, draws, lifecycle, pendingGpuQueries: pending.size }; },
        lifecycle(stage, renderer) {
            if (lifecycle.length < 50) lifecycle.push({ stage, phase, atMs: now(), ...memory(renderer) });
            if (stage === 'dispose-start') {
                stopped = true;
                if (poll !== null) cancel(poll);
                poll = null;
                for (const item of [...pending]) finish(item, 'disposed_before_result');
            }
            notify();
        },
        render(renderer, render) {
            total++;
            if (draws.length >= 200 || stopped) return render();
            const row = { call: total, phase, cpuCallMs: null, gpuMs: null, gpuStatus: 'unsupported', ...memory(renderer) };
            draws.push(row);
            const gl = renderer.getContext();
            const ext = gl.getExtension('EXT_disjoint_timer_query_webgl2');
            let query = null;
            if (ext && typeof gl.createQuery === 'function') {
                row.gpuStatus = 'busy_or_bounded';
                if (pending.size < 16 && !gl.getQuery(ext.TIME_ELAPSED_EXT, gl.CURRENT_QUERY) && !gl.isContextLost()) {
                    // Clear any prior disjoint flag; invalidate outstanding measurements if set.
                    if (gl.getParameter(ext.GPU_DISJOINT_EXT)) for (const item of [...pending]) finish(item, 'disjoint');
                    query = gl.createQuery();
                    if (query) { gl.beginQuery(ext.TIME_ELAPSED_EXT, query); row.gpuStatus = 'pending'; }
                }
            }
            const start = now();
            try { return render(); }
            catch (error) { row.renderFailed = true; throw error; }
            finally {
                row.cpuCallMs = now() - start;
                Object.assign(row, memory(renderer));
                if (query) {
                    gl.endQuery(ext.TIME_ELAPSED_EXT);
                    pending.add({ gl, ext, query, row, started: now() });
                    if (poll === null) poll = schedule(pollResults);
                }
                notify();
            }
        },
    };
}

// Only the dedicated harness imports this module; no production instrumentation.
export const metrics = createMetrics({ changed: () => globalThis.dispatchEvent?.(new Event('harness-metrics')) });
