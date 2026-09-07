<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StoreSection;

class HeaderMigrationSeeder extends Seeder
{
    public function run()
    {
        $headers = StoreSection::where('type', 'header')->get();
        foreach ($headers as $header) {
            $data = $header->data;
            if (isset($data['logo_alignment'])) {
                $data['alignment'] = $data['logo_alignment'];
                unset($data['logo_alignment']);
            }
            if (!isset($data['link_1'])) $data['link_1'] = 'INICIO';
            if (!isset($data['link_2'])) $data['link_2'] = 'CATÁLOGO';
            if (!isset($data['link_3'])) $data['link_3'] = 'CONTACTO';
            
            $header->data = $data;
            $header->save();
        }
    }
}
