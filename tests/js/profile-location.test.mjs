import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { test } from 'node:test';
import assert from 'node:assert/strict';

// Exercise the actual profile Blade script with controlled async replies.
const template = readFileSync(new URL('../../resources/views/profile/edit.blade.php', import.meta.url), 'utf8');
const source = template.split('<script>')[1].split('</script>')[0]
    .replace(/\{\{[^}]+\}\}/g, 'endpoint');

function select() {
    return {
        value: '', disabled: false, options: [], handlers: {},
        replaceChildren(...items) { this.options = items; this.value = ''; },
        addEventListener(event, handler) { this.handlers[event] = handler; },
        get selectedOptions() { return this.options.filter(option => String(option.value) === String(this.value)); },
    };
}

function harness(dataset = {}) {
    const fields = {
        '[data-province]': select(), '[data-district]': select(), '[data-ward]': select(),
        '[data-province-id]': { value: '' }, '[data-province-name]': { value: '' },
        '[data-district-id]': { value: '' }, '[data-district-name]': { value: '' },
        '[data-ward-code]': { value: '' }, '[data-ward-name]': { value: '' },
    };
    const picker = { dataset, querySelector: selector => fields[selector] };
    const pending = [];
    const context = {
        document: { querySelectorAll: () => [picker] },
        Option: function (text, value) { this.text = text; this.value = value; },
        fetch: (url, init) => new Promise(resolve => pending.push({
            url, init,
            reply(data, ok = true) { resolve({ ok, json: async () => data }); },
        })),
    };
    runInNewContext(source, context);
    return { fields, pending };
}

const tick = () => new Promise(resolve => setImmediate(resolve));
const provinces = { code: 200, data: [
    { ProvinceID: 1, ProvinceName: 'Tỉnh A' },
    { ProvinceID: 2, ProvinceName: 'Tỉnh B' },
] };

test('latest province wins when the previous district request completes last', async () => {
    const h = harness();
    h.pending.shift().reply(provinces);
    await tick();

    const province = h.fields['[data-province]'];
    province.value = '1'; province.handlers.change();
    const oldRequest = h.pending.shift();
    province.value = '2'; province.handlers.change();
    h.pending.shift().reply({ code: 200, data: [{ DistrictID: 22, DistrictName: 'Quận mới' }] });
    await tick();
    oldRequest.reply({ code: 200, data: [{ DistrictID: 11, DistrictName: 'Quận cũ' }] });
    await tick();

    assert.equal(h.fields['[data-district]'].options[1].value, 22);
    assert.equal(h.fields['[data-district]'].options[1].text, 'Quận mới');
});

test('changing province immediately clears stale district and ward payload', async () => {
    const h = harness();
    h.pending.shift().reply(provinces);
    await tick();

    h.fields['[data-district-id]'].value = '11';
    h.fields['[data-district-name]'].value = 'Quận cũ';
    h.fields['[data-ward-code]'].value = '111';
    h.fields['[data-ward-name]'].value = 'Phường cũ';
    const province = h.fields['[data-province]'];
    province.value = '2'; province.handlers.change();

    assert.equal(h.fields['[data-province-id]'].value, '2');
    assert.equal(h.fields['[data-province-name]'].value, 'Tỉnh B');
    assert.equal(h.fields['[data-district-id]'].value, '');
    assert.equal(h.fields['[data-district-name]'].value, '');
    assert.equal(h.fields['[data-ward-code]'].value, '');
    assert.equal(h.fields['[data-ward-name]'].value, '');
});

test('district request failure is visible and a later selection recovers', async () => {
    const h = harness();
    h.pending.shift().reply(provinces);
    await tick();

    const province = h.fields['[data-province]'];
    province.value = '1'; province.handlers.change();
    h.pending.shift().reply({}, false);
    await tick();
    assert.equal(h.fields['[data-district]'].disabled, true);
    assert.match(h.fields['[data-district]'].options[0].text, /Lỗi tải/);

    province.value = '2'; province.handlers.change();
    h.pending.shift().reply({ code: 200, data: [{ DistrictID: 22, DistrictName: 'Đã phục hồi' }] });
    await tick();
    assert.equal(h.fields['[data-district]'].disabled, false);
    assert.equal(h.fields['[data-district]'].options[1].text, 'Đã phục hồi');
});
