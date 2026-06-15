<?php

namespace Database\Seeders;

use App\Models\Profile;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Profile::firstOrCreate(
            ['handle' => 'viram'],
            [
                'display_name' => 'viram',
                'base_look' => '검은색 짧은 단발머리, 갈색 눈',
            ],
        );
    }
}
