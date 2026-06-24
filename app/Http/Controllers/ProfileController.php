<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['profile' => $request->user()->profile]);
    }

    public function update(Request $request)
    {
        $profile = $request->user()->profile;

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
