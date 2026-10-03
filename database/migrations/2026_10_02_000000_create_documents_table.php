<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('original_filename');
            $table->string('storage_disk', 64);
            $table->string('file_path', 1024);
            $table->string('mime_type', 255);
            $table->unsignedBigInteger('file_size');
            $table->string('public_token', 64)->unique();
            $table->timestamps();

            $table->index(['user_id', 'created_at', 'id'], 'documents_user_recent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
