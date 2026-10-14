<?php

namespace Database\Seeders;

use App\Models\HealthMessage;
use Illuminate\Database\Seeder;

class HealthMessageSeeder extends Seeder
{
    public function run(): void
    {
        $messages = [
            ['message_km' => 'តិចអំបិល តិចខ្លាញ់ តិចស្ករ', 'icon' => 'Salad', 'accent_color' => '#F5B301', 'sort_order' => 1, 'source_url' => 'https://moh.gov.kh/kh/events/detail/467'],
            ['message_km' => 'ធ្វើលំហាត់ប្រាណឱ្យបានទៀងទាត់', 'icon' => 'Dumbbell', 'accent_color' => '#22C55E', 'sort_order' => 2, 'source_url' => 'https://moh.gov.kh/kh/events/detail/467'],
            ['message_km' => 'ចៀសវាងការប្រើប្រាស់ផលិតផលថ្នាំជក់', 'icon' => 'CigaretteOff', 'accent_color' => '#EF4444', 'sort_order' => 3, 'source_url' => 'https://moh.gov.kh/kh/news/detail/331'],
            ['message_km' => 'កាត់បន្ថយ និងចៀសវាងការប្រើប្រាស់គ្រឿងស្រវឹង', 'icon' => 'WineOff', 'accent_color' => '#F97316', 'sort_order' => 4, 'source_url' => 'https://moh.gov.kh/kh/news/detail/331'],
            ['message_km' => 'កាត់បន្ថយភេសជ្ជៈផ្អែម ដើម្បីសុខភាពល្អ', 'icon' => 'CupSoda', 'accent_color' => '#EAB308', 'sort_order' => 5, 'source_url' => 'https://moh.gov.kh/kh/events/detail/467'],
            ['message_km' => 'ទទួលទានអាហារដែលមានសុខភាព', 'icon' => 'Apple', 'accent_color' => '#16A34A', 'sort_order' => 6, 'source_url' => 'https://moh.gov.kh/kh/news/detail/331'],
            ['message_km' => 'ការរស់នៅប្រកបដោយសុខភាពល្អ ជួយបង្ការជំងឺមិនឆ្លង', 'icon' => 'HeartPulse', 'accent_color' => '#0EA5E9', 'sort_order' => 7, 'source_url' => 'https://moh.gov.kh/en/events/detail/578'],
            ['message_km' => 'ពេលមានបញ្ហាសុខភាព សូមទៅមូលដ្ឋានសុខាភិបាលដែលនៅជិតបំផុត', 'icon' => 'Hospital', 'accent_color' => '#2563EB', 'sort_order' => 8, 'source_url' => 'https://moh.gov.kh/kh/events/detail/467'],
        ];

        foreach ($messages as $message) {
            HealthMessage::firstOrCreate(
                ['message_km' => $message['message_km']],
                array_merge($message, ['source_name' => 'ក្រសួងសុខាភិបាល', 'is_active' => true])
            );
        }
    }
}
