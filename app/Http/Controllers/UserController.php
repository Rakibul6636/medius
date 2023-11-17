<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Enums\AccountType;
use Spatie\Enum\Laravel\Rules\EnumRule;

class UserController extends Controller
{   //Register new user.
    public function register(Request $request)
    {
        $request->merge([
            "account_type" => strtoUpper($request->input("account_type")),
        ]);

        // Validation of input data
        $validator = validator::make($request->all(), [
            "name" => "required|string",
            "account_type" =>
                "required|string|in:" . implode(",", AccountType::toValues()),
            "email" => "required|string|email|max:255|unique:users",
            "password" => "required|string|min:8",
        ]);

        if ($validator->fails()) {
            $response = [
                "success" => false,
                "message" => $validator->errors(),
            ];
            return response()->json($response, 400);
        }

        $input = $request->all();

        // Create an instance of the AccountType enum
        $input["account_type"] = new AccountType($input["account_type"]);

        $input["password"] = Hash::make($input["password"]);

        $user = User::create($input);
        $success["name"] = $user->name;

        $response = [
            "success" => true,
            "data" => $success,
            "message" => "User registration successful",
        ];

        return response()->json($response, 200);
    }
    //Logged in
    public function loginUser(Request $request)
    {
        $credentials = $request->validate([
            "email" => "required|email",
            "password" => "required",
        ]);

        if (auth()->attempt($credentials)) {
            $user = auth()->user();
            return response()->json(
                ["user" => $user, "message" => "Logged In Successfully"],
                200
            );
        }

        return response()->json(["message" => "Invalid credentials"], 401);
    }
}
