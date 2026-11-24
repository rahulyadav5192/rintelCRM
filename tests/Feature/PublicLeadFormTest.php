<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\PublicLeadForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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
        $this->form = PublicLeadForm::ensureFor($this->adminA);
        $this->token = $this->form->plainToken();
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
        $row = DB::table('public_lead_forms')->find($this->form->id);
        $this->assertSame(hash('sha256', $this->token), $row->token_hash);
        $this->assertStringNotContainsString($this->token, json_encode($row));
    }

    public function test_public_form_renders_without_authentication_with_the_admins_campaigns(): void
    {
        $this->get("/public/leads/{$this->token}")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Public/LeadForm')->where('available', true)->has('campaigns', 1)->where('campaigns.0.name', 'IVA'));
    }

    public function test_public_submission_is_owned_by_the_forms_admin(): void
    {
        $this->post("/public/leads/{$this->token}", $this->submission(['admin_id' => $this->adminB->id]))->assertSessionHasNoErrors()->assertRedirect();

        $lead = Lead::where('first_name', 'Jane')->firstOrFail();
        $this->assertSame($this->adminA->id, $lead->admin_id);
        $this->assertSame('public', $lead->source);
        $this->assertNull($lead->created_by);
        $this->assertSame($this->managerA->id, $lead->assigned_to);
        $this->assertSame(1, $this->form->fresh()->submissions_count);

        // Visible to the owning admin only.
        $this->actingAs($this->adminA)->get("/leads/{$lead->id}")->assertOk();
        $this->actingAs($this->adminB)->get("/leads/{$lead->id}")->assertForbidden();
    }

    public function test_public_form_validates_campaign_and_manager(): void
    {
        // Solar is not assigned to adminA.
        $this->post("/public/leads/{$this->token}", $this->submission(['campaign_id' => $this->solar->id, 'assigned_to' => null]))->assertSessionHasErrors('campaign_id');
        $this->post("/public/leads/{$this->token}", $this->submission(['assigned_to' => $this->solarManager->id]))->assertSessionHasErrors('assigned_to');
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

        $this->form->update(['is_active' => false]);
        $this->post("/public/leads/{$newToken}", $this->submission())->assertNotFound();
    }

    public function test_inactive_admins_and_campaigns_cannot_receive_public_submissions(): void
    {
        $this->iva->is_public = false;
        $this->iva->save();
        $this->post("/public/leads/{$this->token}", $this->submission())->assertSessionHasErrors('campaign_id');

        $this->iva->is_public = true;
        $this->iva->is_active = false;
        $this->iva->save();
        $this->post("/public/leads/{$this->token}", $this->submission())->assertSessionHasErrors('campaign_id');

        $this->iva->is_active = true;
        $this->iva->save();
        $this->adminA->is_active = false;
        $this->adminA->save();
        $this->post("/public/leads/{$this->token}", $this->submission())->assertNotFound();
        $this->assertSame(0, Lead::where('first_name', 'Jane')->count());
    }

    public function test_only_super_admin_manages_public_urls_and_admins_see_only_their_own(): void
    {
        $this->actingAs($this->adminA)->post("/public-forms/{$this->form->id}/regenerate")->assertForbidden();
        $this->actingAs($this->adminA)->patch("/public-forms/{$this->form->id}/toggle")->assertForbidden();

        PublicLeadForm::ensureFor($this->adminB);
        $this->actingAs($this->adminA)->get('/public-forms')->assertOk()
            ->assertInertia(fn ($page) => $page->has('admins', 1)->where('admins.0.id', $this->adminA->id)->where('canManage', false));
        $this->actingAs($this->super)->get('/public-forms')->assertOk()->assertInertia(fn ($page) => $page->has('admins', 2));
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
            ->assertJsonCount(2, 'managers')
            ->assertJsonStructure(['managers' => [['id', 'name']]])
            ->assertJsonMissingPath('managers.0.email');
        $this->getJson("/public/leads/{$this->token}/campaigns/{$this->solar->id}")->assertNotFound();
        $other = Campaign::factory()->create();
        $this->getJson("/public/leads/{$this->token}/campaigns/{$other->id}")->assertNotFound();
    }
}
