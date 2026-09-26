import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { test } from 'node:test';
import assert from 'node:assert/strict';

// Exercise the actual Blade script with controlled async replies (no merchant calls).
const template = readFileSync(new URL('../../resources/views/shop/checkout.blade.php', import.meta.url), 'utf8');
const source = template.split('        const province = document.getElementById')[1].split('        @endif')[0];
const script = 'const province = document.getElementById' + source.replace(/\{\{[^}]+\}\}/g, 'endpoint');

function harness() {
    const selects = Object.fromEntries(['provinceSelect', 'districtSelect', 'wardSelect'].map(id => [id, {
        value: '', disabled: false, options: [], handlers: {},
        replaceChildren(...items) { this.options = items; this.value = ''; },
        addEventListener(event, handler) { this.handlers[event] = handler; },
        dispatchEvent(event) { return this.handlers[event.type]?.(); },
    }]));
    const pending = [];
    const feeText = { textContent: '' }, totalText = { textContent: '' };
    const context = {
        document: { getElementById: id => selects[id] || null },
        window: {}, Option: function (text, value) { this.text = text; this.value = value; },
        Event: function (type) { this.type = type; },
        fetch: (url, init) => new Promise(resolve => pending.push({
            url, init,
            reply(data, ok = true) { resolve({ ok, json: async () => data }); },
        })),
        feeText, totalText, weight: 300, shippingFee: 30000,
        showTotal() { feeText.textContent = String(context.shippingFee); },
    };
    runInNewContext(script, context);
    return { ...selects, pending, context, feeText, totalText };
}
const tick = () => new Promise(resolve => setImmediate(resolve));
const provinces = { code: 200, data: [{ ProvinceID: 1, ProvinceName: 'A' }, { ProvinceID: 2, ProvinceName: 'B' }] };

test('latest province wins even when old request completes last', async () => {
    const h = harness();
    h.pending.shift().reply(provinces);
    await tick();
    h.provinceSelect.value = '1'; h.provinceSelect.handlers.change();
    const old = h.pending.shift();
    h.provinceSelect.value = '2'; h.provinceSelect.handlers.change();
    h.pending.shift().reply({ code: 200, data: [{ DistrictID: 22, DistrictName: 'New' }] });
    await tick();
    old.reply({ code: 200, data: [{ DistrictID: 11, DistrictName: 'Old' }] });
    await tick();
    assert.equal(h.districtSelect.options[1].value, 22);
    assert.equal(h.wardSelect.disabled, true);
});

test('old fee cannot return after parent location changes', async () => {
    const h = harness();
    h.pending.shift().reply(provinces); await tick();
    h.districtSelect.value = '11'; h.wardSelect.value = '111';
    h.wardSelect.handlers.change();
    const fee = h.pending.shift();
    h.provinceSelect.value = '2'; h.provinceSelect.handlers.change();
    fee.reply({ code: 200, data: { total: 99999 } }); await tick();
    assert.equal(h.context.shippingFee, null);
    assert.equal(h.totalText.textContent, 'Chờ phí giao hàng');
});

test('district failure clears child values and a new selection recovers', async () => {
    const h = harness();
    h.pending.shift().reply(provinces); await tick();
    h.provinceSelect.value = '1'; h.districtSelect.value = 'stale'; h.wardSelect.value = 'stale';
    h.provinceSelect.handlers.change();
    h.pending.shift().reply({}, false); await tick();
    assert.equal(h.districtSelect.value, '');
    assert.equal(h.wardSelect.value, '');
    assert.equal(h.districtSelect.disabled, true);
    h.provinceSelect.value = '2'; h.provinceSelect.handlers.change();
    h.pending.shift().reply({ code: 200, data: [{ DistrictID: 22, DistrictName: 'Recovered' }] });
    await tick();
    assert.equal(h.districtSelect.disabled, false);
    assert.equal(h.districtSelect.options[1].text, 'Recovered');
});
