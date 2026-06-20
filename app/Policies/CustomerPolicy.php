<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Customer;

class CustomerPolicy
{
    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض قائمة العملاء
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'customer_marketer', 'institution_owner']);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض عميل محدد
     */
    public function view(User $user, Customer $customer): bool
    {
        return in_array($user->role, ['admin', 'customer_marketer']) || 
               $user->id === $customer->user_id;
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء عميل جديد
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'customer_marketer']);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث عميل
     */
    public function update(User $user, Customer $customer): bool
    {
        return in_array($user->role, ['admin', 'customer_marketer']) || 
               $user->id === $customer->user_id;
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف عميل
     */
    public function delete(User $user): bool
    {
        return $user->isAdmin();
    }
    
    /**
     * تحديد ما إذا كان المستخدم يمكنه تجديد عضوية عميل
     */
    public function renewMembership(User $user, Customer $customer): bool
    {
        return $user->isAdmin() || $user->isCustomerMarketer();
    }
}