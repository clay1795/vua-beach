import { expect, test } from '@playwright/test';

test('form chỉ khóa sau khi hợp lệ và chặn gửi lặp trong lúc chờ', async ({ page }) => {
    await page.goto('/dang-nhap');

    const form = page.locator('form').filter({ has: page.getByLabel('Tên đăng nhập') });
    const submit = form.locator('button[type="submit"], button:not([type])');
    await expect(submit).toHaveText('Đăng nhập');

    await submit.click();
    await expect(submit).toBeEnabled();
    await expect(form).not.toHaveAttribute('aria-busy', 'true');

    const dispatchResults = await form.evaluate((element) => {
        const button = element.querySelector('button[type="submit"], button:not([type])');
        const first = element.dispatchEvent(new SubmitEvent('submit', {
            bubbles: true,
            cancelable: true,
            submitter: button,
        }));
        const second = element.dispatchEvent(new SubmitEvent('submit', {
            bubbles: true,
            cancelable: true,
            submitter: button,
        }));

        return { first, second };
    });

    expect(dispatchResults).toEqual({ first: true, second: false });
    await expect(form).toHaveAttribute('aria-busy', 'true');
    await expect(submit).toBeDisabled();
    await expect(submit).toHaveAttribute('aria-busy', 'true');
    await expect(submit).toContainText('Đang xử lý…');
    await expect(submit.locator('.spinner-border')).toBeVisible();

    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow')));
    await expect(form).not.toHaveAttribute('aria-busy', 'true');
    await expect(submit).toBeEnabled();
    await expect(submit).toHaveText('Đăng nhập');
});

test('lỗi máy chủ được gắn đúng ô nhập thay vì chỉ hiện ở đầu trang', async ({ page }) => {
    await page.goto('/dang-nhap');
    await page.getByLabel('Tên đăng nhập').fill('e2e_customer');
    await page.getByLabel('Mật khẩu').fill('Customer!Pass123');
    await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click();

    await page.goto('/tai-khoan');
    const email = page.locator('#profile-email');
    await email.fill('admin@example.test');
    await page.getByRole('button', { name: 'Lưu thay đổi' }).click();

    await expect(page).toHaveURL(/\/tai-khoan$/);
    await expect(email).toHaveAttribute('aria-invalid', 'true');
    await expect(email).toHaveClass(/is-invalid/);
    await expect(email).toHaveAttribute('aria-describedby', /validation-error-/);
    await expect(page.locator('[data-generated-validation]').filter({ hasText: 'địa chỉ email này đã được sử dụng.' })).toBeVisible();
    await expect(email).toBeFocused();

    await email.fill('email-moi-e2e@example.test');
    await page.getByRole('button', { name: 'Lưu thay đổi' }).click();
    const currentPassword = page.locator('#profile-current-password');
    await expect(currentPassword).toHaveAttribute('aria-invalid', 'true');
    await expect(currentPassword).toHaveAttribute('autocomplete', 'current-password');
    await expect(page.locator('[data-generated-validation]').filter({ hasText: 'Vui lòng nhập mật khẩu hiện tại.' })).toBeVisible();
    await expect(currentPassword).toBeFocused();

    await page.locator('#address-label').fill('Nhà kiểm thử');
    await page.locator('#recipient-name').fill('Khách E2E');
    await page.locator('#address-phone').fill('012345678901234567890');
    await page.locator('#address-detail').fill('123 Đường Biển, Đà Nẵng');
    await page.getByRole('button', { name: 'Thêm địa chỉ' }).click();

    const addressPhone = page.locator('#address-phone');
    await expect(addressPhone).toHaveAttribute('aria-invalid', 'true');
    await expect(addressPhone).toHaveAttribute('aria-describedby', /validation-error-/);
    await expect(page.locator('#profile-phone')).not.toHaveAttribute('aria-invalid', 'true');
    await expect(addressPhone).toBeFocused();
});

test('bộ lọc catalog luôn hiện trên desktop và thu gọn trên mobile', async ({ page, isMobile }) => {
  await page.goto('/san-pham');

  const disclosure = page.locator('.catalog-filter-disclosure');
  const summary = disclosure.locator('summary');
  const filterForm = disclosure.locator('form');

  if (isMobile) {
    await expect(disclosure).not.toHaveAttribute('open', '');
    await expect(summary).toBeVisible();
    await summary.click();
    await expect(disclosure).toHaveAttribute('open', '');
    await expect(filterForm).toBeVisible();
  } else {
    await expect(disclosure).toHaveAttribute('open', '');
    await expect(summary).toBeHidden();
    await expect(filterForm).toBeVisible();
  }
});
