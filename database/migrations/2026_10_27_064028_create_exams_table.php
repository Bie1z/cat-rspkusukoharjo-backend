
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description')->nullable();

            // Jadwal tetap untuk seluruh peserta
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            // Komposisi tetap: total 30 soal
            $table->unsignedTinyInteger('aik_count')
                ->default(10);

            $table->unsignedTinyInteger('hospital_count')
                ->default(10);

            $table->unsignedTinyInteger('competency_count')
                ->default(10);

            // Draft / published / archived
            $table->enum('status', [
                'draft',
                'published',
                'archived',
            ])->default('draft');

            // Admin yang membuat ujian
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
