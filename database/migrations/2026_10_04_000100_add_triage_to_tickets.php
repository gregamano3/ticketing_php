<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedTinyInteger('impact')->nullable()->after('source');
            $table->unsignedTinyInteger('urgency')->nullable()->after('impact');
            $table->timestamp('triaged_at')->nullable()->after('urgency');
            $table->foreignId('triaged_by')->nullable()->after('triaged_at')->constrained('users')->nullOnDelete();
            $table->index('triaged_at');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('default_priority_id')->nullable()->after('parent_id')->constrained('priorities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_priority_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['triaged_at']);
            $table->dropConstrainedForeignId('triaged_by');
            $table->dropColumn(['impact', 'urgency', 'triaged_at']);
        });
    }
};
