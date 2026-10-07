<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 20)->unique();
            $table->string('first_name', 60);
            $table->string('last_name', 60);
            $table->string('email', 120)->unique();
            $table->string('phone', 20);
            $table->text('address')->nullable();
            $table->string('gender', 10);
            $table->date('date_of_birth');
            $table->string('nid', 20)->unique();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('photo')->nullable();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('designation_id')->constrained()->restrictOnDelete();
            $table->string('employment_type', 20)->default('permanent'); // permanent, contract, probation, part_time
            $table->date('joining_date');
            $table->decimal('basic_salary', 12, 2);
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 40)->nullable();
            $table->string('bank_branch', 100)->nullable();
            $table->string('status', 20)->default('active'); // active, on_leave, suspended, resigned, terminated
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
