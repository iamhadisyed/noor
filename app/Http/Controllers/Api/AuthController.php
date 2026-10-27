<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:191|unique:users',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create($data);

        return response()->json(['token' => $user->createToken('kindo')->plainTextToken, 'user' => $user], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        return response()->json(['token' => $user->createToken('kindo')->plainTextToken, 'user' => $user]);
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:500', 'device_type' => 'required|in:android,ios']);

        DeviceToken::updateOrCreate(['token' => $data['token']], ['user_id' => $request->user()->id, 'device_type' => $data['device_type']]);

        return response()->json(['ok' => true]);
    }
}
