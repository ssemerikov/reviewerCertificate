const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

test('initializing a completed review does not call a nonexistent availability endpoint', () => {
    const requests = [];
    const document = {};
    const element = {
        length: 0,
        on() { return this; },
        ready(callback) { callback(); },
        each(callback) { callback.call(this); },
        data(name) { return name === 'review-id' ? 7 : true; },
    };
    const $ = () => element;
    $.ajax = (options) => requests.push(options.url);
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../js/certificate.js'), 'utf8'), {
        jQuery: $, document, window: {}, pkp: { registry: { get: () => 'https://example.test' } }, console,
    });
    assert.deepEqual(requests, []);
});
