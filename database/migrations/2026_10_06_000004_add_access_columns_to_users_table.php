<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->after('id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->unique()->after('role_id')
                ->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('active')->after('password'); // active, inactive
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['status', 'last_login_at', 'last_login_ip']);
        });
    }
};
