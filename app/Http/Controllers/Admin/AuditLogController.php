<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    private function authorizeAdmin(): void
    {
        if (!auth()->user()->is_admin) {
            abort(403);
        }
    }

    private function applyFilters($query, Request $request)
    {
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('model')) {
            $query->where('model', $request->model);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $query = AuditLog::with([
                'user:id,name,email',
                'message',
            ])
            ->orderBy('created_at', 'desc');

        $this->applyFilters($query, $request);

        return response()->json($query->paginate(50));
    }

    public function export(Request $request)
    {
        $this->authorizeAdmin();

        $query = AuditLog::with(['user:id,name,email', 'message'])
            ->orderBy('created_at', 'desc');

        $this->applyFilters($query, $request);

        $logs = $query->get();
        $date = now()->format('Y-m-d');

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID', 'Utilizador', 'Email', 'Data', 'Modelo',
                'Tokens Input', 'Tokens Output', 'Custo (USD)',
            ]);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->user?->name,
                    $log->user?->email,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->model,
                    $log->input_tokens,
                    $log->output_tokens,
                    number_format($log->cost_usd, 8, '.', ''),
                ]);
            }

            fclose($handle);
        }, "audit-logs-{$date}.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }
}
