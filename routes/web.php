<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\UserRegistrationController;
use App\Http\Controllers\HomeController;

Route::get('/', function () {
    $users = User::limit(100)->get();
    return view('welcome',compact('users'));
})->name('home');

Route::get('/import-users', [UserController::class, 'importIndex'])->name('users.form');
Route::post('/import-users', [UserController::class, 'importUsers'])->name('users.import');

Route::get('/register', [UserController::class, 'registerIndex'])->name('register.index');
Route::post('/register', [UserController::class, 'registerUser'])->name('register.store');

Route::get('/chart', [ChartController::class, 'index'])->name('chart.index');

Route::get('/register', [UserRegistrationController::class, 'index']);
Route::post('/register/upload', [UserRegistrationController::class, 'upload'])->name('register.upload');
Route::post('/register/store', [UserRegistrationController::class, 'register'])->name('register.store');


Route::get('/retry', [HomeController::class, 'index']);
Route::get('/stris', [HomeController::class, 'strIs']);
