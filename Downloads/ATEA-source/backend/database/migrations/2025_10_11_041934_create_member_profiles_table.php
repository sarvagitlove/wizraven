<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Business Information
            $table->string('business_name');
            $table->string('business_type')->nullable(); // LLC, Corporation, Partnership, etc.
            $table->string('industry')->nullable();
            $table->text('business_description')->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('business_email')->nullable();
            
            // Address Information
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('country')->default('USA');
            
            // Additional Information
            $table->integer('year_established')->nullable();
            $table->string('employees_count')->nullable(); // "1-10", "11-50", etc.
            $table->text('services_products')->nullable();
            $table->text('target_market')->nullable();
            
            // Membership Status
            $table->enum('profile_status', ['signup_pending', 'approval_pending', 'approved', 'rejected'])->default('signup_pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_profiles');
    }
};
