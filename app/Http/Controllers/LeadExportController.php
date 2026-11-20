<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\ActivityLogger;
use App\Services\LeadFilters;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeadExportController extends Controller
{
    /**
     * Exports exactly what the lead listing would show for the same filters — built on the
     * same LeadFilters::query(), which is rooted in Lead::visibleTo().
     */
    public function __invoke(Request $request): BinaryFileResponse
    {
        $this->authorize('export', Lead::class);
        $user = $request->user();
        $filters = LeadFilters::fromRequest($request);
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';

        $query = LeadFilters::applySort(LeadFilters::query($user, $filters), $filters)
            ->with(['branch:id,name', 'campaign:id,name', 'assignee:id,name', 'creator:id,name', 'leadStatus:id,name', 'paymentStatus:id,name']);

        $count = (clone $query)->count();
        ActivityLogger::log('exports', 'leads.exported', "Exported {$count} leads as ".strtoupper($format), null, null, ['filters' => $filters, 'count' => $count, 'format' => $format]);

        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $lead) {
                yield [
                    $lead->lead_number, $lead->lead_date?->format('Y-m-d'), $lead->title, $lead->first_name, $lead->last_name,
                    $lead->mobile, $lead->email, $lead->house_number, $lead->post_code,
                    $lead->branch?->name, $lead->campaign?->name, $lead->assignee?->name,
                    $lead->leadStatus?->name, $lead->paymentStatus?->name, $lead->follow_up_date?->format('Y-m-d'),
                    $lead->source, $lead->creator?->name, $lead->created_at?->format('Y-m-d H:i'), $lead->notes,
                ];
            }
        })();

        return SpreadsheetExporter::download($format, 'leads', [
            'Lead ID', 'Date', 'Title', 'First Name', 'Last Name', 'Mobile', 'Email', 'House Number', 'Post Code',
            'Branch', 'Campaign', 'Advisor', 'Lead Status', 'Payment Status', 'Follow-up Date', 'Source', 'Created By', 'Created At', 'Notes',
        ], $rows);
    }
}
