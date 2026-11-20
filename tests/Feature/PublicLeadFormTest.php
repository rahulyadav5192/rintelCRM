<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\PublicLeadForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\CrmScenario;
use Tests\TestCase;

class PublicLeadFormTest extends TestCase
{
    use CrmScenario, RefreshDatabase;

    private PublicLeadForm $form;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildScenario();
        $this->form = new PublicLeadForm(['is_active' => true]);
        $this->form->branch_id = $this->branchA->id;
        $this->token = $this->form->rotateToken();
        $this->form->save();
    }

    private function submission(array $overrides = []): array
    {
        return [
            'title' => 'Ms',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'mobile' => '+447700900555',
            'email' => 'jane@example.com',
            'post_code' => 'EC1A 1BB',
            'campaign_id' => $this->iva->id,
            'assigned_to' => $this->managerA->id,
            'form_token' => Crypt::encryptString((string) now()->subSeconds(10)->timestamp),
            'website' => '',
            ...$overrides,
        ];
    }

    public function test_token_is_stored_hashed_not_in_plain_text(): void
    {
        $row = \DB::table('public_lead_forms')->find($this->form->id);
        $this->assertSame(hash('sha256', $this->token), $row->token_hash);
        $this->assertStringNotContainsString($this->token, json_encode($row));
    }

    public function test_public_form_renders_without_authentication(): void
    {
        $this->get("/public/leads/{$this->token}")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Public/LeadForm')->where('available', true)->has('campaigns', 2));
    }

    public function test_public_form_automatically_assigns_the_correct_branch(): void
    {
        // A branch_id in the payload is ignored completely.
        $this->post("/public/leads/{$this->token}", $this->submission(['branch_id' => $this->branchB->id]))->assertSessionHasNoErrors()->assertRedirect();

        $lead = Lead::where('first_name', 'Jane')->firstOrFail();
        $this->assertSame($this->branchA->id, $lead->branch_id);
        $this->assertSame('public', $lead->source);
        $this->assertNull($lead->created_by);
        $this->assertSame($this->managerA->id, $lead->assigned_to);
        $this->assertSame($this->form->id, $lead->public_lead_form_id);
        $this->assertSame(1, $this->form->fresh()->submissions_count);
    }

    public function test_public_form_validates_campaign_and_manager_belong_to_branch(): void
    {
        $other = \App\Models\Campaign::factory()->forBranches([$this->branchB])->create();
        $this->post("/public/leads/{$this->token}", $this->submission(['campaign_id' => $other->id, 'assigned_to' => null]))->assertSessionHasErrors('campaign_id');
        $this->post("/public/leads/{$this->token}", $this->submission(['assigned_to' => $this->managerB->id]))->assertSessionHasErrors('assigned_to');
        $this->post("/public/leads/{$this->token}", $this->submission(['assigned_to' => $this->solarManagerA->id]))->assertSessionHasErrors('assigned_to');
        $this->post("/public/leads/{$this->token}", $this->submission(['mobile' => 'abc', 'email' => 'nope']))->assertSessionHasErrors(['mobile', 'email']);
        $this->assertSame(0, Lead::where('first_name', 'Jane')->count());
    }

    public function test_hidden_statuses_cannot_be_injected(): void
    {
        $completed = $this->iva->leadStatuses()->where('name', 'Completed')->first();
        $this->post("/public/leads/{$this->token}", $this->submission(['lead_status_id' => $completed->id]))->assertSessionHasNoErrors();
        $this->assertSame($this->iva->defaultLeadStatus()->id, Lead::where('first_name', 'Jane')->first()->lead_status_id);
    }

    public function test_invalid_or_reset_urls_cannot_submit_leads(): void
    {
        $this->post('/public/leads/'.str_repeat('a', 48), $this->submission())->assertNotFound();

        $this->actingAs($this->super)->post("/public-forms/{$this->form->id}/regenerate")->assertRedirect();
        auth()->logout();
        $this->post("/public/leads/{$this->token}", $this->submission())->assertNotFound();
        $this->get("/public/leads/{$this->token}")->assertOk()->assertInertia(fn ($page) => $page->where('available', false));

        $newToken = $this->form->fresh()->plainToken();
        $this->assertNotSame($this->token, $newToken);
        $this->post("/public/leads/{$newToken}", $this->submission())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activity_logs', ['action' => 'public_form.reset']);

        // Deactivated URL.
        $this->form->update(['is_active' => false]);
        $this->post("/public/leads/{$newToken}", $this->submission())->assertNotFound();
    }

    public function test_inactive_branches_and_campaigns_cannot_receive_public_submissions(): void
    {
        $this->iva->is_active = false;
        $this->iva->save();
        $this->post("/public/leads/{$this->token}", $this->submission())->assertSessionHasErrors('campaign_id');

        $this->solar->is_public = false;
        $this->solar->save();
        $this->post("/public/leads/{$this->token}", $this->submission(['campaign_id' => $this->solar->id, 'assigned_to' => null]))->assertSessionHasErrors('campaign_id');

        $this->branchA->is_active = false;
        $this->branchA->save();
        $this->post("/public/leads/{$this->token}", $this->submission())->assertNotFound();
        $this->assertSame(0, Lead::where('first_name', 'Jane')->count());
    }

    public function test_spam_protection_rejects_bots(): void
    {
        $this->post("/public/leads/{$this->token}", $this->submission(['website' => 'http://spam']))->assertSessionHasErrors('form');
        $this->post("/public/leads/{$this->token}", $this->submission(['form_token' => Crypt::encryptString((string) now()->timestamp)]))->assertSessionHasErrors('form');
        $this->post("/public/leads/{$this->token}", $this->submission(['form_token' => 'garbage']))->assertSessionHasErrors('form');
    }

    public function test_public_submissions_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post("/public/leads/{$this->token}", $this->submission(['mobile' => '0770090010'.$i]));
        }
        $this->post("/public/leads/{$this->token}", $this->submission())->assertStatus(429);
    }

    public function test_public_options_expose_only_names_of_eligible_managers(): void
    {
        $this->getJson("/public/leads/{$this->token}/campaigns/{$this->iva->id}")->assertOk()
            ->assertExactJson(['managers' => [['id' => $this->managerA->id, 'name' => $this->managerA->name]], 'lead_statuses' => [], 'payment_statuses' => []]);
        $other = \App\Models\Campaign::factory()->forBranches([$this->branchB])->create();
        $this->getJson("/public/leads/{$this->token}/campaigns/{$other->id}")->assertNotFound();
    }
}
