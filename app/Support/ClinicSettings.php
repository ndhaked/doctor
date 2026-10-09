<?php

namespace App\Support;

use App\Models\Setting;

/** Clinic-wide options (billing, printing, appointments) edited in Admin > Clinic Settings. */
class ClinicSettings
{
    public static function defaults(): array
    {
        return [
            'clinic.currency' => '₹',
            'clinic.invoice_prefix' => 'INV',
            'clinic.invoice_note' => 'Thank you for visiting us.',
            'clinic.rx_note' => '',
            'clinic.default_visit_fee_label' => 'Consultation fee',
        ];
    }

    /** @return array<string, string> */
    public static function content(): array
    {
        $v = Setting::many(self::defaults());

        return [
            'currency' => $v['clinic.currency'],
            'invoice_prefix' => preg_replace('/[^A-Za-z0-9]/', '', $v['clinic.invoice_prefix']) ?: 'INV',
            'invoice_note' => $v['clinic.invoice_note'],
            'rx_note' => $v['clinic.rx_note'],
            'visit_fee_label' => $v['clinic.default_visit_fee_label'],
        ];
    }

    public static function money(float|int|string|null $amount): string
    {
        return self::content()['currency'].number_format((float) $amount, 2);
    }
}
