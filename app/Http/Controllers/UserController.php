<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ProcessUserChunk;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UsersImport;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function importIndex()
    {
        return view('ImportUser');
    }
    public function importUsers(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:51200' // 50MB
        ]);

        $file = $request->file('file');
        $fileName = time() . '.' . $request->file->extension();
        $request->file->move(public_path('uploads'), $fileName);
        $fullPath = public_path('uploads/' . $fileName);

        Excel::import(new UsersImport, $fullPath);

        return back()->with('success', 'Chunked jobs dispatched!');
    }
    public function registerIndex()
    {
        return view('register');
    }
    public function registerUser(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'g-recaptcha-response' => 'required',
        ]);

        // Validate Google reCAPTCHA
        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => env('GOOGLE_RECAPTCHA_SECRET_KEY'),
            'response' => $request->input('g-recaptcha-response'),
            'remoteip' => $request->ip(),
        ]);

        if (! ($response->json()['success'] ?? false)) {
            return back()->withErrors(['captcha' => 'Google reCAPTCHA validation failed.'])->withInput();
        }

        // Store user
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('register.index')->with('success', 'User registered successfully!');
    }
}
