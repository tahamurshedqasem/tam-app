<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Institution;
use App\Models\User;
use App\Models\DiscountTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerificationService
{
    /**
     * ✅ الخطوة 1: التحقق من وجود العميل برقم الهاتف
     * هذه الدالة تستخدمها مالك المؤسسة
     */
    public function verifyCustomerByPhone(string $phone, int $institutionId): array
    {
        // البحث عن العميل عبر رقم الهاتف
        $customer = Customer::whereHas('user', function($query) use ($phone) {
            $query->where('phone', $phone);
        })->first();

        $institution = Institution::find($institutionId);

        // ❌ العميل غير موجود
        if (!$customer) {
            return [
                'is_valid' => false,
                'message' => 'رقم الهاتف غير مسجل في النظام',
                'customer_exists' => false,
                'has_fingerprint' => false,
            ];
        }

        // ❌ المؤسسة غير نشطة
        if (!$institution || !$institution->isValid()) {
            return [
                'is_valid' => false,
                'message' => 'المؤسسة غير نشطة',
                'customer_exists' => true,
                'has_fingerprint' => $customer->hasFingerprint(),
                'customer' => [
                    'id' => $customer->id,
                    'full_name' => $customer->user->full_name,
                    'membership_number' => $customer->membership_number,
                    'phone' => $customer->user->phone,
                ],
            ];
        }

        // ❌ انتهت صلاحية العضوية
        if (!$customer->isValidMembership()) {
            return [
                'is_valid' => false,
                'message' => 'انتهت صلاحية العضوية',
                'customer_exists' => true,
                'has_fingerprint' => $customer->hasFingerprint(),
                'customer' => [
                    'id' => $customer->id,
                    'full_name' => $customer->user->full_name,
                    'membership_number' => $customer->membership_number,
                    'phone' => $customer->user->phone,
                    'membership_status' => $customer->membership_status,
                    'days_remaining' => $customer->days_remaining,
                ],
            ];
        }

        // ✅ العميل موجود وصالح
        $user = $customer->user;
        $hasFingerprint = $customer->hasFingerprint();

        return [
            'is_valid' => true,
            'message' => $hasFingerprint 
                ? '✅ العميل موجود وموثق بالبصمة' 
                : '⚠️ العميل موجود ولكن لا توجد بصمة مسجلة',
            'customer_exists' => true,
            'has_fingerprint' => $hasFingerprint,
            'customer' => [
                'id' => $customer->id,
                'full_name' => $user->full_name,
                'membership_number' => $customer->membership_number,
                'phone' => $user->phone,
                'email' => $user->email,
                'address' => $customer->address,
                'total_savings' => (float) $customer->total_discount_saved,
                'total_discount_usage' => $customer->discountTransactions()->count(),
                'membership_status' => $customer->membership_status,
                'days_remaining' => $customer->days_remaining,
                'has_fingerprint' => $hasFingerprint,
                'discount_percentage' => $institution->discount_percentage,
            ],
        ];
    }

    /**
     * ✅ الخطوة 2: التحقق من البصمة
     * هذه الدالة تستخدمها مالك المؤسسة بعد التأكد من وجود العميل
     */
    public function verifyFingerprint(string $phone, array $fingerprintData): array
    {
        // البحث عن العميل
        $customer = Customer::whereHas('user', function($query) use ($phone) {
            $query->where('phone', $phone);
        })->first();

        if (!$customer) {
            return [
                'is_valid' => false,
                'message' => 'رقم الهاتف غير مسجل في النظام',
                'customer_exists' => false,
                'fingerprint_matched' => false,
            ];
        }

        if (!$customer->hasFingerprint()) {
            return [
                'is_valid' => false,
                'message' => 'العميل ليس لديه بصمة مسجلة',
                'customer_exists' => true,
                'fingerprint_matched' => false,
                'customer' => [
                    'id' => $customer->id,
                    'full_name' => $customer->user->full_name,
                    'membership_number' => $customer->membership_number,
                ],
            ];
        }

        // التحقق من تطابق البصمة
        $isFingerprintMatch = $this->verifyFingerprintData(
            $customer->fingerprint_data, 
            $fingerprintData
        );

        if (!$isFingerprintMatch) {
            return [
                'is_valid' => false,
                'message' => '❌ البصمة غير متطابقة',
                'customer_exists' => true,
                'fingerprint_matched' => false,
                'customer' => [
                    'id' => $customer->id,
                    'full_name' => $customer->user->full_name,
                    'membership_number' => $customer->membership_number,
                ],
            ];
        }

        // ✅ البصمة متطابقة
        return [
            'is_valid' => true,
            'message' => '✅ تم التحقق من بصمة العميل بنجاح',
            'customer_exists' => true,
            'fingerprint_matched' => true,
            'customer' => [
                'id' => $customer->id,
                'full_name' => $customer->user->full_name,
                'membership_number' => $customer->membership_number,
                'phone' => $customer->user->phone,
                'email' => $customer->user->email,
            ],
        ];
    }

    /**
     * التحقق من تطابق بيانات البصمة
     */
    protected function verifyFingerprintData($storedData, array $providedData): bool
    {
        // فك تشفير البيانات المخزنة
        $stored = json_decode($storedData, true);
        
        if (!$stored || !isset($stored['data'])) {
            return false;
        }

        $storedFingerprint = $stored['data'];
        
        // مقارنة بصمة العميل المخزنة مع البصمة المقدمة
        return hash_equals(
            hash('sha256', json_encode($storedFingerprint)),
            hash('sha256', json_encode($providedData))
        );
    }

    /**
     * ✅ التحقق من العميل باستخدام رقم العضوية
     */
    public function verifyCustomer(string $membershipNumber, int $institutionId): array
    {
        $customer = Customer::where('membership_number', $membershipNumber)->first();
        $institution = Institution::find($institutionId);

        if (!$customer) {
            return [
                'is_valid' => false,
                'message' => 'رقم العضوية غير صحيح',
                'customer_exists' => false,
                'has_fingerprint' => false,
            ];
        }

        if (!$institution || !$institution->isValid()) {
            return [
                'is_valid' => false,
                'message' => 'المؤسسة غير نشطة',
                'customer_exists' => true,
                'has_fingerprint' => $customer->hasFingerprint(),
                'customer' => [
                    'id' => $customer->id,
                    'full_name' => $customer->user->full_name,
                    'membership_number' => $customer->membership_number,
                    'phone' => $customer->user->phone,
                ],
            ];
        }

        if (!$customer->isValidMembership()) {
            return [
                'is_valid' => false,
                'message' => 'انتهت صلاحية العضوية',
                'customer_exists' => true,
                'has_fingerprint' => $customer->hasFingerprint(),
                'customer' => [
                    'id' => $customer->id,
                    'full_name' => $customer->user->full_name,
                    'membership_number' => $customer->membership_number,
                    'phone' => $customer->user->phone,
                    'membership_status' => $customer->membership_status,
                    'days_remaining' => $customer->days_remaining,
                ],
            ];
        }

        $user = $customer->user;
        $hasFingerprint = $customer->hasFingerprint();

        return [
            'is_valid' => true,
            'message' => $hasFingerprint 
                ? '✅ العميل موجود وموثق بالبصمة' 
                : '⚠️ العميل موجود ولكن لا توجد بصمة مسجلة',
            'customer_exists' => true,
            'has_fingerprint' => $hasFingerprint,
            'customer' => [
                'id' => $customer->id,
                'full_name' => $user->full_name,
                'membership_number' => $customer->membership_number,
                'phone' => $user->phone,
                'email' => $user->email,
                'address' => $customer->address,
                'total_savings' => (float) $customer->total_discount_saved,
                'total_discount_usage' => $customer->discountTransactions()->count(),
                'membership_status' => $customer->membership_status,
                'days_remaining' => $customer->days_remaining,
                'has_fingerprint' => $hasFingerprint,
                'discount_percentage' => $institution->discount_percentage,
            ],
        ];
    }

    /**
     * التحقق باستخدام QR Code
     */
    public function verifyByQR(string $qrData, int $institutionId): array
    {
        $decodedData = json_decode($qrData, true);
        
        if ($decodedData && isset($decodedData['phone'])) {
            return $this->verifyCustomerByPhone($decodedData['phone'], $institutionId);
        }
        
        if ($decodedData && isset($decodedData['membership_number'])) {
            return $this->verifyCustomer($decodedData['membership_number'], $institutionId);
        }
        
        if (preg_match('/^[0-9]{10,15}$/', $qrData)) {
            return $this->verifyCustomerByPhone($qrData, $institutionId);
        }
        
        return [
            'is_valid' => false,
            'message' => 'رمز QR غير صالح',
            'customer_exists' => false,
            'has_fingerprint' => false,
        ];
    }

    /**
     * اعتماد الخصم بعد التحقق
     */
    public function approveDiscount(
        int $customerId,
        int $institutionId,
        int $ownerId,
        ?float $originalAmount = null,
        ?string $notes = null
    ): DiscountTransaction {
        return DB::transaction(function () use ($customerId, $institutionId, $ownerId, $originalAmount, $notes) {
            $customer = Customer::findOrFail($customerId);
            $institution = Institution::findOrFail($institutionId);
            $owner = User::findOrFail($ownerId);

            $transaction = DiscountTransaction::create([
                'customer_id' => $customerId,
                'institution_id' => $institutionId,
                'institution_owner_id' => $ownerId,
                'discount_percentage' => $institution->discount_percentage,
                'transaction_date' => now(),
                'notes' => $notes,
                'verification_method' => $originalAmount ? 'manual' : 'qr'
            ]);

            if ($originalAmount && $originalAmount > 0) {
                $transaction->calculateSavings($originalAmount);
                $transaction->save();
            }

            if ($transaction->amount_saved > 0) {
                $customer->addSavings($transaction->amount_saved);
            }

            return $transaction;
        });
    }

    /**
     * الحصول على بيانات العميل للعرض
     */
    public function getCustomerDetails(int $customerId): array
    {
        $customer = Customer::with('user')->findOrFail($customerId);
        $user = $customer->user;
        
        return [
            'id' => $customer->id,
            'full_name' => $user->full_name ?? 'غير معروف',
            'membership_number' => $customer->membership_number,
            'phone' => $user->phone ?? '---',
            'email' => $user->email ?? '',
            'address' => $customer->address,
            'total_savings' => (float) $customer->total_discount_saved,
            'total_discount_usage' => $customer->discountTransactions()->count(),
            'membership_status' => $customer->membership_status,
            'days_remaining' => $customer->days_remaining,
            'has_fingerprint' => $customer->hasFingerprint(),
            'membership_expiry_date' => $customer->membership_expiry_date,
        ];
    }
}