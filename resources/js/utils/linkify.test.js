import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { linkifyText } from './linkify.js';

describe('linkifyText', () => {
    it('returns empty array for empty input', () => {
        assert.deepEqual(linkifyText(''), []);
        assert.deepEqual(linkifyText(null), []);
        assert.deepEqual(linkifyText(undefined), []);
    });

    it('returns single text segment when no URLs', () => {
        assert.deepEqual(linkifyText('hello world'), [
            { type: 'text', value: 'hello world' },
        ]);
    });

    it('linkifies https URLs', () => {
        assert.deepEqual(linkifyText('see https://example.com/path now'), [
            { type: 'text', value: 'see ' },
            { type: 'link', value: 'https://example.com/path', href: 'https://example.com/path' },
            { type: 'text', value: ' now' },
        ]);
    });

    it('linkifies www URLs with https href', () => {
        assert.deepEqual(linkifyText('go www.example.com please'), [
            { type: 'text', value: 'go ' },
            { type: 'link', value: 'www.example.com', href: 'https://www.example.com' },
            { type: 'text', value: ' please' },
        ]);
    });

    it('strips trailing punctuation from URLs', () => {
        assert.deepEqual(linkifyText('see https://example.com.'), [
            { type: 'text', value: 'see ' },
            { type: 'link', value: 'https://example.com', href: 'https://example.com' },
            { type: 'text', value: '.' },
        ]);
    });

    it('does not linkify javascript: or bare text', () => {
        assert.deepEqual(linkifyText('javascript:alert(1)'), [
            { type: 'text', value: 'javascript:alert(1)' },
        ]);
    });

    it('preserves newlines in text segments', () => {
        assert.deepEqual(linkifyText('a\nhttps://x.com\nb'), [
            { type: 'text', value: 'a\n' },
            { type: 'link', value: 'https://x.com', href: 'https://x.com' },
            { type: 'text', value: '\nb' },
        ]);
    });
});
