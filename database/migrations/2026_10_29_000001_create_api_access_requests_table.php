<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_access_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('organisation', 200);
            $table->string('contact_name', 200);
            $table->string('email', 254);
            $table->text('use_case');
            $table->string('expected_volume', 40);
            $table->text('egress_cidrs')->nullable();
            $table->boolean('institution_eligible')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_access_requests');
    }
};
