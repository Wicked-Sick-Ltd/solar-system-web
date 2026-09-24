<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visibility_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('object_id');
            $table->decimal('latitude', 5, 2);
            $table->decimal('longitude', 6, 2);
            $table->boolean('active')->default(true);
            $table->boolean('last_state_up_after_dark')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'object_id', 'latitude', 'longitude'], 'visibility_alerts_unique');
            $table->index(['active', 'object_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visibility_alerts');
    }
};
