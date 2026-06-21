<?php

namespace Database\Seeders;

use App\Models\InstitutionType;
use Illuminate\Database\Seeder;

class InstitutionTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Restaurant', 'name_ar' => 'مطعم', 'is_active' => true],
            ['name' => 'Cafe', 'name_ar' => 'كافيه', 'is_active' => true],
            ['name' => 'Hotel', 'name_ar' => 'فندق', 'is_active' => true],
            ['name' => 'Store', 'name_ar' => 'متجر', 'is_active' => true],
            ['name' => 'Clinic', 'name_ar' => 'عيادة', 'is_active' => true],
            ['name' => 'Pharmacy', 'name_ar' => 'صيدلية', 'is_active' => true],
            ['name' => 'Gym', 'name_ar' => 'نادي رياضي', 'is_active' => true],
            ['name' => 'Beauty Center', 'name_ar' => 'مركز تجميل', 'is_active' => true],
            ['name' => 'School', 'name_ar' => 'مدرسة', 'is_active' => true],
            ['name' => 'University', 'name_ar' => 'جامعة', 'is_active' => true],
            ['name' => 'Hospital', 'name_ar' => 'مستشفى', 'is_active' => true],
            ['name' => 'Supermarket', 'name_ar' => 'سوبر ماركت', 'is_active' => true],
            ['name' => 'Bakery', 'name_ar' => 'مخبز', 'is_active' => true],
            ['name' => 'Butchery', 'name_ar' => 'جزارة', 'is_active' => true],
            ['name' => 'Electronics', 'name_ar' => 'إلكترونيات', 'is_active' => true],
            ['name' => 'Furniture', 'name_ar' => 'أثاث', 'is_active' => true],
            ['name' => 'Car Rental', 'name_ar' => 'تأجير سيارات', 'is_active' => true],
            ['name' => 'Travel Agency', 'name_ar' => 'وكالة سفر', 'is_active' => true],
            ['name' => 'Insurance', 'name_ar' => 'تأمين', 'is_active' => true],
            ['name' => 'Bank', 'name_ar' => 'بنك', 'is_active' => true],
        ];

        foreach ($types as $type) {
            // تجنب التكرار
            InstitutionType::firstOrCreate(
                ['name' => $type['name']],
                $type
            );
        }

        $this->command->info('✅ Institution types seeded successfully!');
        $this->command->info('Total: ' . InstitutionType::count() . ' types');
    }
}