<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller // Handles user authentication and registration
{
    /**
     * Get the authenticated User
    *
    * @return [json] user object
    */
    public function user(Request $request) // Returns current authenticated user data
    {
        return response()->json($request->user()); // Return user object as JSON
    }
    
    
    /**
     * Get all users
     *
     * @return [json] users object
     */
    public function all(): JsonResponse // Retrieves all users (admin only endpoint)
    {
        $users = User::select('id', 'name', 'email')->get(); // Get only essential user fields

        return response()->json($users); // Return users collection as JSON
    }
    
    /**
    * Create user
    *
    * @param  [string] name
    * @param  [string] email
    * @param  [string] password
    * @param  [string] password_confirmation
    * @return [string] message
    */
    public function register(Request $request) // Register new user with validation
    {
        $validator = Validator::make($request->all(), [ // Validate incoming registration data
            'name' => 'required|string|max:255', // Name is required, max 255 chars
            'email' => 'required|string|email|max:255|unique:users', // Email must be unique
            'password' => 'required|string|min:8', // Password minimum 8 characters
            'password_confirmation' => 'required|same:password', // Must match password
            'is_admin' => 'sometimes|boolean' // Optional admin flag
        ]);

        if ($validator->fails()) { // If validation fails
            return response()->json([
                'message' => 'Validation failed', // Error message
                'errors' => $validator->errors() // Return validation errors
            ], 422); // HTTP 422 Unprocessable Entity
        }

        $user = User::create([ // Create new user record
            'name' => $request->name, // User's display name
            'email' => $request->email, // User's email address
            'password' => bcrypt($request->password), // Hash password for security
            'is_admin' => filter_var($request->is_admin, FILTER_VALIDATE_BOOLEAN), // Convert admin flag to boolean
        ]);

        $tokenResult = $user->createToken('Personal Access Token'); // Generate API token
        $token = $tokenResult->plainTextToken; // Get plain text token for response

        return response()->json([
            'message' => 'Successfully created user!', // Success message
            'accessToken' => $token, // Return token for immediate authentication
        ], 201); // HTTP 201 Created
    }

    /**
     * Login user and create token
    *
    * @param  [string] email
    * @param  [string] password
    * @param  [boolean] remember_me
    */

    public function login(Request $request) // Authenticate user and return access token
    {
        $request->validate([ // Validate login credentials
        'email' => 'required|string|email', // Email is required and must be valid
        'password' => 'required|string', // Password is required
        'remember_me' => 'boolean' // Optional remember me flag
        ]);

        $credentials = request(['email','password']); // Extract credentials from request
        if(!Auth::attempt($credentials)) // Attempt authentication
        {
        return response()->json([
            'message' => 'Unauthorized' // Invalid credentials message
        ],401); // HTTP 401 Unauthorized
        }

        $user = $request->user(); // Get authenticated user
        $tokenResult = $user->createToken('Personal Access Token'); // Generate new API token
        $token = $tokenResult->plainTextToken; // Get plain text token

        return response()->json([
        'accessToken' =>$token, // Return access token
        'token_type' => 'Bearer', // Specify token type for API usage
        ]);
    }

    /**
     * Logout user (Revoke the token)
    *
    * @return [string] message
    */
    public function logout(Request $request) // Revoke all user tokens and logout
    {
        $request->user()->tokens()->delete(); // Delete all user's API tokens

        return response()->json([
        'message' => 'Successfully logged out' // Confirmation message
        ]);

    }
}