<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            if (Schema::hasColumn('employees', 'pay_grade_id')) {
                Schema::table('employees', fn (Blueprint $table) => $table->dropConstrainedForeignId('pay_grade_id'));
            }

            $employeeColumns = array_values(array_filter(
                ['tin', 'pf_account_no', 'last_increment_on'],
                fn (string $column) => Schema::hasColumn('employees', $column)
            ));

            if (in_array('tin', $employeeColumns, true)) {
                Schema::table('employees', fn (Blueprint $table) => $table->dropUnique(['tin']));
            }

            if ($employeeColumns !== []) {
                Schema::table('employees', fn (Blueprint $table) => $table->dropColumn($employeeColumns));
            }
        }

        if (Schema::hasTable('designations') && Schema::hasColumn('designations', 'category')) {
            Schema::table('designations', fn (Blueprint $table) => $table->dropColumn('category'));
        }

        if (Schema::hasTable('departments')) {
            if (Schema::hasColumn('departments', 'faculty_id')) {
                Schema::table('departments', fn (Blueprint $table) => $table->dropConstrainedForeignId('faculty_id'));
            }

            if (Schema::hasColumn('departments', 'type')) {
                Schema::table('departments', fn (Blueprint $table) => $table->dropColumn('type'));
            }
        }

        Schema::dropIfExists('pay_grades');
        Schema::dropIfExists('faculties');
    }

    public function down(): void
    {
        if (! Schema::hasTable('faculties')) {
            Schema::create('faculties', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120)->unique();
                $table->string('short_name', 20)->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pay_grades')) {
            Schema::create('pay_grades', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('grade_no')->unique();
                $table->string('name', 60);
                $table->decimal('min_basic', 12, 2);
                $table->decimal('max_basic', 12, 2)->nullable();
                $table->decimal('increment_percent', 5, 2)->default(5);
                $table->string('status', 20)->default('active');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('departments') && ! Schema::hasColumn('departments', 'faculty_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->foreignId('faculty_id')->nullable()->after('id')->constrained('faculties')->nullOnDelete();
            });
        }

        if (Schema::hasTable('departments') && ! Schema::hasColumn('departments', 'type')) {
            Schema::table('departments', fn (Blueprint $table) => $table->string('type', 20)->default('academic')->after('name'));
        }

        if (Schema::hasTable('designations') && ! Schema::hasColumn('designations', 'category')) {
            Schema::table('designations', fn (Blueprint $table) => $table->string('category', 20)->default('staff')->after('name'));
        }

        if (Schema::hasTable('employees')) {
            if (! Schema::hasColumn('employees', 'pay_grade_id')) {
                Schema::table('employees', fn (Blueprint $table) => $table->foreignId('pay_grade_id')->nullable()->after('designation_id')->constrained('pay_grades')->nullOnDelete());
            }

            if (! Schema::hasColumn('employees', 'tin')) {
                Schema::table('employees', fn (Blueprint $table) => $table->string('tin', 12)->nullable()->unique()->after('nid'));
            }

            if (! Schema::hasColumn('employees', 'pf_account_no')) {
                Schema::table('employees', fn (Blueprint $table) => $table->string('pf_account_no', 30)->nullable()->after('bank_branch'));
            }

            if (! Schema::hasColumn('employees', 'last_increment_on')) {
                Schema::table('employees', fn (Blueprint $table) => $table->date('last_increment_on')->nullable()->after('basic_salary'));
            }
        }
    }
};
