<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('type', 20);      // earning, deduction
            $table->string('calc_type', 20)->default('fixed'); // fixed, percent (percent of basic salary)
            $table->decimal('default_value', 12, 2)->default(0);
            $table->string('source', 20)->default('structure'); // structure, adjustment, system
            $table->boolean('is_taxable')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('salary_component_id')->constrained()->restrictOnDelete();
            $table->decimal('value', 12, 2)->nullable(); // null = use the component default
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'salary_component_id', 'effective_from'], 'emp_component_effective_unique');
        });

        Schema::create('tax_slabs', function (Blueprint $table) {
            $table->id();
            $table->decimal('from_amount', 14, 2);
            $table->decimal('to_amount', 14, 2)->nullable(); // null = no upper limit
            $table->decimal('rate_percent', 5, 2);
            $table->date('effective_from');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_slabs');
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('salary_components');
    }
};
