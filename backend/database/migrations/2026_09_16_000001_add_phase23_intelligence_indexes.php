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
        if (Schema::hasTable('ems_registrations')) {
            Schema::table('ems_registrations', function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'idx_ems_regs_status_created');
            });
        }

        if (Schema::hasTable('ems_payments')) {
            Schema::table('ems_payments', function (Blueprint $table) {
                if (Schema::hasColumn('ems_payments', 'payment_method')) {
                    $table->index(['status', 'payment_method', 'created_at'], 'idx_ems_payments_stat_method_created');
                } elseif (Schema::hasColumn('ems_payments', 'provider')) {
                    $table->index(['status', 'provider', 'created_at'], 'idx_ems_payments_stat_method_created');
                } else {
                    $table->index(['status', 'created_at'], 'idx_ems_payments_stat_method_created');
                }
            });
        }

        if (Schema::hasTable('donations')) {
            Schema::table('donations', function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'idx_donations_status_created');
            });
        }

        if (Schema::hasTable('store_orders')) {
            Schema::table('store_orders', function (Blueprint $table) {
                $table->index(['payment_status', 'created_at'], 'idx_store_orders_paystat_created');
            });
        }

        if (Schema::hasTable('mlibms_loans')) {
            Schema::table('mlibms_loans', function (Blueprint $table) {
                $table->index(['returned_at', 'due_at', 'created_at'], 'idx_mlibms_loans_dates');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ems_registrations')) {
            Schema::table('ems_registrations', function (Blueprint $table) {
                $table->dropIndex('idx_ems_regs_status_created');
            });
        }

        if (Schema::hasTable('ems_payments')) {
            Schema::table('ems_payments', function (Blueprint $table) {
                $table->dropIndex('idx_ems_payments_stat_method_created');
            });
        }

        if (Schema::hasTable('donations')) {
            Schema::table('donations', function (Blueprint $table) {
                $table->dropIndex('idx_donations_status_created');
            });
        }

        if (Schema::hasTable('store_orders')) {
            Schema::table('store_orders', function (Blueprint $table) {
                $table->dropIndex('idx_store_orders_paystat_created');
            });
        }

        if (Schema::hasTable('mlibms_loans')) {
            Schema::table('mlibms_loans', function (Blueprint $table) {
                $table->dropIndex('idx_mlibms_loans_dates');
            });
        }
    }
};
