<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tooth_status_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'display_order']);
        });

        Schema::create('tooth_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->foreignId('tooth_status_group_id')
                ->constrained('tooth_status_groups')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('color', 16);
            $table->string('icon', 32)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'display_order']);
            $table->index('tooth_status_group_id');
        });

        Schema::create('tooth_status_proposals', function (Blueprint $table) {
            $table->id();
            $table->enum('action', ['create', 'update']);
            $table->foreignId('tooth_status_id')->nullable()
                ->constrained('tooth_statuses')->nullOnDelete();
            $table->json('payload');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_note', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('tooth_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tooth_status_id')->nullable()
                ->constrained('tooth_statuses')->nullOnDelete();
            $table->string('action', 50);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tooth_status_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tooth_status_history');
        Schema::dropIfExists('tooth_status_proposals');
        Schema::dropIfExists('tooth_statuses');
        Schema::dropIfExists('tooth_status_groups');
    }
};
