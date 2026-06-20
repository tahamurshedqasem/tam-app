<?php

namespace App\Services;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\Commission;
use App\Models\Customer;
use App\Models\RevenueTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CustomerService
{
    protected CustomerRepositoryInterface $customerRepository;
    protected UserRepositoryInterface $userRepository;
    protected CommissionService $commissionService;
    

    // ✅ تعريف المتغيرات الثابتة
    protected float $serviceFee = 3000; // 3000 YER
    protected float $marketerCommission = 400; // 400 YER
    protected string $currency = 'YER';

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        UserRepositoryInterface $userRepository,
        CommissionService $commissionService
        
    ) {
        $this->customerRepository = $customerRepository;
        $this->userRepository = $userRepository;
        $this->commissionService = $commissionService;
        
    }

    /**
     * ✅ الحصول على جميع العملاء مع التصفية
     */
    public function getAllCustomers(array $filters = [], int $perPage = 15)
    {
        $query = Customer::with(['user', 'marketer']);

        // ✅ البحث بالاسم أو رقم الهاتف أو رقم العضوية
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('membership_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        // ✅ تصفية حسب الحالة (دعم كل من membership_status و status)
        if (isset($filters['status']) && !empty($filters['status']) && $filters['status'] !== 'all') {
            $status = $filters['status'];
            $query->where(function ($q) use ($status) {
                $q->where('membership_status', $status)
                  ->orWhere('status', $status);
            });
        }

        // ✅ تصفية حسب المسوق
        if (isset($filters['marketer_id']) && !empty($filters['marketer_id'])) {
            $query->where('created_by_marketer', $filters['marketer_id']);
        }

        // ✅ ترتيب النتائج
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    /**
     * ✅ الحصول على إحصائيات العملاء
     */
    public function getCustomersStats(): array
    {
        return [
            'total' => Customer::count(),
            'active' => Customer::where(function ($q) {
                $q->where('membership_status', 'active')
                  ->orWhere('status', 'active');
            })->count(),
            'pending' => Customer::where(function ($q) {
                $q->where('membership_status', 'pending')
                  ->orWhere('status', 'pending');
            })->count(),
            'suspended' => Customer::where(function ($q) {
                $q->where('membership_status', 'suspended')
                  ->orWhere('status', 'suspended');
            })->count(),
            'expired' => Customer::where(function ($q) {
                $q->where('membership_status', 'expired')
                  ->orWhere('status', 'expired');
            })->count(),
        ];
    }

    /**
     * ✅ الحصول على عميل محدد مع جميع البيانات
     */
    public function getCustomerWithDetails(int $id): ?Customer
    {
        return Customer::with(['user', 'marketer', 'discountTransactions'])
            ->find($id);
    }

    /**
     * إنشاء عميل جديد مع حفظ الصور والبصمة
     */
    public function createCustomer(array $data, int $marketerId): array
    {
        return DB::transaction(function () use ($data, $marketerId) {
            Log::info('Creating customer with data:', $data);
            Log::info('Password exists: ' . (isset($data['password']) ? 'Yes' : 'No'));
            Log::info('Password length: ' . (isset($data['password']) ? strlen($data['password']) : 0));
            
            // Make sure password exists
            if (!isset($data['password']) || empty($data['password'])) {
                throw new \Exception('Password is required');
            }
            
            // معالجة الصور (Base64)
            $identityImagePath = $this->saveBase64Image($data['identity_image_base64'] ?? null, 'identities');
            $personalImagePath = $this->saveBase64Image($data['personal_image_base64'] ?? null, 'profiles');
            
            Log::info('Image paths:', [
                'identity_image' => $identityImagePath,
                'personal_image' => $personalImagePath
            ]);
            
            // معالجة بيانات البصمة
            $fingerprintData = $this->processFingerprintData($data['fingerprint_data'] ?? null);
            
            // استخدام كلمة المرور المدخلة
            $password = $data['password'];
            
            // إنشاء حساب المستخدم (تشفير كلمة المرور)
            $user = $this->userRepository->create([
                'full_name' => $data['full_name'],
                'region'=>$data['address'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password' => $password,
                'role' => 'customer',
                'status' => 'active'
            ]);

            // إنشاء رقم عضوية فريد
            $membershipNumber = $this->generateMembershipNumber();

            // إنشاء بيانات العميل
            $customerData = [
                'user_id' => $user->id,
                'membership_number' => $membershipNumber,
                'address' => $data['address'] ?? null,
                'identity_image' => $identityImagePath,
                'personal_image' => $personalImagePath,
                'fingerprint_data' => $fingerprintData,
                'created_by_marketer' => $marketerId,
                'membership_expiry_date' => now()->addYear(),
                'total_discount_saved' => 0,
                'membership_status' => 'active',
                'status' => 'active', // ✅ إضافة عمود status
            ];
            
            Log::info('Customer data to save:', $customerData);
            
            $customer = $this->customerRepository->create($customerData);

            // إنشاء عمولة لمسوق العملاء (400 YER) ومعاملة الإيرادات
            $marketer = User::find($marketerId);
            if ($marketer && $marketer->isCustomerMarketer()) {
                $this->createMarketerCommissionWithRevenue($customer, $marketer);
            } else {
                Log::warning('Invalid marketer for customer creation', [
                    'marketer_id' => $marketerId,
                    'customer_id' => $customer->id,
                    'is_marketer' => $marketer ? $marketer->isCustomerMarketer() : false
                ]);
            }

            return [
                'customer' => $customer,
                'user' => $user,
                'temporary_password' => $password,
                'membership_number' => $membershipNumber
            ];
        });
    }

    /**
     * إنشاء عمولة للمسوق (400 ريال يمني) ومعاملة الإيرادات
     */
    protected function createMarketerCommissionWithRevenue(Customer $customer, User $marketer): void
    {
        try {
            $commissionAmount = 400; // 400 YER
            $serviceFee = 3000; // 3000 YER
            $netAmount = $serviceFee - $commissionAmount; // 2600 YER

            Log::info('Creating commission with values:', [
                'commissionAmount' => $commissionAmount,
                'serviceFee' => $serviceFee,
                'netAmount' => $netAmount,
                'currency' => $this->currency,
                'customer_id' => $customer->id,
                'marketer_id' => $marketer->id
            ]);

            // 1. إنشاء العمولة
            $commission = Commission::create([
                'user_id' => $marketer->id,
                'role' => 'customer_marketer',
                'amount' => $commissionAmount,
                'commission_percentage' => 0,
                'reason' => "عمولة عن تسجيل عميل جديد: {$customer->full_name} (رقم العضوية: {$customer->membership_number})",
                'customer_id' => $customer->id,
                'transaction_id' => null,
                'status' => 'pending',
                'currency' => 'YER',
                'service_fee' => $serviceFee,
                'customer_discount' => 0,
                'due_date' => now()->addDays(30),
                'notes' => "عمولة تسجيل عميل جديد - رقم العضوية: {$customer->membership_number}"
            ]);

            Log::info('✅ Commission created successfully', [
                'commission_id' => $commission->id,
                'amount' => $commissionAmount
            ]);

            // 2. تحديث إحصائيات المسوق
            $marketer->increment('customers_count');
            $marketer->increment('pending_commission', $commissionAmount);
            $marketer->increment('total_commission', $commissionAmount);

            Log::info('✅ Marketer stats updated', [
                'marketer_id' => $marketer->id,
                'customers_count' => $marketer->customers_count,
                'pending_commission' => $marketer->pending_commission,
                'total_commission' => $marketer->total_commission
            ]);

            // 3. حساب المجموع التراكمي للشركة قبل الإضافة
            $previousTotal = RevenueTransaction::getCompanyTotal();
            $newTotal = $previousTotal + $netAmount;

            // 4. إنشاء معاملة الإيرادات
            $revenueTransaction = RevenueTransaction::create([
                'type' => 'customer_registration',
                'gross_amount' => $serviceFee,
                'total_commissions' => $commissionAmount,
                'net_amount' => $netAmount,
                'total' => $newTotal,
                'commission_breakdown' => [
                    'commission_id' => $commission->id,
                    'customer_name' => $customer->full_name,
                    'membership_number' => $customer->membership_number,
                    'marketer_name' => $marketer->full_name,
                    'marketer_id' => $marketer->id,
                    'commission_amount' => $commissionAmount,
                    'service_fee' => $serviceFee,
                    'net_revenue' => $netAmount,
                    'previous_total' => $previousTotal,
                    'new_total' => $newTotal,
                ],
                'customer_id' => $customer->id,
                'marketer_id' => $marketer->id,
                'status' => 'completed',
                'currency' => 'YER',
                'transaction_date' => now(),
                'notes' => "تسجيل عميل جديد: {$customer->full_name} - الإيرادات: {$serviceFee} YER - العمولة: {$commissionAmount} YER - صافي الإيرادات: {$netAmount} YER"
            ]);

            Log::info('✅ Revenue transaction created', [
                'transaction_id' => $revenueTransaction->id,
                'gross_amount' => $serviceFee,
                'commission' => $commissionAmount,
                'net_amount' => $netAmount,
                'company_total' => $newTotal,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to create marketer commission and revenue: ' . $e->getMessage(), [
                'customer_id' => $customer->id,
                'marketer_id' => $marketer->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * إنشاء خصم من حساب المؤسسة (3000 ريال يمني)
     */
    protected function createInstitutionDeduction(Customer $customer): void
    {
        try {
            // Check if table exists
            if (!\Illuminate\Support\Facades\Schema::hasTable('institution_deductions')) {
                Log::warning('institution_deductions table does not exist, skipping deduction');
                return;
            }

            \Illuminate\Support\Facades\DB::table('institution_deductions')->insert([
                'customer_id' => $customer->id,
                'membership_number' => $customer->membership_number,
                'amount' => $this->serviceFee,
                'currency' => $this->currency,
                'deduction_type' => 'membership_fee',
                'status' => 'pending',
                'description' => "رسوم عضوية للعميل: {$customer->full_name} (رقم العضوية: {$customer->membership_number})",
                'deducted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('✅ Institution deduction created', [
                'customer_id' => $customer->id,
                'amount' => $this->serviceFee,
                'membership_number' => $customer->membership_number,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to create institution deduction: ' . $e->getMessage(), [
                'customer_id' => $customer->id
            ]);
        }
    }

    /**
     * حفظ الصورة من Base64
     */
    protected function saveBase64Image(?string $base64Image, string $directory): ?string
    {
        if (empty($base64Image)) {
            return null;
        }

        try {
            $base64Image = trim($base64Image);
            
            // Remove data:image/...;base64, if present
            if (str_contains($base64Image, 'base64,')) {
                $base64Image = explode('base64,', $base64Image)[1];
            }
            
            // Decode Base64
            $imageData = base64_decode($base64Image);
            
            if ($imageData === false) {
                Log::error('Failed to decode base64 image');
                return null;
            }
            
            // Determine image type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_buffer($finfo, $imageData);
            finfo_close($finfo);
            
            $extension = match($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                default => 'png'
            };
            
            // Create unique filename
            $fileName = Str::uuid() . '_' . time() . '.' . $extension;
            $filePath = $directory . '/' . $fileName;
            
            // Save file
            $saved = Storage::disk('public')->put($filePath, $imageData);
            
            if (!$saved) {
                Log::error('Failed to save image: ' . $filePath);
                return null;
            }
            
            Log::info('✅ Image saved successfully: ' . $filePath);
            
            return $filePath;
            
        } catch (\Exception $e) {
            Log::error('❌ Error saving base64 image: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * معالجة بيانات البصمة
     */
    protected function processFingerprintData($fingerprintData): ?string
    {
        if (empty($fingerprintData)) {
            return null;
        }
        
        try {
            if (is_string($fingerprintData)) {
                $decoded = json_decode($fingerprintData, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $processedData = [
                        'data' => $decoded,
                        'registered_at' => now()->toISOString(),
                        'device_info' => request()->userAgent(),
                        'ip_address' => request()->ip(),
                    ];
                    return json_encode($processedData);
                }
            }
            
            if (is_array($fingerprintData)) {
                $processedData = [
                    'data' => $fingerprintData,
                    'registered_at' => now()->toISOString(),
                    'device_info' => request()->userAgent(),
                    'ip_address' => request()->ip(),
                ];
                return json_encode($processedData);
            }
            
            if (is_string($fingerprintData)) {
                $processedData = [
                    'data' => $fingerprintData,
                    'registered_at' => now()->toISOString(),
                    'device_info' => request()->userAgent(),
                    'ip_address' => request()->ip(),
                ];
                return json_encode($processedData);
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('❌ Error processing fingerprint: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * إنشاء رقم عضوية فريد
     */
    protected function generateMembershipNumber(): string
    {
        do {
            $year = date('Y');
            $random = str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            $number = $year . $random;
        } while ($this->customerRepository->findByMembershipNumber($number));

        return $number;
    }

    /**
     * إنشاء كلمة مرور مؤقتة (fallback)
     */
    protected function generateTemporaryPassword(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        return substr(str_shuffle($characters), 0, 8);
    }

    /**
     * تحديث بيانات العميل
     */
    public function updateCustomer(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data) {
            // تحديث بيانات المستخدم
            if (isset($data['full_name']) || isset($data['phone']) || isset($data['email'])) {
                $userData = [];
                if (isset($data['full_name'])) {
                    $userData['full_name'] = $data['full_name'];
                }
                if (isset($data['phone'])) {
                    $userData['phone'] = $data['phone'];
                }
                if (isset($data['email'])) {
                    $userData['email'] = $data['email'];
                }
                $this->userRepository->update($customer->user_id, $userData);
            }

            // تحديث صورة الهوية
            if (isset($data['identity_image_base64']) && !empty($data['identity_image_base64'])) {
                if ($customer->identity_image) {
                    Storage::disk('public')->delete($customer->identity_image);
                }
                $data['identity_image'] = $this->saveBase64Image($data['identity_image_base64'], 'identities');
            }

            // تحديث الصورة الشخصية
            if (isset($data['personal_image_base64']) && !empty($data['personal_image_base64'])) {
                if ($customer->personal_image) {
                    Storage::disk('public')->delete($customer->personal_image);
                }
                $data['personal_image'] = $this->saveBase64Image($data['personal_image_base64'], 'profiles');
            }

            // تحديث بيانات البصمة
            if (isset($data['fingerprint_data']) && !empty($data['fingerprint_data'])) {
                $data['fingerprint_data'] = $this->processFingerprintData($data['fingerprint_data']);
            }

            // تحديث بيانات العميل
            $customerData = array_intersect_key($data, array_flip([
                'address', 'identity_image', 'personal_image', 'fingerprint_data'
            ]));
            
            if (!empty($customerData)) {
                $this->customerRepository->update($customer->id, $customerData);
            }

            return $customer->fresh();
        });
    }

    /**
     * حذف عميل مع حذف ملفاته
     */
    public function deleteCustomer(Customer $customer): bool
    {
        return DB::transaction(function () use ($customer) {
            if ($customer->identity_image) {
                Storage::disk('public')->delete($customer->identity_image);
            }
            if ($customer->personal_image) {
                Storage::disk('public')->delete($customer->personal_image);
            }
            
            $user = $customer->user;
            $this->customerRepository->delete($customer->id);
            $user->delete();
            return true;
        });
    }

    /**
     * تجديد عضوية العميل
     */
    public function renewMembership(Customer $customer, int $months = 12): Customer
    {
        $customer->renewMembership($months);
        
        $marketer = User::find($customer->created_by_marketer);
        if ($marketer && $marketer->isCustomerMarketer()) {
            $this->createRenewalCommissionWithRevenue($customer, $marketer);
        }
        
        return $customer->fresh();
    }

    /**
     * إنشاء عمولة تجديد ومعاملة إيرادات
     */
    protected function createRenewalCommissionWithRevenue(Customer $customer, User $marketer): void
    {
        try {
            $commissionAmount = $this->marketerCommission; // 400 YER
            $serviceFee = $this->serviceFee; // 3000 YER
            $netAmount = $serviceFee - $commissionAmount; // 2600 YER

            Log::info('Creating renewal commission with values:', [
                'commissionAmount' => $commissionAmount,
                'serviceFee' => $serviceFee,
                'netAmount' => $netAmount,
                'customer_id' => $customer->id,
                'marketer_id' => $marketer->id
            ]);

            // 1. إنشاء العمولة
            $commission = Commission::create([
                'user_id' => $marketer->id,
                'role' => 'customer_marketer',
                'amount' => $commissionAmount,
                'commission_percentage' => 0,
                'reason' => "عمولة عن تجديد عضوية عميل: {$customer->full_name} (رقم العضوية: {$customer->membership_number})",
                'customer_id' => $customer->id,
                'transaction_id' => null,
                'status' => 'pending',
                'currency' => $this->currency,
                'service_fee' => $serviceFee,
                'customer_discount' => 0,
                'due_date' => now()->addDays(30),
                'notes' => "عمولة تجديد عضوية - رقم العضوية: {$customer->membership_number}"
            ]);

            Log::info('✅ Renewal commission created', [
                'commission_id' => $commission->id,
                'amount' => $commissionAmount
            ]);

            // 2. تحديث إحصائيات المسوق
            $marketer->increment('pending_commission', $commissionAmount);
            $marketer->increment('total_commission', $commissionAmount);

            // 3. حساب المجموع التراكمي للشركة قبل الإضافة
            $previousTotal = RevenueTransaction::getCompanyTotal();
            $newTotal = $previousTotal + $netAmount;

            // 4. إنشاء معاملة الإيرادات
            $revenueTransaction = RevenueTransaction::create([
                'type' => 'renewal',
                'gross_amount' => $serviceFee,
                'total_commissions' => $commissionAmount,
                'net_amount' => $netAmount,
                'total' => $newTotal,
                'commission_breakdown' => [
                    'commission_id' => $commission->id,
                    'customer_name' => $customer->full_name,
                    'membership_number' => $customer->membership_number,
                    'marketer_name' => $marketer->full_name,
                    'marketer_id' => $marketer->id,
                    'commission_amount' => $commissionAmount,
                    'service_fee' => $serviceFee,
                    'net_revenue' => $netAmount,
                    'previous_total' => $previousTotal,
                    'new_total' => $newTotal,
                ],
                'customer_id' => $customer->id,
                'marketer_id' => $marketer->id,
                'status' => 'completed',
                'currency' => $this->currency,
                'transaction_date' => now(),
                'notes' => "تجديد عضوية عميل: {$customer->full_name} - الإيرادات: {$serviceFee} {$this->currency} - العمولة: {$commissionAmount} {$this->currency} - صافي الإيرادات: {$netAmount} {$this->currency}"
            ]);

            Log::info('✅ Renewal revenue transaction created', [
                'transaction_id' => $revenueTransaction->id,
                'gross_amount' => $serviceFee,
                'commission' => $commissionAmount,
                'net_amount' => $netAmount,
                'company_total' => $newTotal,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to create renewal commission and revenue: ' . $e->getMessage(), [
                'customer_id' => $customer->id,
                'marketer_id' => $marketer->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * ✅ تحديث حالة العميل
     */
    public function updateCustomerStatus(Customer $customer, string $status): Customer
    {
        $customer->membership_status = $status;
        $customer->status = $status;
        $customer->save();
        
        Log::info('✅ Customer status updated', [
            'customer_id' => $customer->id,
            'status' => $status
        ]);
        
        return $customer->fresh();
    }

    /**
     * الحصول على رابط صورة الهوية
     */
    public function getIdentityImageUrl(Customer $customer): ?string
    {
        if ($customer->identity_image && Storage::disk('public')->exists($customer->identity_image)) {
            return asset('storage/' . $customer->identity_image);
        }
        return null;
    }

    /**
     * الحصول على رابط الصورة الشخصية
     */
    public function getPersonalImageUrl(Customer $customer): ?string
    {
        if ($customer->personal_image && Storage::disk('public')->exists($customer->personal_image)) {
            return asset('storage/' . $customer->personal_image);
        }
        return null;
    }

    /**
     * حذف صورة الهوية
     */
    public function deleteIdentityImage(Customer $customer): bool
    {
        if ($customer->identity_image && Storage::disk('public')->delete($customer->identity_image)) {
            $this->customerRepository->update($customer->id, ['identity_image' => null]);
            return true;
        }
        return false;
    }

    /**
     * حذف الصورة الشخصية
     */
    public function deletePersonalImage(Customer $customer): bool
    {
        if ($customer->personal_image && Storage::disk('public')->delete($customer->personal_image)) {
            $this->customerRepository->update($customer->id, ['personal_image' => null]);
            return true;
        }
        return false;
    }

    /**
     * حذف بيانات البصمة
     */
    public function deleteFingerprint(Customer $customer): bool
    {
        $this->customerRepository->update($customer->id, ['fingerprint_data' => null]);
        return true;
    }

    /**
     * إحصائيات العميل
     */
    public function getCustomerStats(Customer $customer): array
    {
        $totalSavings = $customer->discountTransactions()->sum('amount_saved');
        $totalTransactions = $customer->discountTransactions()->count();
        $lastTransaction = $customer->discountTransactions()->latest()->first();
        
        return [
            'total_savings' => $totalSavings,
            'total_transactions' => $totalTransactions,
            'last_transaction_date' => $lastTransaction?->transaction_date,
            'membership_days_remaining' => $customer->days_remaining,
            'membership_status' => $customer->membership_status,
            'service_fee' => $this->serviceFee,
            'currency' => $this->currency,
        ];
    }
}