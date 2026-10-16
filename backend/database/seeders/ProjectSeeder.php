<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public const DEMO_PUBLIC_KEY = 'pk_demo_local_public_key';

    public const DEMO_SERVER_KEY = 'sk_demo_local_server_key';

    public function run(): void
    {
        Project::updateOrCreate(
            ['name' => 'Restaurant Demo'],
            [
                'public_key' => self::DEMO_PUBLIC_KEY,
                'server_key' => self::DEMO_SERVER_KEY,
            ]
        );
    }
}
