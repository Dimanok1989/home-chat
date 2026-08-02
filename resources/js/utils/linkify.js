const URL_RE = /https?:\/\/[^\s<]+|www\.[^\s<]+/gi;
const TRAILING_PUNCT_RE = /[.,;:!?)\]]+$/;

/**
 * Split plain text into text/link segments for safe Vue rendering (no v-html).
 *
 * @param {string|null|undefined} text
 * @returns {Array<{type:'text',value:string}|{type:'link',value:string,href:string}>}
 */
export function linkifyText(text) {
    if (text == null || text === '') {
        return [];
    }

    const source = String(text);
    const segments = [];
    let lastIndex = 0;

    for (const match of source.matchAll(URL_RE)) {
        const raw = match[0];
        const start = match.index;

        let url = raw;
        let trailing = '';
        const punct = url.match(TRAILING_PUNCT_RE);
        if (punct) {
            trailing = punct[0];
            url = url.slice(0, -trailing.length);
        }

        if (!url) {
            continue;
        }

        if (start > lastIndex) {
            segments.push({ type: 'text', value: source.slice(lastIndex, start) });
        }

        const href = /^https?:\/\//i.test(url) ? url : `https://${url}`;
        segments.push({ type: 'link', value: url, href });

        lastIndex = start + url.length;
        if (trailing) {
            // trailing punctuation stays as text; continue loop — do not skip it via lastIndex on raw
            // lastIndex already points at start of trailing within raw; emit trailing as text before next match
            segments.push({ type: 'text', value: trailing });
            lastIndex = start + raw.length;
        }
    }

    if (lastIndex < source.length) {
        segments.push({ type: 'text', value: source.slice(lastIndex) });
    }

    return segments;
}
