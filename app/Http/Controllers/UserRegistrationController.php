<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserImage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class UserRegistrationController extends Controller
{
    public function index()
    {
        return view('registerUser');
    }

    public function upload(Request $request)
    {
        $files = [];
        if ($request->file && is_array($request->file)) {
            foreach ($request->file as $file) {
                $fileName = time() . '_' . rand() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads'), $fileName);
                $files[] = $fileName;

                // Store temporarily in session or in cache
                session()->push('uploaded_images', $fileName);
            }
        }

        return response()->json(['success' => true, 'files' => $files]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'uploaded_images' => 'required|array|min:1'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $randomPassword = Str::random(10);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($randomPassword),
        ]);

        foreach ($request->uploaded_images as $fileName) {
            UserImage::create([
                'user_id'   => $user->id,
                'file_name' => $fileName
            ]);
        }

        session()->forget('uploaded_images');

        return redirect()->back()->with('success', 'User registered successfully! ✅');
    }
}
