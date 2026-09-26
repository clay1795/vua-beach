<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class SampleCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            [
                'name' => 'Đồ bơi nữ',
                'slug' => 'do-boi-nu',
                'description' => 'Bikini, đồ bơi liền thân và các thiết kế kín đáo dành cho nữ.',
                'image_url' => '/images/catalog/categories/do-boi-nu.jpg',
            ],
            [
                'name' => 'Đồ bơi nam',
                'slug' => 'do-boi-nam',
                'description' => 'Quần bơi và trang phục bơi năng động dành cho nam.',
                'image_url' => '/images/catalog/categories/do-boi-nam.jpg',
            ],
            [
                'name' => 'Đồ bơi trẻ em',
                'slug' => 'do-boi-tre-em',
                'description' => 'Trang phục bơi thoải mái, nhiều màu sắc dành cho bé.',
                'image_url' => '/images/catalog/categories/do-boi-tre-em.jpg',
            ],
            [
                'name' => 'Phụ kiện bơi',
                'slug' => 'phu-kien-boi',
                'description' => 'Kính bơi, mũ bơi và phụ kiện cần thiết cho chuyến đi biển.',
                'image_url' => '/images/vua-beach-hero-v2.jpg',
            ],
        ])->mapWithKeys(function (array $data) {
            $category = Category::updateOrCreate(['slug' => $data['slug']], $data);

            return [$data['slug'] => $category];
        });

        $products = [
            [
                'name' => 'Bộ bơi dài tay hồng phối chân váy đen',
                'slug' => 'bo-boi-dai-tay-hong-phoi-chan-vay-den',
                'price' => 540000,
                'sale_price' => null,
                'image_url' => '/images/catalog/products/bo-boi-hong-chan-vay.jpg',
                'description' => 'Thiết kế dài tay kín đáo, khóa kéo tiện dụng và chân váy cạp cao giúp vận động thoải mái khi bơi hoặc đi biển.',
                'color' => 'Hồng phối đen',
            ],
            [
                'name' => 'Đồ bơi cộc tay liền thân quần short đen',
                'slug' => 'do-boi-coc-tay-lien-than-quan-short-den',
                'price' => 450000,
                'sale_price' => 419000,
                'image_url' => '/images/catalog/products/do-boi-lien-than-short-den.jpg',
                'description' => 'Dáng liền thân cộc tay kết hợp quần short thể thao, phù hợp học bơi, đi biển và các hoạt động ngoài trời.',
                'color' => 'Đen',
            ],
            [
                'name' => 'Bộ bơi liền tay dài phối ren',
                'slug' => 'bo-boi-lien-tay-dai-phoi-ren',
                'price' => 520000,
                'sale_price' => null,
                'image_url' => '/images/catalog/products/bo-lien-tay-dai-phoi-ren.jpg',
                'description' => 'Phom liền thân thanh lịch với phần tay ren nhẹ, tạo cảm giác kín đáo mà vẫn nữ tính.',
                'color' => 'Kem',
            ],
            [
                'name' => 'Bikini cạp cao quần váy họa tiết nhiệt đới',
                'slug' => 'bikini-cap-cao-quan-vay-hoa-tiet-nhiet-doi',
                'price' => 450000,
                'sale_price' => 399000,
                'image_url' => '/images/catalog/products/bikini-cap-cao-nhiet-doi.jpg',
                'description' => 'Bikini cạp cao phối quần váy họa tiết rực rỡ, tôn dáng và phù hợp cho kỳ nghỉ mùa hè.',
                'color' => 'Họa tiết nhiệt đới',
            ],
            [
                'name' => 'Bộ bơi dài tay quần đùi xanh phối trắng',
                'slug' => 'bo-boi-dai-tay-quan-dui-xanh-phoi-trang',
                'price' => 630000,
                'sale_price' => null,
                'image_url' => '/images/catalog/products/bo-boi-xanh-phoi-trang.jpg',
                'description' => 'Bộ bơi dài tay thể thao với quần đùi hai lớp, co giãn tốt và thuận tiện cho các hoạt động dưới nước.',
                'color' => 'Xanh phối trắng',
            ],
            [
                'name' => 'Bộ bơi liền váy dài tay đen phối ghi',
                'slug' => 'bo-boi-lien-vay-dai-tay-den-phoi-ghi',
                'price' => 550000,
                'sale_price' => 499000,
                'image_url' => '/images/catalog/products/bo-lien-vay-den-phoi-ghi.jpg',
                'description' => 'Thiết kế liền váy dài tay gọn gàng, gam đen ghi dễ mặc và phù hợp nhiều vóc dáng.',
                'color' => 'Đen phối ghi',
            ],
            [
                'name' => 'Bộ bơi liền tay dài đen tối giản',
                'slug' => 'bo-boi-lien-tay-dai-den-toi-gian',
                'price' => 480000,
                'sale_price' => null,
                'image_url' => '/images/catalog/products/bo-lien-tay-dai-den.jpg',
                'description' => 'Bộ bơi liền thân màu đen với đường cắt tối giản, nhanh khô và dễ phối cùng phụ kiện đi biển.',
                'color' => 'Đen',
            ],
            [
                'name' => 'Quần bơi nam cao cấp đen phối sóng',
                'slug' => 'quan-boi-nam-cao-cap-den-phoi-song',
                'price' => 520000,
                'sale_price' => null,
                'image_url' => '/images/catalog/products/quan-boi-nam-den-phoi-song.jpg',
                'description' => 'Quần bơi nam dáng thể thao, nền đen phối họa tiết sóng khỏe khoắn và chất liệu co giãn nhanh khô.',
                'color' => 'Đen phối sóng',
                'category_slug' => 'do-boi-nam',
                'sizes' => ['M', 'L', 'XL'],
            ],
            [
                'name' => 'Quần bơi nam cao cấp đen phối xanh',
                'slug' => 'quan-boi-nam-cao-cap-den-phoi-xanh',
                'price' => 520000,
                'sale_price' => 479000,
                'image_url' => '/images/catalog/products/quan-boi-nam-den-phoi-xanh.jpg',
                'description' => 'Thiết kế quần bơi nam đen phối xanh hiện đại, ôm vừa vặn và linh hoạt khi vận động dưới nước.',
                'color' => 'Đen phối xanh',
                'category_slug' => 'do-boi-nam',
                'sizes' => ['M', 'L', 'XL'],
            ],
            [
                'name' => 'Bộ rời bé trai tay dài kèm mũ',
                'slug' => 'bo-roi-be-trai-tay-dai-kem-mu',
                'price' => 300000,
                'sale_price' => null,
                'image_url' => '/images/catalog/products/bo-roi-be-trai-tay-dai-kem-mu.jpg',
                'description' => 'Bộ bơi tay dài dành cho bé trai đi kèm mũ, giúp che nắng tốt và thoải mái trong các buổi học bơi.',
                'color' => 'Xanh',
                'category_slug' => 'do-boi-tre-em',
                'sizes' => ['4', '6', '8'],
            ],
            [
                'name' => 'Bộ rời bé trai xanh phối cam',
                'slug' => 'bo-roi-be-trai-xanh-phoi-cam',
                'price' => 250000,
                'sale_price' => null,
                'image_url' => '/images/catalog/products/bo-roi-be-trai-xanh-phoi-cam.jpg',
                'description' => 'Bộ bơi rời xanh phối cam nổi bật, chất vải mềm và co giãn để bé vui chơi dưới nước dễ dàng.',
                'color' => 'Xanh phối cam',
                'category_slug' => 'do-boi-tre-em',
                'sizes' => ['4', '6', '8'],
            ],
        ];

        foreach ($products as $index => $data) {
            $color = $data['color'];
            $categorySlug = $data['category_slug'] ?? 'do-boi-nu';
            $sizes = $data['sizes'] ?? ['S', 'M', 'L'];
            unset($data['color'], $data['category_slug'], $data['sizes']);

            $product = Product::updateOrCreate(
                ['slug' => $data['slug']],
                $data + [
                    'category_id' => $categories[$categorySlug]->id,
                    'is_featured' => true,
                    'status' => 'active',
                ],
            );

            foreach ($sizes as $sizeIndex => $size) {
                $product->variants()->updateOrCreate(
                    ['color' => $color, 'size' => $size],
                    ['stock' => 8 + $index + $sizeIndex],
                );
            }
        }
    }
}
