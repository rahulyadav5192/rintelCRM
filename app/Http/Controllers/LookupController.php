<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Campaign;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints that power dependent dropdowns on lead forms. Every response is limited to
 * what the requesting user is allowed to use, so changing IDs in the URL reveals nothing extra.
 */
class LookupController extends Controller
{
    public function __construct(private readonly LeadService $leads) {}

    public function branchCampaigns(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($request->user()->canAccessBranch($branch->id), 403);

        return response()->json(['campaigns' => $this->leads->creatableCampaigns($request->user(), $branch->id)]);
    }

    public function campaignOptions(Request $request, Branch $branch, Campaign $campaign): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessBranch($branch->id) && $user->canAccessCampaign($campaign->id), 403);

        return response()->json([
            'managers' => $this->leads->eligibleManagers($branch->id, $campaign->id),
            ...$this->leads->statusOptions($campaign),
        ]);
    }
}
