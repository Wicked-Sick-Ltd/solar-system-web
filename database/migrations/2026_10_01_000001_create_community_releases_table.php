<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_releases', function (Blueprint $table): void {
            $table->id();
            $table->string('version', 40)->unique();
            $table->char('commit', 40);
            $table->json('notes');
            $table->timestamp('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_releases');
    }
};
