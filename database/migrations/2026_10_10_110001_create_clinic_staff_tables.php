<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Staff working under a doctor: profile, patient assignment, tasks, attendance, shifts and pay. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('staff_role_id')->nullable()->constrained('staff_roles')->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete(); // their login, if they have one
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->date('joined_on')->nullable();
            $table->decimal('monthly_salary', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['doctor_id', 'is_active']);
        });

        Schema::create('staff_patient', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'patient_id']);
        });

        Schema::create('staff_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('title');
            $table->text('details')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 10)->default('pending'); // pending | done
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'status']);
        });

        Schema::create('staff_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('shift_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'shift_date']);
        });

        Schema::create('staff_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('work_date');
            $table->string('status', 10)->default('present'); // present | absent | leave | half_day
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'work_date']);
        });

        Schema::create('staff_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('type', 10)->default('salary'); // salary | bonus | advance | other
            $table->string('period', 7)->nullable(); // the month it is for, YYYY-MM
            $table->decimal('amount', 12, 2);
            $table->date('paid_on');
            $table->string('method', 10)->default('cash'); // cash | upi | card | bank
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        foreach (['staff_payments', 'staff_attendance', 'staff_shifts', 'staff_tasks', 'staff_patient', 'staff', 'staff_roles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
