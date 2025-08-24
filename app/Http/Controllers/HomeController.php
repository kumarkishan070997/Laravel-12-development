<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class HomeController extends Controller
{
    public function index(){
        try {
            $data = retry(3, function() {
                return Http::get('https://random-api.dev/data');
            }, 100);
        } catch (\Exception $e) {
            // Handle failure gracefully
            Log::error('Operation failed after retries: ' . $e->getMessage());
        }
    }
    public function strIs(){
        $one = Str::is('hello', 'hello'); // true
        $two = Str::is('hello', 'world'); // false
        dd($one,$two);
    }
}
