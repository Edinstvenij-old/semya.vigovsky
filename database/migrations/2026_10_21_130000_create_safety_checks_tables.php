<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Is everyone OK?" roll calls after an air raid alert or explosions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safety_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['family_id', 'closed_at']);
        });

        Schema::create('safety_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('safety_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // safe | shelter | help
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['safety_check_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_responses');
        Schema::dropIfExists('safety_checks');
    }
};
