<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{


    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Admin déconnecté avec succès'
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
    public function getClients()
    {
        $clients = User::where('role', 'client')->get();
        return response()->json($clients);
    }

    public function getTransporteurs()
    {
        $transporteurs = User::where('role', 'transporteur')->get();
        return response()->json($transporteurs);
    }
};
