<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Faculties group academic departments (Faculty of Agriculture, Faculty of Engineering ...).
        Schema::create('faculties', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('short_name', 20)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        // Pay grades of the national pay scale. The amounts are data, entered and changed by the administrator.
        Schema::create('pay_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('grade_no')->unique();
            $table->string('name', 60);
            $table->decimal('min_basic', 12, 2);
            $table->decimal('max_basic', 12, 2)->nullable();   // empty = a fixed scale (only the minimum is allowed)
            $table->decimal('increment_percent', 5, 2)->default(5);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('faculty_id')->nullable()->after('id')->constrained('faculties')->nullOnDelete();
            $table->string('type', 20)->default('academic')->after('name');   // academic, administrative
        });

        Schema::table('designations', function (Blueprint $table) {
            $table->string('category', 20)->default('staff')->after('name');  // teacher, officer, staff
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('pay_grade_id')->nullable()->after('designation_id')->constrained('pay_grades')->nullOnDelete();
            $table->string('tin', 12)->nullable()->unique()->after('nid');
            $table->string('pf_account_no', 30)->nullable()->after('bank_branch');
            $table->date('last_increment_on')->nullable()->after('basic_salary');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pay_grade_id');
            $table->dropUnique(['tin']);
            $table->dropColumn(['tin', 'pf_account_no', 'last_increment_on']);
        });
        Schema::table('designations', fn (Blueprint $table) => $table->dropColumn('category'));
        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('faculty_id');
            $table->dropColumn('type');
        });
        Schema::dropIfExists('pay_grades');
        Schema::dropIfExists('faculties');
    }
};
