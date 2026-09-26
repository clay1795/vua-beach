import { expect, test } from '@playwright/test';

test('trình duyệt dùng AVIF và đổi đúng nguồn ảnh trong thư viện sản phẩm', async ({ page }) => {
    await page.goto('/san-pham/bo-boi-e2e-xanh-bien');

    const mainImage = page.locator('#mainProductImage');
    await expect(mainImage).toHaveAttribute('width', '1200');
    await expect(mainImage).toHaveAttribute('height', '1400');
    await expect.poll(() => mainImage.evaluate((image) => image.currentSrc)).toMatch(/bo-boi-xanh-phoi-trang\.avif$/);

    const thumbnails = page.locator('.product-thumbnail');
    await expect(thumbnails).toHaveCount(2);
    await thumbnails.nth(1).click();

    await expect(mainImage).toHaveAttribute('src', '/images/catalog/products/bo-lien-vay-den-phoi-ghi.jpg');
    await expect.poll(() => mainImage.evaluate((image) => image.currentSrc)).toMatch(/bo-lien-vay-den-phoi-ghi\.avif$/);
});

test('trang chi tiết ưu tiên tên sản phẩm trong màn hình đầu trên mobile', async ({ page }) => {
    await page.goto('/san-pham/bo-boi-e2e-xanh-bien');

    const viewport = page.viewportSize();
    expect(viewport).not.toBeNull();

    const imageBox = await page.locator('#mainProductImage').boundingBox();
    const headingBox = await page.getByRole('heading', { level: 1 }).boundingBox();

    expect(imageBox).not.toBeNull();
    expect(headingBox).not.toBeNull();

    if (viewport.width <= 767) {
        const dockBox = await page.locator('.mobile-dock').boundingBox();

        expect(dockBox).not.toBeNull();
        expect(imageBox.height).toBeLessThanOrEqual(361);
        expect(headingBox.y + headingBox.height).toBeLessThan(dockBox.y);
    } else {
        await expect(page.locator('#mainProductImage')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    }
});
