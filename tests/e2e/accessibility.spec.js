import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

async function assertWcagAa(page) {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();

    const failures = results.violations
        .filter(({ impact }) => ['serious', 'critical'].includes(impact))
        .flatMap(({ id, nodes }) => nodes.map((node) => ({ id, target: node.target, html: node.html })));

    expect(failures).toEqual([]);
}

async function login(page, username, password) {
    await page.goto('/dang-nhap');
    await page.getByLabel('Tên đăng nhập').fill(username);
    await page.getByLabel('Mật khẩu').fill(password);
    await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click();
}

for (const route of ['/', '/dang-nhap', '/dang-ky', '/quen-mat-khau', '/dat-lai-mat-khau/e2e-token?email=e2e_customer%40example.test', '/san-pham/bo-boi-e2e-xanh-bien', '/duong-dan-khong-ton-tai']) {
    test(`không có lỗi accessibility nghiêm trọng tại ${route}`, async ({ page }) => {
        await page.goto(route);
        await assertWcagAa(page);
    });
}

test('các màn hình tài khoản khách đạt WCAG AA nghiêm trọng', async ({ page }) => {
    await login(page, 'e2e_customer', 'Customer!Pass123');

    for (const route of ['/tai-khoan', '/gio-hang', '/don-hang-cua-toi']) {
        await page.goto(route);
        await assertWcagAa(page);
    }
});

test('các màn hình quản trị chính đạt WCAG AA nghiêm trọng', async ({ page }) => {
    await login(page, 'e2e_admin', 'Admin!Pass12345');

    for (const route of ['/admin', '/admin/orders', '/admin/products', '/admin/users']) {
        await page.goto(route);
        await assertWcagAa(page);
    }
});

test('trang chủ và catalog đạt WCAG AA nghiêm trọng ở dark mode', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'dark', reducedMotion: 'reduce' });

    for (const route of ['/', '/san-pham']) {
        await page.goto(route);
        await assertWcagAa(page);
    }
});

test('điều hướng bàn phím có skip-link và focus rõ ràng', async ({ page, browserName }) => {
    test.skip(browserName === 'webkit', 'WebKit mô phỏng thiết lập macOS không dùng Tab để chọn liên kết.');
    await page.goto('/');
    await page.keyboard.press('Tab');
    const skipLink = page.getByRole('link', { name: 'Chuyển đến nội dung chính' });
    await expect(skipLink).toBeFocused();
    await expect(skipLink).toBeVisible();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/#main-content$/);
});
