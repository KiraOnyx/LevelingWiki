/** Piecewise interpolation between actual milestone coordinates, not numeric floor counts. */
export function levelPosition(level, anchors) {
    const sorted = [...anchors].sort((a, b) => a.level - b.level);
    if (!sorted.length) throw new RangeError('At least one anchor is required');
    if (level <= sorted[0].level) return sorted[0].y;
    for (let i = 1; i < sorted.length; i++) {
        const low = sorted[i - 1], high = sorted[i];
        if (level <= high.level) return low.y + (high.y - low.y) * (level - low.level) / (high.level - low.level);
    }
    // The renderer adds an anchor for progress beyond the highest known reward.
    return sorted.at(-1).y;
}

/** Greedy nearest free interval. Real level coordinates remain independent of label displacement. */
export function placeLabel(target, height, occupied, minimum = 0, gap = 12) {
    const intervals = [...occupied].sort((a, b) => a.start - b.start);
    let cursor = minimum;
    const candidates = [];
    for (const block of intervals) {
        const end = block.start - gap;
        if (end - cursor >= height) candidates.push(Math.max(cursor, Math.min(target - height / 2, end - height)));
        cursor = Math.max(cursor, block.end + gap);
    }
    candidates.push(Math.max(cursor, target - height / 2));
    return candidates.sort((a, b) => Math.abs(a + height / 2 - target) - Math.abs(b + height / 2 - target))[0];
}
