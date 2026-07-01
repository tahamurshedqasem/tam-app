<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Governorate;
use App\Models\District;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
       
        $locations = [
            'صنعاء' => ['السبعين', 'التحرير', 'معين', 'الثورة', 'صنعاء القديمة'],
            'عدن' => ['خور مكسر', 'كريتر', 'الشيخ عثمان', 'المنصورة', 'صيرة'],
            'تعز' => ['القاهرة', 'المظفر', 'صالة', 'الجند'],
            'حضرموت' => ['المكلا', 'سيئون', 'تريم'],
            'إب' => ['إب', 'الظهار', 'المخادر'],
            'الحديدة' => ['الحديدة', 'الدريهمي', 'الزيدية'],
            'ذمار' => ['ذمار', 'عنس', 'وصاب العالي'],
            'عمران' => ['عمران', 'العشة', 'جبل عيال يزيد'],
            'المهرة' => ['الغيضة', 'حصوين', 'قشن'],
            'الجوف' => ['الحزم', 'الخلق', 'برط العنان'],
            'شبوة' => ['عتق', 'بيحان', 'حبان'],
            'الضالع' => ['الضالع', 'الحشاء', 'جبن'],
            'لحج' => ['الحوطة', 'تبن', 'حبيل جبر'],
            'أبين' => ['زنجبار', 'خنفر', 'مودية'],
            'مأرب' => ['مأرب', 'جبل مراد', 'الوادي'],
            'ريمة' => ['الجبين', 'كسمة', 'مزهر'],
            'صعدة' => ['صعدة', 'شداء', 'باقم'],
            'البيضاء' => ['البيضاء', 'العرش', 'الصومعة'],
            'حجة' => ['حجة', 'كعيدنة', 'أفلح الشام'],
        ];

        foreach ($locations as $govName => $districts) {
            $governorate = Governorate::firstOrCreate(
                ['name_ar' => $govName],
                ['name' => $govName, 'is_active' => true]
            );

            foreach ($districts as $distName) {
                District::firstOrCreate(
                    ['name_ar' => $distName, 'governorate_id' => $governorate->id],
                    ['name' => $distName, 'is_active' => true]
                );
            }
        }
    }
    }
