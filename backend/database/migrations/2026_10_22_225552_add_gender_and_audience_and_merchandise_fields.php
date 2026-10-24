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
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'gender')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('gender', 20)->nullable()->after('email');
            });
        }

        if (Schema::hasTable('ems_events') && !Schema::hasColumn('ems_events', 'audience')) {
            Schema::table('ems_events', function (Blueprint $table) {
                $table->string('audience', 20)->default('both')->after('is_featured');
            });
        }

        if (Schema::hasTable('media') && !Schema::hasColumn('media', 'audience')) {
            Schema::table('media', function (Blueprint $table) {
                $table->string('audience', 20)->default('both')->after('display_name');
            });
        }

        if (Schema::hasTable('store_products') && !Schema::hasColumn('store_products', 'is_merchandise')) {
            Schema::table('store_products', function (Blueprint $table) {
                $table->boolean('is_merchandise')->default(false)->after('has_variants');
            });
        }

        if (Schema::hasTable('store_product_images')) {
            Schema::table('store_product_images', function (Blueprint $table) {
                if (!Schema::hasColumn('store_product_images', 'image_type')) {
                    $table->string('image_type', 20)->nullable()->after('image_url');
                }
                if (!Schema::hasColumn('store_product_images', 'view_type')) {
                    $table->string('view_type', 20)->nullable()->after('image_type');
                }
                if (!Schema::hasColumn('store_product_images', 'is_private')) {
                    $table->boolean('is_private')->default(false)->after('view_type');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'gender')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('gender');
            });
        }

        if (Schema::hasTable('ems_events') && Schema::hasColumn('ems_events', 'audience')) {
            Schema::table('ems_events', function (Blueprint $table) {
                $table->dropColumn('audience');
            });
        }

        if (Schema::hasTable('media') && Schema::hasColumn('media', 'audience')) {
            Schema::table('media', function (Blueprint $table) {
                $table->dropColumn('audience');
            });
        }

        if (Schema::hasTable('store_products') && Schema::hasColumn('store_products', 'is_merchandise')) {
            Schema::table('store_products', function (Blueprint $table) {
                $table->dropColumn('is_merchandise');
            });
        }

        if (Schema::hasTable('store_product_images')) {
            Schema::table('store_product_images', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('store_product_images', 'is_private')) {
                    $columns[] = 'is_private';
                }
                if (Schema::hasColumn('store_product_images', 'view_type')) {
                    $columns[] = 'view_type';
                }
                if (Schema::hasColumn('store_product_images', 'image_type')) {
                    $columns[] = 'image_type';
                }
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
