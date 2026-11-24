<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoint that powers the dependent dropdowns on lead forms. The response is limited to
 * what the requesting user may use, so changing the campaign ID in the URL reveals nothing extra.
 */
class LookupController extends Controller
{
    public function __construct(private readonly LeadService $leads) {}

    public function campaignOptions(Request $request, Campaign $campaign): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessCampaign($campaign->id), 403);

        return response()->json([
            'managers' => $this->leads->eligibleManagers($campaign->id),
            'admins' => $this->leads->canChooseOwner($user) ? $this->leads->eligibleAdmins($campaign->id) : [],
            ...$this->leads->statusOptions($campaign),
        ]);
    }
}
