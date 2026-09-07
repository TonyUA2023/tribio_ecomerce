<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;

class HeaderFooterSeeder extends Seeder
{
    public function run()
    {
        foreach (Store::all() as $store) {
            if (!$store->sections()->where('type', 'header')->exists()) {
                $store->sections()->create([
                    'type' => 'header',
                    'order' => -1,
                    'data' => [
                        'topbar_text' => '“Todo lo que hace que un tractor se mueva”',
                        'topbar_bg_color' => '#E50914',
                        'topbar_text_color' => '#FFFFFF',
                        'header_bg_color' => '#FFFFFF',
                        'header_text_color' => '#374151',
                        'logo_alignment' => 'left'
                    ],
                    'is_active' => true
                ]);
            }
            if (!$store->sections()->where('type', 'footer')->exists()) {
                $store->sections()->create([
                    'type' => 'footer',
                    'order' => 999,
                    'data' => [
                        'footer_bg_color' => '#111827',
                        'footer_text_color' => '#9CA3AF',
                        'heading_color' => '#E50914'
                    ],
                    'is_active' => true
                ]);
            }
        }
    }
}
