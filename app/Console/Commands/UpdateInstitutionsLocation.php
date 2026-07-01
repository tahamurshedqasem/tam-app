<?php

namespace App\Console\Commands;

use App\Models\Institution;
use App\Models\Governorate;
use App\Models\District;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateInstitutionsLocation extends Command
{
    protected $signature = 'institutions:update-location';
    protected $description = 'Update existing institutions with governorate and district IDs based on address';

    public function handle()
    {
        $this->info('🚀 Starting to update institutions location...');

        // ✅ قائمة المحافظات والمناطق المعروفة
        $locationMap = [
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

        // ✅ جلب جميع المؤسسات
        $institutions = Institution::all();
        $this->info("📊 Found {$institutions->count()} institutions");

        $updated = 0;
        $skipped = 0;

        foreach ($institutions as $institution) {
            $address = $institution->address ?? '';
            $name = $institution->name ?? '';
            
            if (empty($address) && empty($name)) {
                $skipped++;
                continue;
            }

            $searchText = $address . ' ' . $name;
            $matchedGovernorate = null;
            $matchedDistrict = null;
            $matchedGovernorateName = null;
            $matchedDistrictName = null;

            // 🔍 البحث عن المحافظة
            foreach ($locationMap as $govName => $districts) {
                if (strpos($searchText, $govName) !== false) {
                    $matchedGovernorate = Governorate::where('name_ar', $govName)
                        ->orWhere('name', $govName)
                        ->first();

                    if ($matchedGovernorate) {
                        $matchedGovernorateName = $matchedGovernorate->name_ar ?? $matchedGovernorate->name;
                        
                        // 🔍 البحث عن المنطقة
                        foreach ($districts as $distName) {
                            if (strpos($searchText, $distName) !== false) {
                                $matchedDistrict = District::where('governorate_id', $matchedGovernorate->id)
                                    ->where(function($q) use ($distName) {
                                        $q->where('name_ar', $distName)
                                          ->orWhere('name', $distName);
                                    })
                                    ->first();

                                if ($matchedDistrict) {
                                    $matchedDistrictName = $matchedDistrict->name_ar ?? $matchedDistrict->name;
                                    break;
                                }
                            }
                        }

                        // إذا لم يتم العثور على منطقة، استخدم أول منطقة في المحافظة
                        if (!$matchedDistrict) {
                            $matchedDistrict = District::where('governorate_id', $matchedGovernorate->id)->first();
                            if ($matchedDistrict) {
                                $matchedDistrictName = $matchedDistrict->name_ar ?? $matchedDistrict->name;
                            }
                        }

                        break;
                    }
                }
            }

            // ✅ تحديث المؤسسة
            if ($matchedGovernorate && $matchedDistrict) {
                $institution->update([
                    'governorate_id' => $matchedGovernorate->id,
                    'district_id' => $matchedDistrict->id,
                    'governorate_name' => $matchedGovernorateName,
                    'district_name' => $matchedDistrictName,
                ]);
                $updated++;
                $this->info("✅ Updated: {$institution->name} -> {$matchedGovernorateName} / {$matchedDistrictName}");
            } else {
                $skipped++;
                $this->warn("⚠️ Skipped: {$institution->name} (No location found)");
            }
        }

        $this->newLine();
        $this->info("✅ Update completed!");
        $this->info("✅ Updated: $updated institutions");
        $this->info("⚠️ Skipped: $skipped institutions");
    }
}