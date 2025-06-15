<?php

namespace App\Imports;

use App\Models\User;
use Maatwebsite\Excel\Row;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class UsersImport implements OnEachRow, WithChunkReading, ShouldQueue
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function onRow(Row $row)
    {
        try {
            $row = $row->toArray();

            User::updateOrCreate([
                'name'     => $row[0],
                'email'    => $row[1],
                'password' => bcrypt($row[2]),
            ]);
        } catch (\Exception $e) {
            Log::error('Row import failed', ['row' => $row, 'error' => $e->getMessage()]);
        }
    }

    public function chunkSize(): int
    {
        return 100; // Adjust based on memory
    }
}
