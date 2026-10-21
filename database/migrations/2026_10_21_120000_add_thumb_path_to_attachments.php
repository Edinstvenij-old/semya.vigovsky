<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            // Small preview generated in the browser before upload (no GD/Imagick needed on the server).
            $table->string('thumb_path')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn('thumb_path');
        });
    }
};
