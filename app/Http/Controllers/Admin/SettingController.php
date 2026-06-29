<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Flip the global payment review mode (manual ↔ auto).
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'review_mode' => ['required', 'in:manual,auto'],
        ]);

        Setting::put('review_mode', $data['review_mode']);

        return back();
    }
}
