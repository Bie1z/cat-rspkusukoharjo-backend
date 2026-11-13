
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_assignments', function (Blueprint $table) {
            $table->id();

            // Paket ujian
            $table->foreignId('exam_id')
                ->constrained('exams')
                ->restrictOnDelete();

            // Pegawai yang ditugaskan
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Admin yang menetapkan peserta
            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('assigned_at')->useCurrent();

            $table->timestamps();

            // Pegawai tidak boleh ditugaskan dua kali
            // ke paket ujian yang sama
            $table->unique(['exam_id', 'user_id']);

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_assignments');
    }
};
