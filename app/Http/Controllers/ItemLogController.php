<?php

namespace App\Http\Controllers;

use App\Http\Requests\Laptop\StoreItemLogRequest;
use App\Models\ItemLog;
use App\Models\Laptop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ItemLogController extends Controller
{
    private const SPEC_KEYS = ['cpu', 'gpu', 'memory', 'storage', 'display'];

    public function store(StoreItemLogRequest $request, Laptop $laptop): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $laptop) {
            $specBefore = array_filter($laptop->specification ?? []);
            $specAfter = null;

            if ($data['type'] === 'upgrade') {
                $specAfter = array_filter(
                    collect(self::SPEC_KEYS)->mapWithKeys(fn (string $key) => [$key => $data[$key] ?? null])->all(),
                );
            }

            $statusBefore = $laptop->status;
            $statusAfter = $data['type'] === 'repair' ? $data['status_after'] : null;

            ItemLog::create([
                'item_type' => $laptop->getMorphClass(),
                'item_id' => $laptop->id,
                'user_id' => $request->user()->id,
                'assigned_to' => $data['assigned_to'] ?? null,
                'type' => $data['type'],
                'description' => $data['description'],
                'spec_before' => $specBefore ?: null,
                'spec_after' => $specAfter ?: null,
                'status_before' => $statusBefore,
                'status_after' => $statusAfter,
            ]);

            $updates = [];

            if (! empty($specAfter)) {
                $updates['specification'] = array_merge($specBefore, $specAfter);
            }

            if ($statusAfter) {
                $updates['status'] = $statusAfter;
            }

            if ($updates) {
                $laptop->update($updates);
            }
        });

        return back()->with('success', 'Log aktivitas berhasil ditambahkan.');
    }

    public function toggleIssue(Request $request, Laptop $laptop, string $issueId): RedirectResponse
    {
        $issues = collect($laptop->issues());
        $issue = $issues->firstWhere('id', $issueId);

        abort_if($issue === null, 404);

        $solved = ! $issue['solved'];
        $issues = $issues->map(fn (array $row) => $row['id'] === $issueId
            ? [...$row, 'solved' => $solved, 'solved_at' => $solved ? now()->toIso8601String() : null]
            : $row);

        DB::transaction(function () use ($laptop, $issues, $issue, $solved, $request) {
            $laptop->update(['issue_notes' => $issues->values()->all()]);

            ItemLog::create([
                'item_type' => $laptop->getMorphClass(),
                'item_id' => $laptop->id,
                'user_id' => $request->user()->id,
                'type' => 'repair',
                'description' => ($solved ? 'Kendala selesai: ' : 'Kendala dibuka lagi: ').$issue['text'],
                'status_before' => $laptop->status,
                'status_after' => $laptop->status,
            ]);
        });

        return back()->with('success', $solved ? 'Kendala ditandai selesai.' : 'Kendala dibuka kembali.');
    }

    public function storeIssue(Request $request, Laptop $laptop): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:255'],
        ]);

        $issues = collect($laptop->issues())->push([
            'id' => (string) Str::uuid(),
            'text' => $data['text'],
            'solved' => false,
            'solved_at' => null,
        ]);

        DB::transaction(function () use ($laptop, $issues, $data, $request) {
            $laptop->update(['issue_notes' => $issues->values()->all()]);

            ItemLog::create([
                'item_type' => $laptop->getMorphClass(),
                'item_id' => $laptop->id,
                'user_id' => $request->user()->id,
                'type' => 'repair',
                'description' => 'Kendala baru: '.$data['text'],
                'status_before' => $laptop->status,
                'status_after' => $laptop->status,
            ]);
        });

        return back()->with('success', 'Kendala berhasil ditambahkan.');
    }

    public function destroy(Request $request, ItemLog $itemLog): RedirectResponse
    {
        abort_unless(
            $request->user()->id === $itemLog->user_id || $request->user()->hasAnyRole(['OWNER', 'SYSADMIN']),
            403,
        );

        $itemLog->delete();

        return back()->with('success', 'Log aktivitas dihapus.');
    }
}
