<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JournalsController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['draft', 'posted', 'void'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = JournalEntry::query()->with(['lines'])->withCount('lines')->latest('entry_date')->latest('id');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (!empty($validated['from'])) {
            $query->whereDate('entry_date', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $query->whereDate('entry_date', '<=', $validated['to']);
        }

        $journals = $query->paginate($request->input('per_page', 100));
        $journals->getCollection()->transform(function (JournalEntry $journalEntry) {
            $totalDebit = (float) $journalEntry->lines->sum('debit');
            $totalCredit = (float) $journalEntry->lines->sum('credit');

            $journalEntry->setAttribute('totals', [
                'debit' => $totalDebit,
                'credit' => $totalCredit,
            ]);
            $journalEntry->setAttribute('total_debit', $totalDebit);
            $journalEntry->setAttribute('total_credit', $totalCredit);
            $journalEntry->setAttribute('is_balanced', round($totalDebit, 2) === round($totalCredit, 2));

            return $journalEntry;
        });

        return response()->json([
            'message' => 'OK',
            'data' => $journals,
        ]);
    }

    public function show(JournalEntry $journalEntry)
    {
        $journalEntry->load('lines', 'createdBy');

        $totals = [
            'debit' => $journalEntry->lines->sum('debit'),
            'credit' => $journalEntry->lines->sum('credit'),
        ];

        return response()->json([
            'data' => $journalEntry,
            'totals' => $totals,
            'is_balanced' => bccomp((string) $totals['debit'], (string) $totals['credit'], 2) === 0,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'integer', 'min:1'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_code' => ['required', 'string', 'max:64'],
            'lines.*.account_name' => ['nullable', 'string', 'max:255'],
            'lines.*.line_description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lines = collect($validated['lines'])->map(function ($line) {
            return [
                'account_code' => $line['account_code'],
                'account_name' => $line['account_name'] ?? null,
                'line_description' => $line['line_description'] ?? null,
                'debit' => (string) ($line['debit'] ?? 0),
                'credit' => (string) ($line['credit'] ?? 0),
            ];
        });

        $totalDebit = $lines->sum(fn ($l) => (float) $l['debit']);
        $totalCredit = $lines->sum(fn ($l) => (float) $l['credit']);
        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return response()->json([
                'message' => 'Journal tidak balance (total debit harus sama dengan total credit).',
                'totals' => ['debit' => round($totalDebit, 2), 'credit' => round($totalCredit, 2)],
            ], 422);
        }

        $entry = DB::transaction(function () use ($validated, $lines, $request) {
            $entry = JournalEntry::create([
                'entry_date' => $validated['entry_date'],
                'description' => $validated['description'] ?? null,
                'reference_type' => $validated['reference_type'] ?? null,
                'reference_id' => $validated['reference_id'] ?? null,
                'status' => 'draft',
                'created_by_user_id' => $request->user()?->id,
            ]);

            $entry->lines()->createMany($lines->all());

            return $entry->load('lines');
        });

        return response()->json(['data' => $entry], 201);
    }

    public function post(JournalEntry $journalEntry)
    {
        $journalEntry->load('lines');

        $totalDebit = $journalEntry->lines->sum('debit');
        $totalCredit = $journalEntry->lines->sum('credit');
        if (bccomp((string) $totalDebit, (string) $totalCredit, 2) !== 0) {
            return response()->json([
                'message' => 'Journal tidak balance (total debit harus sama dengan total credit).',
                'totals' => ['debit' => $totalDebit, 'credit' => $totalCredit],
            ], 422);
        }

        if ($journalEntry->status !== 'draft') {
            return response()->json([
                'message' => 'Hanya journal status draft yang bisa di-posting.',
            ], 422);
        }

        $journalEntry->update([
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        return response()->json(['data' => $journalEntry->fresh(['lines'])]);
    }
}
