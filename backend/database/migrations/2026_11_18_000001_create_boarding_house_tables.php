<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('address');
            $table->string('contact_number', 30)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('room_number', 50);
            $table->string('floor', 50)->nullable();
            $table->unsignedInteger('capacity');
            $table->decimal('monthly_rate', 12, 2);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['property_id', 'room_number']);
            $table->index('status');
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('email')->nullable();
            $table->string('contact_number', 30);
            $table->string('address')->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_number', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('status');
            $table->index('last_name');
        });

        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('lease_number', 50)->unique();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('monthly_rent', 12, 2);
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->text('terms')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index('status');
            $table->index('start_date');
        });

        Schema::create('room_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->date('move_in_date');
            $table->date('move_out_date')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->index(['room_id', 'status']);
            $table->index('move_in_date');
        });

        Schema::create('billing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->restrictOnDelete();
            $table->string('billing_number', 50)->unique();
            $table->date('billing_date');
            $table->date('due_date');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('unpaid');
            $table->timestamps();

            $table->index('status');
            $table->index('due_date');
            $table->index('billing_date');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_record_id')->constrained()->restrictOnDelete();
            $table->string('payment_reference', 100)->nullable()->unique();
            $table->date('payment_date');
            $table->decimal('amount_paid', 12, 2);
            $table->string('payment_method', 30);
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('billing_records');
        Schema::dropIfExists('room_assignments');
        Schema::dropIfExists('leases');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('properties');
    }
};
