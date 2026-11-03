<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MktSetting;

class MktSettingsController extends Controller
{
    public function index()
    {
        $settings = MktSetting::all()->pluck('value', 'key');
        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($request->settings as $key => $value) {
            MktSetting::setValue($key, $value);
        }

        return response()->json(['message' => 'Configuraciones actualizadas correctamente.']);
    }
}
