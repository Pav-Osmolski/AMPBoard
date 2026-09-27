// Execute the actual log UI module against a small DOM/fetch fixture.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/modules/error.js'), 'utf8').replace(/^export /gm, '');
async function scenario(enabled, present = true) {
  const callbacks = [], intervals = [], requests = [], elements = {};
  for (const kind of ['apache', 'php']) {
    for (const id of [`${kind}-error-log-section`, `toggle-${kind}-error-log`, `${kind}-error-log`]) {
      elements[id] = { classList: { contains: () => false, toggle() {} }, addEventListener() {}, replaceChildren(child) { this.child = child; } };
      Object.defineProperty(elements[id], 'innerHTML', { set() { throw new Error('Log text must never use innerHTML'); } });
    }
  }
  const payload = '<img src=x onerror="alert(1)">&\n<script>unsafe</script>';
  const context = vm.createContext({
    document: {
      body: { getAttribute: () => enabled ? 'true' : 'false' },
      getElementById: id => present ? elements[id] : null,
      addEventListener: (event, callback) => callbacks.push(callback),
      createElement: tag => ({ tag, textContent: '', className: '' })
    },
    window: { BASE_URL: '/ampboard/' },
    fetch: async url => { requests.push(url); return { ok: true, text: async () => url.includes('apache') ? payload : ' \n' }; },
    setInterval: (callback, delay) => intervals.push({ callback, delay })
  });
  vm.runInContext(source + '\ninitApacheErrorLog(); initPhpErrorLog();', context);
  callbacks.forEach(callback => callback());
  await new Promise(setImmediate);
  if (!enabled || !present) { assert.equal(requests.length, 0); assert.equal(intervals.length, 0); return; }
  assert.deepEqual(requests, ['/ampboard/utils/apache_error_log.php', '/ampboard/utils/php_error_log.php']);
  assert.equal(elements['apache-error-log'].child.textContent, payload);
  assert.equal(elements['apache-error-log'].child.tag, 'code');
  assert.equal(elements['php-error-log'].child.className, 'muted');
  assert.match(elements['php-error-log'].child.textContent, /No errors logged/);
  assert.equal(intervals.length, 2);
  assert.ok(intervals.every(timer => timer.delay === 3000));
  intervals.forEach(timer => timer.callback());
  await new Promise(setImmediate);
  assert.equal(requests.length, 4);
  assert.equal(elements['apache-error-log'].child.textContent, payload);
}
(async () => { await scenario(true); await scenario(false); await scenario(true, false); console.log('PASS log UI text rendering and refresh'); })().catch(error => { console.error(error); process.exitCode = 1; });
