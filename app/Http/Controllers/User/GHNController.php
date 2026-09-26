<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\GHNService;
use Illuminate\Http\Request;

class GHNController extends Controller
{
    public function provinces(GHNService $ghn)
    {
        return $this->respond($ghn->provinces());
    }

    public function districts(int $provinceId, GHNService $ghn)
    {
        return $this->respond($ghn->districts($provinceId));
    }

    public function wards(int $districtId, GHNService $ghn)
    {
        return $this->respond($ghn->wards($districtId));
    }

    public function fee(Request $request, GHNService $ghn)
    {
        $data = $request->validate(['to_district_id' => ['required', 'integer'], 'to_ward_code' => ['required', 'string', 'max:20'], 'weight' => ['nullable', 'integer', 'min:300', 'max:30000']]);

        return $this->respond($ghn->calculateFee($data['to_district_id'], $data['to_ward_code'], $data['weight'] ?? 300));
    }

    private function respond(array $data)
    {
        $status = (int) ($data['code'] ?? 200);

        return response()->json($data, $status >= 400 ? $status : 200);
    }
}
