
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();

            $table->enum('category', [
                'AIK',
                'Kerumahsakitan',
                'Kompetensi',
            ]);

            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            $table->text('question');

            $table->text('option_a');
            $table->text('option_b');
            $table->text('option_c');
            $table->text('option_d');

            $table->enum('correct_answer', [
                'A', 'B', 'C', 'D',
            ]);

            $table->enum('difficulty', [
                'Mudah',
                'Sedang',
                'Sulit',
            ])->default('Sedang');

            $table->enum('status', [
                'Aktif',
                'Nonaktif',
            ])->default('Aktif');

            $table->timestamps();

            $table->index(['category', 'status']);
            $table->index(['unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
