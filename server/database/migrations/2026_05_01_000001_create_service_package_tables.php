<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('slug', 191)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();

            $table->enum('status', ['draft', 'active', 'hidden', 'discontinued'])->default('draft');
            $table->enum('visibility', ['public', 'internal'])->default('internal');

            $table->decimal('original_price', 15, 2)->default(0);
            $table->decimal('package_price', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('discount_percent', 6, 2)->default(0);

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('usage_validity_days')->nullable();
            $table->text('conditions')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('parent_package_id')->nullable()
                ->constrained('service_packages')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'visibility']);
            $table->index('effective_from');
            $table->index('effective_to');
        });

        Schema::create('service_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('service_packages')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['package_id', 'service_id']);
            $table->index('service_id');
        });

        Schema::create('service_package_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('service_packages')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('snapshot');
            $table->string('reason', 500)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['package_id', 'version_number']);
            $table->index('package_id');
        });

        Schema::create('service_package_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('service_packages')->cascadeOnDelete();
            $table->string('action', 60);
            $table->json('payload')->nullable();
            $table->string('reason', 500)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['package_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_package_history');
        Schema::dropIfExists('service_package_versions');
        Schema::dropIfExists('service_package_items');
        Schema::dropIfExists('service_packages');
    }
};
