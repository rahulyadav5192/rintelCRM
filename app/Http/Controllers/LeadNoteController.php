<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadNoteController extends Controller
{
    public function store(Request $request, Lead $lead, LeadService $leads): RedirectResponse
    {
        $this->authorize('addNote', $lead);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $leads->addNote($lead, $request->user(), $data['body']);

        return back()->with('success', 'Note added.');
    }
}
