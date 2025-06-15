<?php

namespace App\Jobs;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessUserChunk implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function handle()
    {
        ini_set('memory_limit', '512M');
        $data = [];

        foreach ($this->rows as $row) {
            $data[] = [
                'name'       => $row[0],
                'email'      => $row[1],
                'password'   => bcrypt($row[2]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        User::insert($data); // Bulk insert
    }
}
