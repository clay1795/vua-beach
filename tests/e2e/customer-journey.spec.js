import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';

const appUrl = 'http://127.0.0.1:8010';
const vnpaySecret = 'e2e-vnpay-secret';

function signedVnpayQuery(data) {
    const sorted = new URLSearchParams();
    Object.keys(data).sort().forEach((key) => sorted.set(key, String(data[key])));
    sorted.set('vnp_SecureHash', createHmac('sha512', vnpaySecret).update(sorted.toString()).digest('hex'));

    return sorted.toString();
}

async function login(page, username, password) {
    await page.goto('/dang-nhap');
    await page.getByLabel('Tên đăng nhập').fill(username);
    await page.getByLabel('Mật khẩu').fill(password);
    const responsePromise = page.waitForResponse((response) =>
        response.request().method() === 'POST' && new URL(response.url()).pathname === '/dang-nhap',
    );
    await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click();
    const response = await responsePromise;

    expect(response.status(), `Đăng nhập ${username} phải redirect thành công`).toBe(302);
    await expect(page).not.toHaveURL(/\/dang-nhap(?:$|\?)/);
}

async function logout(page) {
    if (!page.url().includes('/admin')) {
        await page.getByRole('button', { name: 'Mở menu tài khoản' }).click();
    } else if (await page.getByRole('button', { name: 'Mở menu' }).isVisible()) {
        await page.getByRole('button', { name: 'Mở menu' }).click();
    }
    await page.getByRole('button', { name: 'Đăng xuất' }).click();
    await expect(page).toHaveURL(/\/$/);
}

test('mua hàng COD, hoàn tất giao hàng và hoàn trả toàn phần', async ({ page }, testInfo) => {
    await login(page, 'e2e_customer', 'Customer!Pass123');
    await page.goto('/san-pham/bo-boi-e2e-xanh-bien');
    await page.getByRole('button', { name: 'Xanh biển', exact: true }).click();
    await page.getByRole('button', { name: 'Size M' }).click();
    await page.getByRole('button', { name: /Thêm vào giỏ/ }).click();
    await expect(page).toHaveURL(/gio-hang/);
    await page.getByRole('button', { name: /Thanh toán sản phẩm đã chọn/ }).click();
    await expect(page).toHaveURL(/thanh-toan/);
    await page.getByLabel('Địa chỉ nhận hàng').fill('123 Đường Biển, Đà Nẵng');
    await page.getByLabel(/Tôi đã đọc và đồng ý/).check();
    await page.getByRole('button', { name: /Xác nhận đặt hàng/ }).click();
    await expect(page.getByText('Đặt hàng thành công')).toBeVisible();
    const orderUrl = page.url();
    const orderCode = (await page.locator('.order-detail-head h1').textContent()).trim();
    await expect(page.getByText('Chờ xác nhận', { exact: true }).first()).toBeVisible();

    await logout(page);
    await login(page, 'e2e_admin', 'Admin!Pass12345');
    await page.goto('/admin/orders');
    const row = page.getByRole('row').filter({ hasText: orderCode });
    await row.getByRole('link', { name: 'Xem đơn' }).click();
    for (const transition of [
        ['confirmed', 'Đã xác nhận'],
        ['shipping', 'Đang giao'],
        ['completed', 'Hoàn thành'],
    ]) {
        await page.locator('select[name="status"]').selectOption(transition[0]);
        await page.getByRole('button', { name: 'Cập nhật trạng thái' }).click();
        await expect(page.locator('.status').filter({ hasText: transition[1] })).toBeVisible();
    }

    await logout(page);
    await login(page, 'e2e_customer', 'Customer!Pass123');
    await page.goto(orderUrl);
    await page.getByRole('link', { name: 'Yêu cầu đổi trả / đổi size' }).click();
    await page.getByLabel('Yêu cầu xử lý sản phẩm này').check();
    await page.getByLabel('Hoàn hàng').check();
    await page.getByLabel('Lý do').selectOption({ label: 'Không đúng mô tả' });
    await page.getByRole('button', { name: 'Gửi yêu cầu đổi trả' }).click();
    await expect(page.getByText('Đã gửi yêu cầu đổi trả')).toBeVisible();

    await logout(page);
    await login(page, 'e2e_admin', 'Admin!Pass12345');
    await page.goto('/admin/returns');
    await page.getByRole('row').filter({ hasText: orderCode }).getByRole('link', { name: 'Xử lý' }).click();
    for (const action of [
        ['Duyệt yêu cầu', 'Đã duyệt'],
        ['Đã nhận hàng hoàn', 'Đã nhận hàng'],
        ['Hoàn tất xử lý', 'Hoàn tất'],
    ]) {
        await page.getByRole('button', { name: action[0] }).click();
        await expect(page.getByText(action[1], { exact: true }).first()).toBeVisible();
    }

    await page.screenshot({ path: `output/playwright/${testInfo.project.name}-journey-final.png`, fullPage: true });
});

test('thanh toán VNPAY chỉ thành công qua IPN ký hợp lệ và xử lý lặp đúng một lần', async ({ page }) => {
    await login(page, 'e2e_customer', 'Customer!Pass123');
    await page.goto('/san-pham/bo-boi-e2e-xanh-bien');
    await page.getByRole('button', { name: 'Xanh biển', exact: true }).click();
    await page.getByRole('button', { name: 'Size M' }).click();
    await page.getByRole('button', { name: /Thêm vào giỏ/ }).click();
    await page.getByRole('button', { name: /Thanh toán sản phẩm đã chọn/ }).click();
    await page.getByLabel('Địa chỉ nhận hàng').fill('123 Đường Biển, Đà Nẵng');
    await page.getByLabel(/VNPAY-QR/).check();
    await page.getByLabel(/Tôi đã đọc và đồng ý/).check();
    const gatewayRequestPromise = page.waitForRequest((request) =>
        request.url().startsWith('https://payments.example.test/pay?'),
    );
    await page.getByRole('button', { name: /Xác nhận đặt hàng/ }).click({ noWaitAfter: true });
    const gatewayUrl = (await gatewayRequestPromise).url();

    expect(gatewayUrl).toMatch(/^https:\/\/payments\.example\.test\/pay\?/);
    const gatewayParams = new URL(gatewayUrl).searchParams;
    expect(gatewayParams.get('vnp_TmnCode')).toBe('E2ETMN');
    expect(gatewayParams.get('vnp_TxnRef')).toMatch(/^VB-/);
    expect(gatewayParams.get('vnp_SecureHash')).toHaveLength(128);

    const callback = {
        vnp_TmnCode: gatewayParams.get('vnp_TmnCode'),
        vnp_TxnRef: gatewayParams.get('vnp_TxnRef'),
        vnp_Amount: gatewayParams.get('vnp_Amount'),
        vnp_ResponseCode: '00',
        vnp_TransactionStatus: '00',
        vnp_TransactionNo: `E2E${Date.now()}`,
        vnp_BankCode: 'NCB',
    };
    const signedQuery = signedVnpayQuery(callback);
    const firstIpn = await page.request.get(`${appUrl}/thanh-toan/vnpay/ipn?${signedQuery}`);
    expect(firstIpn.ok()).toBeTruthy();
    expect(await firstIpn.json()).toMatchObject({ RspCode: '00' });

    const replayedIpn = await page.request.get(`${appUrl}/thanh-toan/vnpay/ipn?${signedQuery}`);
    expect(replayedIpn.ok()).toBeTruthy();
    expect(await replayedIpn.json()).toMatchObject({ RspCode: '00' });

    const callbackPage = await page.context().newPage();
    await callbackPage.goto(`${appUrl}/thanh-toan/vnpay/ket-qua?${signedQuery}`);
    await expect(callbackPage).toHaveURL(/\/don-hang\/\d+$/);
    await expect(callbackPage.getByText('VNPAY đã xác nhận thanh toán thành công.')).toBeVisible();
    await expect(callbackPage.getByText('Đã thanh toán', { exact: true })).toBeVisible();
    await expect(callbackPage.getByText(callback.vnp_TransactionNo, { exact: true })).toBeVisible();
    await callbackPage.close();
});

test('đổi size một phần giữ hàng thay thế và hoàn tất qua toàn bộ giao diện', async ({ page }) => {
    await login(page, 'e2e_customer', 'Customer!Pass123');
    await page.goto('/san-pham/bo-boi-e2e-xanh-bien');
    await page.getByRole('button', { name: 'Xanh biển', exact: true }).click();
    await page.getByRole('button', { name: 'Size M' }).click();
    await page.getByLabel('Số lượng').fill('2');
    await page.getByRole('button', { name: /Thêm vào giỏ/ }).click();
    await page.getByRole('button', { name: /Thanh toán sản phẩm đã chọn/ }).click();
    await page.getByLabel('Địa chỉ nhận hàng').fill('123 Đường Biển, Đà Nẵng');
    await page.getByLabel(/Tôi đã đọc và đồng ý/).check();
    await page.getByRole('button', { name: /Xác nhận đặt hàng/ }).click();

    const orderUrl = page.url();
    const orderCode = (await page.locator('.order-detail-head h1').textContent()).trim();
    await logout(page);
    await login(page, 'e2e_admin', 'Admin!Pass12345');
    await page.goto('/admin/orders');
    await page.getByRole('row').filter({ hasText: orderCode }).getByRole('link', { name: 'Xem đơn' }).click();
    for (const status of ['confirmed', 'shipping', 'completed']) {
        await page.locator('select[name="status"]').selectOption(status);
        await page.getByRole('button', { name: 'Cập nhật trạng thái' }).click();
    }

    await logout(page);
    await login(page, 'e2e_customer', 'Customer!Pass123');
    await page.goto(orderUrl);
    await page.getByRole('link', { name: 'Yêu cầu đổi trả / đổi size' }).click();
    await page.getByLabel('Yêu cầu xử lý sản phẩm này').check();
    await page.getByLabel('Số lượng đổi/trả').fill('1');
    await page.getByLabel('Size mong muốn').selectOption('L');
    await page.getByLabel('Lý do').selectOption({ label: 'Không vừa size' });
    await page.getByRole('button', { name: 'Gửi yêu cầu đổi trả' }).click();
    await expect(page.getByText('Đã gửi yêu cầu đổi trả')).toBeVisible();

    await logout(page);
    await login(page, 'e2e_admin', 'Admin!Pass12345');
    await page.goto('/admin/returns');
    await page.getByRole('row').filter({ hasText: orderCode }).getByRole('link', { name: 'Xử lý' }).click();
    const replacement = page.locator('select[name^="replacement_variant_id"]');
    const replacementValue = await replacement.locator('option').filter({ hasText: 'Xanh biển · Size L' }).getAttribute('value');
    await replacement.selectOption(replacementValue);
    await page.getByRole('button', { name: 'Duyệt yêu cầu' }).click();
    await expect(page.getByText('Đã duyệt', { exact: true }).first()).toBeVisible();
    await page.getByRole('button', { name: 'Đã nhận hàng hoàn' }).click();
    await expect(page.getByText('Đã nhận hàng', { exact: true }).first()).toBeVisible();
    await page.getByRole('button', { name: 'Hoàn tất xử lý' }).click();
    await expect(page.getByText('Hoàn tất', { exact: true }).first()).toBeVisible();

    await logout(page);
    await login(page, 'e2e_customer', 'Customer!Pass123');
    await page.goto(orderUrl);
    await expect(page.getByText('Đã hoàn tất', { exact: true })).toBeVisible();
});
