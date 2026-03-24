<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * حذف الأعمدة: room_suite, multi_room, additional_rooms_ids من جدول reservations
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // حذف الأعمدة غير المطلوبة
            $table->dropColumn([
                'room_suite',
                'room_id',
                'multi_room',
                'additional_rooms_ids'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // إعادة إضافة الأعمدة المحذوفة (للتراجع)
            $table->tinyInteger('room_suite')->default(0)->comment('0 for room, 1 for suite');
            $table->tinyInteger('multi_room')->default(0)->comment('0 for single room, 1 for multiple rooms');
            $table->string('additional_rooms_ids')->comment('the additional rooms ids seperated by "-" ');
        });
    }
};

