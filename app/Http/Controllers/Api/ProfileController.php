<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:190', Rule::unique('users')->ignore($user->id)],
            'avatar_color' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $user->update($data);

        return response()->json(['user' => $user->fresh()->toSessionArray()]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $rules = [
            'font_scale' => ['sometimes', Rule::in([1, 1.25, 1.5, 1.75])],
            'locale' => ['sometimes', Rule::in(SetLocale::SUPPORTED)],
            'high_contrast' => ['sometimes', 'boolean'],
            'simple_mode' => ['sometimes', 'boolean'],
        ];
        foreach (['logistics', 'projects', 'maintenance', 'chat'] as $channel) {
            $rules["push_{$channel}"] = ['sometimes', 'boolean'];
        }
        $rules['telegram_all'] = ['sometimes', 'boolean'];

        $user = $request->user();
        $user->update(['preferences' => array_merge($user->preferences ?? [], $request->validate($rules))]);

        return response()->json(['user' => $user->fresh()->toSessionArray()]);
    }

    public function password(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        $request->user()->update(['password' => $request->input('password')]);

        return response()->json(['ok' => true]);
    }

    public function switchFamily(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $familyId = (int) $request->validate(['family_id' => ['required', 'integer']])['family_id'];

        abort_unless($user->membership($familyId), 403);
        $user->update(['current_family_id' => $familyId]);

        return response()->json(['user' => $user->fresh()->toSessionArray()]);
    }
}
