<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $query = Question::with('unit')->orderByDesc('created_at');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where('question', 'like', "%{$search}%");
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->query('unit_id'));
        }

        $questions = $query->get()->map(
            fn (Question $question) => $this->questionData($question)
        );

        return response()->json(['data' => $questions]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $this->validated($request);
        $this->normalizeUnit($data);

        $question = Question::create($data);

        return response()->json([
            'data' => $this->questionData($question->load('unit')),
        ], 201);
    }

    public function show(Request $request, Question $question): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json([
            'data' => $this->questionData($question->load('unit')),
        ]);
    }

    public function update(
        Request $request,
        Question $question
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $this->validated($request);
        $this->normalizeUnit($data);

        $question->update($data);

        return response()->json([
            'data' => $this->questionData($question->fresh()->load('unit')),
        ]);
    }

    public function destroy(
        Request $request,
        Question $question
    ): JsonResponse {
        $this->ensureAdmin($request);

        $question->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category' => [
                'required',
                'in:AIK,Kerumahsakitan,Kompetensi',
            ],
            'unit_id' => [
                'nullable',
                'integer',
                'exists:units,id',
            ],
            'question' => ['required', 'string', 'max:10000'],
            'optionA' => ['required', 'string', 'max:5000'],
            'optionB' => ['required', 'string', 'max:5000'],
            'optionC' => ['required', 'string', 'max:5000'],
            'optionD' => ['required', 'string', 'max:5000'],
            'correctAnswer' => ['required', 'in:A,B,C,D'],
            'difficulty' => ['required', 'in:Mudah,Sedang,Sulit'],
            'status' => ['required', 'in:Aktif,Nonaktif'],
        ]);

        if (
            $data['category'] === 'Kompetensi'
            && empty($data['unit_id'])
        ) {
            throw ValidationException::withMessages([
                'unit_id' => [
                    'Pilih unit kerja untuk soal kategori Kompetensi.',
                ],
            ]);
        }

        return [
            'category' => $data['category'],
            'unit_id' => $data['unit_id'] ?? null,
            'question' => $data['question'],
            'option_a' => $data['optionA'],
            'option_b' => $data['optionB'],
            'option_c' => $data['optionC'],
            'option_d' => $data['optionD'],
            'correct_answer' => $data['correctAnswer'],
            'difficulty' => $data['difficulty'],
            'status' => $data['status'],
        ];
    }

    private function normalizeUnit(array &$data): void
    {
        if ($data['category'] !== 'Kompetensi') {
            $data['unit_id'] = null;
        }
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            $request->attributes->get('authUser')?->role === 'admin',
            403,
            'Akses hanya untuk Admin CAT.'
        );
    }

    private function questionData(Question $question): array
    {
        return [
            'id' => $question->id,
            'category' => $question->category,
            'unit_id' => $question->unit_id,
            'unit' => $question->unit?->name ?? 'Umum',
            'question' => $question->question,
            'optionA' => $question->option_a,
            'optionB' => $question->option_b,
            'optionC' => $question->option_c,
            'optionD' => $question->option_d,
            'correctAnswer' => $question->correct_answer,
            'difficulty' => $question->difficulty,
            'status' => $question->status,
            'created_at' => $question->created_at,
            'updated_at' => $question->updated_at,
        ];
    }
}
