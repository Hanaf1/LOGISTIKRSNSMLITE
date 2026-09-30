const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const context = { window: {} };
const script = fs.readFileSync(path.join(root, 'js/admin/logistik.js'), 'utf8').split('/* Logistik Non Medis Script */')[0];
vm.runInNewContext(script, context);
const calendar = context.window.LogistikMonthlyWeeks;
assert.equal(calendar.ranges('2026-09').length, 5);
assert.equal(calendar.ranges('2026-09')[4].label, 'Minggu ke-5 (27 Sep–3 Okt)');
assert.equal(calendar.ranges('2026-09')[4].date, '2026-09-27');
assert.equal(calendar.ranges('2026-02').length, 4);
assert.equal(calendar.ranges('2026-08').length, 6);
assert.equal(calendar.ranges('2026-13').length, 0);
const backend = JSON.parse(execFileSync('php', [path.join(__dirname, 'monthly_weeks.php'), '--json'], { encoding: 'utf8' }));
for (const [period, ranges] of Object.entries(backend)) {
  const browser = calendar.ranges(period);
  assert.equal(browser.length, ranges.length, period);
  ranges.forEach((range, i) => assert.equal(browser[i].date, range.date, period));
}
console.log('PASS: frontend/backend cocok untuk 48 bulan; dropdown 4/5/6 minggu');

// Simulasi Selectator yang menyimpan option saat inisialisasi, seperti tema mLITE.
const elements = new Map();
function element() {
  return {
    value: '', options: [], events: {}, decorated: false, visibleOptions: [], rebuilds: 0,
    val(value) { if (arguments.length) { this.value = String(value); return this; } return this.value; },
    text(value) { this.label = value; return this; },
    empty() { this.options = []; return this; },
    append(option) { this.options.push(option); return this; },
    data() { return this.decorated; },
    on(events, callback) { events.split(' ').forEach(event => this.events[event] = callback); return this; },
    selectator(options) {
      if (options === 'destroy') { this.decorated = false; this.visibleOptions = []; }
      else { this.decorated = true; this.visibleOptions = this.options.slice(); this.rebuilds++; }
      return this;
    },
  };
}
function jquery(selector) {
  if (selector === '<option>') return element();
  if (!elements.has(selector)) elements.set(selector, element());
  return elements.get(selector);
}
jquery.fn = { selectator() {} };
const uiContext = {window:{jQuery:jquery}};
vm.runInNewContext(script, uiContext);
const period = jquery('#period'), select = jquery('#week'), picker = jquery('#picker');
period.val('2026-07');
select.selectator({}); // dropdown awal kosong, persis gejala yang dilaporkan.
uiContext.window.LogistikMonthlyWeeks.bind('#picker', '#period', '#week', false);
assert.equal(select.visibleOptions.length, 5);
select.val('5');
const rebuilds = select.rebuilds;
select.events.change();
assert.equal(select.val(), '5');
assert.equal(select.rebuilds, rebuilds, 'Memilih minggu jangan membangun ulang dropdown');
assert.equal(jquery('#sppb-tanggal-otomatis').label, 'Tanggal SPPB otomatis: 26/07/2026');
period.val('2026-02'); picker.events['dp.change']();
assert.equal(select.visibleOptions.length, 4);
assert.equal(select.val(), '1');
period.val('2026-09'); picker.events['dp.change']();
assert.equal(select.visibleOptions[4].label, 'Minggu ke-5 (27 Sep–3 Okt)');
console.log('PASS: dropdown kosong diperbarui; minggu bisa dipilih; perubahan bulan menyegarkan daftar');
