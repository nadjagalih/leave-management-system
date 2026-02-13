<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    /**
     * Update the authenticated user's profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => ['sometimes', 'confirmed', Password::defaults()],
        ]);

        if (isset($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        }

        $user->update($validatedData);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user,
        ]);
    }

    /**
     * Delete the authenticated user's account.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $user = Auth::user();

        try {
            DB::transaction(function () use ($user) {
                // Delete related leave requests first
                $user->leaveRequests()->delete();
                
                // Revoke all tokens
                $user->tokens()->delete();

                // Delete the user
                $user->delete();
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete account due to a server error.'], 500);
        }

        return response()->json(['message' => 'Your account and all associated data have been successfully deleted.']);
    }
}
