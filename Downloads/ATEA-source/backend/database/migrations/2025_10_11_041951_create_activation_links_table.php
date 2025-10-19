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
        Schema::create('activation_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->uuid('token')->unique(); // GUID for activation
            $table->string('email'); // Email this link was sent to
            $table->timestamp('expires_at'); // 45 days from creation
            $table->timestamp('used_at')->nullable(); // When the link was used
            $table->timestamp('sent_at')->nullable(); // When email was sent
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activation_links');
    }
};
