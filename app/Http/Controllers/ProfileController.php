<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile.edit', ['profile' => Profile::firstOrFail()]);
    }

    public function update(Request $request)
    {
        $profile = Profile::firstOrFail();

        $validated = $request->validate([
            'handle' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('profiles')->ignore($profile->id)],
            'display_name' => ['required', 'string', 'max:50'],
            'base_look' => ['nullable', 'string', 'max:500'],
        ]);

        $profile->update($validated);

        return redirect()
            ->route('calendar', ['handle' => $profile->handle])
            ->with('status', '프로필이 저장됐어요!');
    }
}
