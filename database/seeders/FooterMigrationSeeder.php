<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StoreSection;

class FooterMigrationSeeder extends Seeder
{
    public function run()
    {
        $footers = StoreSection::where('type', 'footer')->get();
        foreach ($footers as $footer) {
            $data = $footer->data;
            $store = $footer->store;
            
            if (!isset($data['col1_text'])) $data['col1_text'] = $store->description ?? 'Tu tienda de confianza con los mejores productos.';
            if (!isset($data['col2_title'])) $data['col2_title'] = 'NAVEGACIÓN';
            if (!isset($data['link_1'])) $data['link_1'] = 'INICIO';
            if (!isset($data['link_2'])) $data['link_2'] = 'CATÁLOGO';
            if (!isset($data['col3_title'])) $data['col3_title'] = 'CONTACTO';
            if (!isset($data['whatsapp'])) $data['whatsapp'] = $store->whatsapp_phone ?? '';
            if (!isset($data['address'])) $data['address'] = $store->address ?? 'PERÚ';
            if (!isset($data['email'])) $data['email'] = $store->email ?? '';
            if (!isset($data['phone'])) $data['phone'] = $store->phone ?? '';
            if (!isset($data['col4_title'])) $data['col4_title'] = 'REDES SOCIALES';
            if (!isset($data['facebook_link'])) $data['facebook_link'] = $store->facebook_url ?? '';
            if (!isset($data['instagram_link'])) $data['instagram_link'] = $store->instagram_url ?? '';
            if (!isset($data['tiktok_link'])) $data['tiktok_link'] = '';
            if (!isset($data['twitter_link'])) $data['twitter_link'] = '';

            $footer->data = $data;
            $footer->save();
        }
    }
}
