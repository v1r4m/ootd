<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outfits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->date('worn_on')->unique();
            $table->text('description');
            $table->text('prompt')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('engine')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outfits');
    }
};
