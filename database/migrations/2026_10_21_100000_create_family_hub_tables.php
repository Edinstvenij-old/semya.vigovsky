<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Family Hub domain schema.
 *
 * Every domain row carries family_id so that all queries can be scoped
 * to the current family of the authenticated user.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Families & membership -------------------------------------------------
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Backup settings: off | daily | weekly
            $table->string('backup_frequency')->default('weekly');
            $table->unsignedSmallInteger('backup_keep')->default(8);
            $table->timestamp('last_backup_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_family_id')->nullable()->after('id')->constrained('families')->nullOnDelete();
            $table->string('avatar_color', 7)->default('#2563eb');
            // UI preferences: font_scale (1|1.25|1.5), simple_mode, notification prefs
            $table->json('preferences')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
        });

        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // admin | member | viewer
            $table->string('relation')->nullable();     // «мама», «дедушка» ...
            $table->timestamps();
            $table->unique(['family_id', 'user_id']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique(); // sha256 of the secret token; raw token is shown once
            $table->string('role')->default('member');
            $table->string('note')->nullable();
            $table->unsignedSmallInteger('max_uses')->default(1);
            $table->unsignedSmallInteger('uses')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        // --- Module 3.1: home & projects ------------------------------------------
        Schema::create('task_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('icon')->default('folder');
            $table->string('color', 7)->default('#2563eb');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('task_categories')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo'); // todo | in_progress | done
            $table->string('priority')->default('normal'); // low | normal | high
            $table->date('due_date')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['family_id', 'status']);
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('text');
            $table->boolean('is_done')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('task_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('title')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->morphs('attachable'); // Task, TravelDocument ...
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind')->default('other'); // before | after | other | document
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        Schema::create('maintenance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('kind')->default('consumable'); // consumable | bill | check | other
            $table->unsignedInteger('interval_days');
            $table->date('next_due_on');
            $table->unsignedSmallInteger('remind_days_before')->default(3);
            $table->date('reminded_for')->nullable(); // next_due_on the last reminder was sent for
            $table->timestamps();
        });

        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('done_on');
            $table->string('note')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            // Electricity bills: day / night tariff meter readings
            $table->decimal('reading_day', 12, 2)->nullable();
            $table->decimal('reading_night', 12, 2)->nullable();
            $table->timestamps();
        });

        // --- Module 3.2: logistics & travel ---------------------------------------
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('destination')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_user', function (Blueprint $table) {
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['trip_id', 'user_id']);
        });

        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // see App\Enums\CheckinType
            $table->string('note')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
            $table->index(['family_id', 'created_at']);
        });

        Schema::create('travel_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('traveller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('flight'); // flight | bus | train | hotel | other
            $table->string('title');
            $table->string('carrier')->nullable();
            $table->string('reference')->nullable(); // flight / bus number
            $table->string('booking_code')->nullable();
            $table->string('from_place')->nullable();
            $table->string('to_place')->nullable();
            $table->dateTime('departs_at')->nullable();
            $table->dateTime('arrives_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // --- Module 3.3: threads & notifications ----------------------------------
        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('icon')->default('message-circle');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['thread_id', 'id']);
        });

        Schema::create('thread_reads', function (Blueprint $table) {
            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->boolean('muted')->default(false);
            $table->primary(['thread_id', 'user_id']);
        });

        Schema::create('family_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel'); // logistics | projects | maintenance | chat | family
            $table->boolean('urgent')->default(false);
            $table->string('title');
            $table->string('body')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 1024);
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding')->default('aes128gcm');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'push_subscriptions', 'family_notifications', 'thread_reads', 'messages', 'threads',
            'travel_documents', 'checkins', 'trip_user', 'trips', 'maintenance_logs',
            'maintenance_items', 'attachments', 'task_links', 'task_checklist_items', 'tasks',
            'task_categories', 'invitations', 'family_members',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_family_id');
            $table->dropColumn(['avatar_color', 'preferences', 'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes']);
        });

        Schema::dropIfExists('families');
    }
};
