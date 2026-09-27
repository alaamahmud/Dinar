<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferenceSeeder::class);

        // بيانات تجريبية للعرض — لا تُشغَّل في الإنتاج الحقيقي
        if (! app()->isProduction() || env('SEED_DEMO', false)) {
            $this->call(DemoSeeder::class);
        }
    }
}
