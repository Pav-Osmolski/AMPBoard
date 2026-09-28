const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/modules/vhosts.js'), 'utf8').replace(/^export /gm, '');
async function scenario(ok, message, confirmed = true, networkError = false) {
 const clicks = [], alerts = [], requests = []; let restarted = 0;
 const button = { dataset: { generateCert: 'example.test' }, addEventListener: (event, fn) => clicks.push(fn) };
 const context = vm.createContext({
  document: { readyState: 'complete', addEventListener() {}, querySelectorAll: selector => selector === '[data-generate-cert]' ? [button] : [], getElementById: id => id === 'restart-apache' ? { click: () => ++restarted } : null },
  window: { BASE_URL: '/ampboard/' }, confirm: () => confirmed, alert: message => alerts.push(message),
  fetch: async url => { requests.push(url); if (networkError) throw Error('offline'); return { ok, text: async () => message }; }
 });
 vm.runInContext(source + '\nsetupVhostCertButtons();', context);
 clicks[0](); await new Promise(setImmediate);
 assert.equal(requests.length, confirmed ? 1 : 0);
 if (confirmed) {
  assert.equal(requests[0], '/ampboard/utils/generate_cert.php?name=example.test');
  assert.equal(alerts[0], networkError ? 'Failed to run cert script.' : message);
 }
 assert.equal(restarted, confirmed && !networkError && ok && message.includes('successfully') ? 1 : 0);
}
(async () => {
 await scenario(false, 'successfully printed before failure');
 await scenario(true, 'Certificate created successfully');
 await scenario(true, '[OK] Certificate and key created');
 await scenario(true, 'cancelled', false);
 await scenario(false, '', true, true);
 console.log('PASS certificate UI response handling');
})().catch(error => { console.error(error); process.exitCode = 1; });
