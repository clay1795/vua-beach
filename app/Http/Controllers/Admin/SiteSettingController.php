<?php

namespace App\Http\Controllers\Admin;

use App\Models\SiteSetting;
use App\Services\ProductImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SiteSettingController extends AdminController
{
    public function edit()
    {
        $this->authorizeAdmin();

        return view('admin.settings.edit', ['settings' => SiteSetting::current()]);
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:150'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'dimensions:min_width=80,min_height=40,max_width=3000,max_height=3000', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $settings = SiteSetting::query()->firstOrCreate([], [
            'site_name' => 'Vua Beach',
        ]);

        $oldLogoPath = $settings->logo_path;
        $newLogoPath = null;
        if ($request->boolean('remove_logo')) {
            $settings->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            $logoUrl = app(ProductImageService::class)->store($request->file('logo'), 'site', 'logo', 600);
            $newLogoPath = ltrim((string) preg_replace('#^/storage/#', '', (string) parse_url($logoUrl, PHP_URL_PATH)), '/');
            $settings->logo_path = $newLogoPath;
        }

        $settings->fill(collect($data)->only([
            'site_name', 'tagline', 'support_email', 'support_phone',
        ])->all());
        try {
            $settings->save();
        } catch (Throwable $exception) {
            if ($newLogoPath) {
                Storage::disk('public')->delete($newLogoPath);
            }

            throw $exception;
        }
        if ($oldLogoPath && $oldLogoPath !== $settings->logo_path) {
            Storage::disk('public')->delete($oldLogoPath);
        }
        $this->audit('site_settings.updated', $settings, 'Cập nhật thông tin hiển thị của website.');

        return back()->with('success', 'Đã cập nhật thông tin website.');
    }
}
