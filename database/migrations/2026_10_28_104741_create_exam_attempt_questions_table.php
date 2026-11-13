
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempt_questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exam_attempt_id')
                ->constrained('exam_attempts')
                ->restrictOnDelete();

            $table->foreignId('question_id')
                ->nullable()
                ->constrained('questions')
                ->nullOnDelete();

            $table->unsignedTinyInteger('question_number');

            $table->enum('category', [
                'AIK',
                'Kerumahsakitan',
                'Kompetensi',
            ]);

            // Salinan soal pada saat sesi dimulai
            $table->text('question_text');

            $table->text('option_a');
            $table->text('option_b');
            $table->text('option_c');
            $table->text('option_d');

            // Kunci hanya boleh digunakan backend
            $table->enum('correct_answer', [
                'A', 'B', 'C', 'D',
            ]);

            // Jawaban peserta
            $table->enum('selected_answer', [
                'A', 'B', 'C', 'D',
            ])->nullable();

            $table->dateTime('answered_at')->nullable();

            $table->timestamps();

            $table->unique([
                'exam_attempt_id',
                'question_number',
            ], 'attempt_question_number_unique');

            $table->unique([
                'exam_attempt_id',
                'question_id',
            ], 'attempt_question_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_questions');
    }
};
