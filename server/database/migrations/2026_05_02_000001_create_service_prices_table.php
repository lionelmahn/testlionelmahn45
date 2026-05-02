<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->string('currency_code', 3)->default('VND');
            $table->boolean('is_tax_inclusive')->default(true);
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->enum('status', ['scheduled', 'active', 'expired', 'cancelled'])->default('scheduled');
            $table->enum('proposal_status', ['approved', 'pending', 'rejected'])->default('approved');
            $table->string('reason', 255)->nullable();
            $table->string('rejected_reason', 255)->nullable();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['service_id', 'status']);
            $table->index(['service_id', 'effective_from', 'effective_to'], 'svc_prices_active_lookup');
            $table->index(['proposal_status', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prices');
    }
};
