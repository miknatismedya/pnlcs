<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Hourly cloud billing (Viyo Cloud addon).
 *
 * viyo_hourly_services: one row per service sold by the hour, with its rates
 * and where charging has got to. Amounts carry four decimals (0,2550₺ an
 * hour); the client's balance has two, so the part of a kuruş not yet taken
 * waits in `carry`, and what could not be taken at all waits in `owed`.
 *
 * viyo_hourly_charges: every charge, for the customer's usage list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viyo_hourly_services', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->primary();
            $table->unsignedBigInteger('client_id')->index();
            $table->decimal('rate', 12, 4);
            $table->decimal('stopped_rate', 12, 4);
            $table->timestamp('charged_until')->nullable();
            $table->decimal('carry', 12, 6)->default(0);
            $table->decimal('owed', 12, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->timestamp('suspended_for_balance_at')->nullable();
            $table->timestamp('low_balance_notified_at')->nullable();
            $table->timestamp('terminate_requested_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('viyo_hourly_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id')->index();
            $table->unsignedBigInteger('client_id')->index();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->boolean('running');
            $table->decimal('amount', 12, 4);
            $table->timestamp('created_at')->nullable();
            $table->unique(['service_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viyo_hourly_charges');
        Schema::dropIfExists('viyo_hourly_services');
    }
};
