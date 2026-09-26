<?php

namespace Tests\Feature;

use App\Services\GHNService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class VietnameseErrorHandlingTest extends TestCase
{
    public function test_standard_validation_messages_are_displayed_in_vietnamese(): void
    {
        $errors = Validator::make([], [
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ])->errors();

        $this->assertSame('Vui lòng nhập địa chỉ email.', $errors->first('email'));
        $this->assertSame('Vui lòng nhập mật khẩu.', $errors->first('password'));
    }

    public function test_vietnamese_catalog_covers_every_framework_validation_message(): void
    {
        $frameworkMessages = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $vietnameseMessages = require lang_path('vi/validation.php');

        $this->assertSame([], array_values(array_diff(array_keys($frameworkMessages), array_keys($vietnameseMessages))));
    }

    public function test_nested_fields_use_human_vietnamese_attribute_names(): void
    {
        $errors = Validator::make(
            ['items' => [['quantity' => null]]],
            ['items.0.quantity' => ['required']],
        )->errors();

        $message = $errors->first('items.0.quantity');

        $this->assertSame('Vui lòng nhập số lượng.', $message);
        $this->assertStringNotContainsString('validation.', $message);
    }

    public function test_web_and_json_http_errors_are_safe_and_vietnamese(): void
    {
        $this->get('/trang-khong-ton-tai')
            ->assertNotFound()
            ->assertSee('Không tìm thấy trang')
            ->assertDontSee('Symfony');

        $this->getJson('/api/khong-ton-tai')
            ->assertNotFound()
            ->assertJsonPath('message', 'Trang hoặc dữ liệu bạn yêu cầu không còn tồn tại.');
    }

    public function test_external_shipping_errors_do_not_expose_raw_provider_messages(): void
    {
        config()->set('services.ghn.token', 'test-token');
        config()->set('services.ghn.shop_id', 1);
        config()->set('services.ghn.from_district_id', 1);
        config()->set('services.ghn.base_url', 'https://ghn.test');

        Http::fake(['https://ghn.test/*' => Http::response([
            'code' => 400,
            'message' => 'Invalid phone 0900000000 token=provider-secret',
        ], 400)]);
        $channel = Mockery::mock(LoggerInterface::class);
        $channel->shouldReceive('warning')->once()->with(
            'GHN returned an error response',
            ['endpoint' => '/v2/shipping-order/fee', 'code' => 400],
        );
        Log::shouldReceive('channel')->once()->with('ghn')->andReturn($channel);

        $result = app(GHNService::class)->calculateFee(1, '00001');

        $this->assertSame('Dữ liệu giao hàng chưa hợp lệ. Vui lòng kiểm tra lại địa chỉ nhận hàng.', $result['message']);
    }
}
