<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinic management core. Every record belongs to a doctor (doctor_id), so the panel works for one
 * doctor today and can serve several later. Medical, patient and financial data live in separate tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specializations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->unique()->constrained('admins')->nullOnDelete(); // the login of this doctor
            $table->string('name');
            $table->string('qualification')->nullable();
            $table->string('specialization')->nullable();
            $table->string('registration_no', 60)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('clinic_name')->nullable();
            $table->text('clinic_address')->nullable();
            $table->json('working_hours')->nullable(); // {"mon": {"open": true, "from": "09:00", "to": "17:00"}, ...}
            $table->decimal('consultation_fee', 10, 2)->default(0);
            $table->unsignedSmallInteger('slot_minutes')->default(15);
            $table->string('signature')->nullable(); // path relative to /public
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->string('uhid', 30)->unique(); // patient number shown on papers
            $table->string('name');
            $table->string('gender', 10)->nullable();
            $table->date('dob')->nullable();
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('emergency_contact')->nullable();
            $table->text('allergies')->nullable();
            $table->text('medical_history')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['doctor_id', 'name']);
            $table->index('phone');
        });

        Schema::create('patient_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('title');
            $table->string('file'); // path relative to /public
            $table->foreignId('uploaded_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(15);
            $table->string('type', 20)->default('new'); // new | follow_up
            $table->string('status', 20)->default('scheduled'); // scheduled | checked_in | in_consultation | completed | cancelled | no_show
            $table->unsignedInteger('token_no')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['doctor_id', 'scheduled_at']);
            $table->index('status');
        });

        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->dateTime('consulted_at');
            $table->text('chief_complaint')->nullable();
            $table->text('symptoms')->nullable();
            $table->json('vitals')->nullable(); // bp, pulse, temperature, spo2, weight, height ... any key
            $table->text('examination')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('treatment')->nullable();
            $table->text('notes')->nullable();
            $table->json('custom_values')->nullable(); // answers to the doctor's own fields, keyed by field id
            $table->text('tests')->nullable();
            $table->text('advice')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->string('status', 10)->default('draft'); // draft | final
            $table->timestamps();

            $table->index(['doctor_id', 'consulted_at']);
            $table->index(['patient_id', 'consulted_at']);
        });

        // Fields the doctor adds for their own specialty; they appear on every consultation form
        Schema::create('consultation_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->string('label');
            $table->string('type', 20)->default('text'); // text | textarea | number | date | select | checkbox
            $table->text('options')->nullable(); // one choice per line, for "select"
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->string('medicine');
            $table->string('dosage', 80)->nullable();
            $table->string('frequency', 80)->nullable();
            $table->string('duration', 80)->nullable();
            $table->string('instructions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('number', 30)->unique();
            $table->date('invoice_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status', 10)->default('unpaid'); // unpaid | partial | paid | cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['doctor_id', 'invoice_date']);
            $table->index('status');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('category', 20)->default('consultation'); // consultation | treatment | procedure | lab | other
            $table->string('description');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('receipt_number', 30)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('method', 10)->default('cash'); // cash | upi | card | bank
            $table->dateTime('paid_at');
            $table->string('reference')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        foreach (['payments', 'invoice_items', 'invoices', 'prescription_items', 'consultation_fields', 'consultations', 'appointments', 'patient_documents', 'patients', 'doctors', 'specializations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
