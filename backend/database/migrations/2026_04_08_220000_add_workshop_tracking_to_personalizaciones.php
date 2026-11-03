<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personalizaciones', function (Blueprint $table) {
            $table->timestamp('processing_started_at')->nullable()->after('notas');
            $table->timestamp('qc_started_at')->nullable()->after('processing_started_at');
            $table->unsignedBigInteger('parent_split_id')->nullable()->after('qc_started_at');
            $table->boolean('qc_blocked')->default(false)->after('parent_split_id');
            $table->unsignedBigInteger('assigned_user_id')->nullable()->after('qc_blocked');

            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('parent_split_id');
        });
    }

    public function down(): void
    {
        Schema::table('personalizaciones', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
            $table->dropIndex(['parent_split_id']);
            $table->dropColumn([
                'processing_started_at',
                'qc_started_at',
                'parent_split_id',
                'qc_blocked',
                'assigned_user_id',
            ]);
        });
    }
};
