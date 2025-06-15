<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DailyActivity;
use DB;

class ChartController extends Controller
{
    public function index()
    {
        $data = DailyActivity::select('activity', DB::raw('SUM(time_spent) / 60 as hours')) // convert minutes to hours
            ->groupBy('activity')
            ->get()
            ->map(function ($item) {
                return [$item->activity, round($item->hours, 2)];
            })
            ->prepend(['Task', 'Hours per Day']) // prepend header
            ->values();

        $data->toArray();

        return view('chart.index', compact('data'));
    }
}
