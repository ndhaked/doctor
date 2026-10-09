# DoctorWeb: Clinic Management Panel

A complete system to run a clinic: patients, appointments, consultations, billing, staff and reports. It also includes a public website that the clinic manages from the same panel.

Built with Laravel 12, Livewire 4, Tailwind CSS and Spatie Laravel Permission.

## Features

### 1. Dashboard
- Today's schedule and open appointments at a glance
- Follow-ups that still need to be arranged
- Quick links to every area the logged-in user is allowed to use

### 2. Patient management
- Register patients with personal details, date of birth, gender, blood group, address, email, phone and emergency contact
- Record allergies, medical history and notes
- Full patient profile with a medical summary and visit history
- Upload patient documents such as lab reports and scans (PDF or image, up to 10 MB), and view or print them
- Book appointments, start consultations and create invoices straight from the patient file

### 3. Appointments
- Day-wise schedule with a waiting queue
- Book, edit, reschedule and cancel appointments, with a visit type and length in minutes
- Check in, mark No show, and open the consultation directly from the appointment
- Register a new patient while booking
- Online booking section on the public website

### 4. Consultations and prescriptions
- Record the chief complaint, history, vitals, examination, diagnosis and advised tests
- Prescription with medicine, dosage, frequency, duration and instructions
- Follow-up date, advice and treatment notes
- Save as a draft, finalise, and reopen to edit
- View previous visits while consulting
- Print the prescription with the clinic's own header and footer text
- Specialty fields: add custom fields for any specialty (dentist, skin doctor, paediatrician and so on)

### 5. Billing and payments
- Create invoices with line items, quantity, price and discount
- Create an invoice directly from a consultation
- Record full or part payments by method (cash, UPI, card or cheque) with a reference number
- Print invoices and payment receipts
- Cancel invoices, and track the amount due
- Customisable invoice number prefix, currency symbol and footer text

### 6. Staff management
- Staff profiles for roles such as nurse and receptionist
- Attendance, shifts and daily tasks, including tasks tied to a specific patient
- Assign patients to staff members
- Record staff payments (salary and other payments)

### 7. Reports (each can be downloaded as CSV)
- Patients
- Appointments (completed, cancelled and no show)
- Consultations (including the most common diagnosis)
- Treatments and medicines prescribed
- Revenue (billed vs collected)
- Pending payments (with days overdue)

### 8. Roles, permissions and security
- Ready-made roles: Super Admin, Doctor, Receptionist, Nurse
- Create your own roles and choose exactly what each can see and do. Billing, medical records and reports are separate permissions.
- Add and manage admin users
- Login protection against repeated wrong attempts
- Own profile and password page for each user

### 9. Clinic settings
- Clinic profile, specializations, staff roles and specialty fields
- Billing and printing options

### 10. Public website, managed from the panel (no coding)
- Homepage slider, intro section, "Why choose us" and an info strip
- Doctor profile and doctor card
- Services, treatments and symptoms pages
- About page and contact page
- Appointment booking section
- Health tips blog
- FAQs
- Patient reviews and an associates section
- Photo gallery and video gallery
- Enquiry form, with enquiries stored in the panel and an email sent to the clinic
- Site settings for branding and details

## Getting started

```bash
composer install
npm install && npm run build
cp .env.example .env      # then set the database values
php artisan key:generate
php artisan migrate --seed
```

Optional demo data (sample patients, appointments, invoices and staff logins):

```bash
php artisan db:seed --class=DemoClinicSeeder
```

The admin panel is at `/admin/login`. Create or reset the Super Admin with `php artisan db:seed --class=AdminSeeder`. Change the seeded passwords before using this on a real server.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
