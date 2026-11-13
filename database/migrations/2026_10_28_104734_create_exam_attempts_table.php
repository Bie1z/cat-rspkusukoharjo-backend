
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exam_assignment_id')
                ->unique()
                ->constrained('exam_assignments')
                ->restrictOnDelete();

            $table->dateTime('started_at');

            $table->dateTime('submitted_at')->nullable();

            $table->enum('status', [
                'in_progress',
                'submitted',
                'timed_out',
            ])->default('in_progress');

            $table->unsignedTinyInteger('correct_count')
                ->nullable();

            $table->unsignedTinyInteger('wrong_count')
                ->nullable();

            $table->unsignedTinyInteger('unanswered_count')
                ->nullable();

            $table->decimal('score', 5, 2)->nullable();

            $table->timestamps();

            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
