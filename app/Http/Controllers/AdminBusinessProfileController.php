<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminBusinessProfileController extends Controller
{
    public function edit(): View
    {
        return view('admin.business-profile', [
            'businessProfile' => AppSetting::getBusinessProfile(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'business_address' => ['nullable', 'string', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:40'],
            'business_email' => ['nullable', 'email', 'max:160'],
            'business_hours' => ['nullable', 'string', 'max:255'],
            'service_area' => ['nullable', 'string', 'max:255'],
            'booking_notice' => ['nullable', 'string', 'max:500'],
            'service_guarantee' => ['nullable', 'string', 'max:1000'],
            'cancellation_policy' => ['nullable', 'string', 'max:1000'],
        ]);

        AppSetting::setBusinessProfile($data);

        return back()->with('success', 'Business trust details updated.');
    }
}
