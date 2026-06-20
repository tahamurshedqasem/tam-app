<?php

namespace App\Services;

use App\Models\Customer;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QRCodeService
{
    public function generateQRCode(string $data, int $size = 200): string
    {
        $qrCode = QrCode::size($size)
            ->format('png')
            ->errorCorrection('H')
            ->generate($data);
        
        return base64_encode($qrCode);
    }

    public function generateQRCodeAsBase64(string $data, int $size = 200): string
    {
        return 'data:image/png;base64,' . $this->generateQRCode($data, $size);
    }

    public function generateMembershipQR(Customer $customer): string
    {
        $data = json_encode([
            'membership_number' => $customer->membership_number,
            'customer_name' => $customer->full_name,
            'type' => 'membership'
        ]);
        
        return $this->generateQRCodeAsBase64($data);
    }
}