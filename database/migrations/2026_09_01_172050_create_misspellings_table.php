<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('misspellings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('word');
            $table->unsignedInteger('start')->comment('Character offset into documents.text, not bytes.');
            $table->unsignedInteger('length')->comment('Length in characters.');
            $table->json('suggestions');
            $table->string('status')->index();
            $table->string('replacement')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('misspellings');
    }
};
