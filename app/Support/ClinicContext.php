<?php

namespace App\Support;

use App\Models\Doctor;

/**
 * Tells every clinic screen which doctor's data to work with.
 *
 * - An admin account linked to a doctor works with that doctor.
 * - Any other account (for example a receptionist or the super admin) works with the first active doctor.
 * - If there is no doctor yet, one is created from the website's doctor details, so the panel never starts empty.
 *
 * All clinic tables carry a doctor_id, so supporting several doctors later only means choosing the doctor here.
 */
class ClinicContext
{
    protected ?Doctor $doctor = null;

    public static function current(): self
    {
        return app(self::class);
    }

    public static function doctor(): Doctor
    {
        return self::current()->resolve();
    }

    public static function id(): int
    {
        return self::doctor()->id;
    }

    /** Forget the remembered doctor (after the profile changed). */
    public function forget(): void
    {
        $this->doctor = null;
    }

    protected function resolve(): Doctor
    {
        if ($this->doctor) {
            return $this->doctor;
        }

        $adminId = auth('admin')->id();

        $doctor = ($adminId ? Doctor::where('admin_id', $adminId)->first() : null)
            ?? Doctor::where('is_active', true)->orderBy('id')->first()
            ?? Doctor::orderBy('id')->first();

        if (! $doctor) {
            $site = SiteSettings::content();

            $doctor = Doctor::create([
                'name' => $site['doctor_name'],
                'qualification' => str_replace("\n", ', ', (string) $site['doctor_qualifications']),
                'phone' => $site['phone'],
                'email' => $site['email'],
                'clinic_name' => $site['brand_full'],
                'clinic_address' => $site['address'],
                'working_hours' => Doctor::defaultWorkingHours(),
            ])->refresh();
        }

        return $this->doctor = $doctor;
    }
}
