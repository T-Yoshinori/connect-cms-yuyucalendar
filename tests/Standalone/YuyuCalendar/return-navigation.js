// Run: node tests/Standalone/YuyuCalendar/return-navigation.js
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert');
const template = fs.readFileSync(path.join(__dirname, '../../..', 'resources/views/plugins/user/calendars/yuyucalendar_return_scripts.blade.php'), 'utf8');
const storage = new Map();
const target = 'https://cms.example/site/portal?ycal_8_date=2026-10-03#frame-8';
const key = 'yuyucalendar_pending_return_1';
function render(mode, url = target, frame = 1) {
    let listener;
    let redirected;
    const window = {location: {replace: url => { redirected = url; }}};
    const script = template.replace(/<\/?script>/g, '')
        .replace(/{{ \(int\) \$frame_id }}/g, frame)
        .replace('@json($calendar_return_mode)', JSON.stringify(mode))
        .replace('@json($calendar_return_url ?? null)', JSON.stringify(url))
        .replace("@json(url('/'))", JSON.stringify('https://cms.example/site'));
    vm.runInNewContext(script, {window, URL, Date, Number,
        document: {forms: {['form_calendars_posts' + frame]: {addEventListener: (_, fn) => { listener = fn; }}}},
        sessionStorage: {getItem: key => storage.get(key), removeItem: key => storage.delete(key), setItem: (key, value) => storage.set(key, value)}});
    return {window, listener, redirected};
}
let edit = render('edit');
assert(!storage.has(key));
edit.listener();
assert(storage.has(key));
assert.strictEqual(render('index').redirected, target);
assert(!storage.has(key));
assert.strictEqual(render('index').redirected, undefined);
edit = render('edit');
edit.window.yuyuCalendarArmReturn_1(); // Temporary save uses programmatic submit.
assert(storage.has(key));
render('edit'); // Validation error returns to edit and clears pending navigation.
assert(!storage.has(key));
assert.strictEqual(render('edit', null).listener, undefined);
for (const pending of [
    {url: target, savedAt: Date.now() - 300001},
    {url: target, savedAt: Date.now() + 60000},
    {url: 'https://evil.example/', savedAt: Date.now()},
    {url: 'https://cms.example/site-other/', savedAt: Date.now()},
    {url: target, savedAt: 'invalid'},
]) {
    storage.set(key, JSON.stringify(pending));
    assert.strictEqual(render('index').redirected, undefined);
    assert(!storage.has(key));
}
storage.set(key, '{invalid');
assert.strictEqual(render('index').redirected, undefined);
assert(!storage.has(key));
edit = render('edit'); edit.listener();
assert.strictEqual(render('index', null, 2).redirected, undefined);
assert(storage.has(key));
assert.strictEqual(render('index').redirected, target);
console.log('PASS template navigation: save, temporary save, validation, direct use, one-shot, expiry, origin/path checks and source frame isolation');
