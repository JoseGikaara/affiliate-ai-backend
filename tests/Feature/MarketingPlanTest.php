<?php

namespace Tests\Feature;

use App\Models\DropservicingMarketingPlan;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingPlanTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected CreditService $creditService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'credits' => 100,
        ]);

        $this->creditService = app(CreditService::class);
    }

    public function test_user_can_create_marketing_plan(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/dropservicing/marketing-plans', [
                'plan_type' => '7-day',
                'input_summary' => [
                    'audience' => 'Small business owners',
                    'platforms' => ['facebook', 'instagram'],
                    'goal' => 'brand awareness',
                    'tone' => 'professional',
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'plan' => [
                    'id',
                    'plan_type',
                    'status',
                    'credit_cost',
                ],
            ]);

        $this->assertDatabaseHas('dropservicing_marketing_plans', [
            'user_id' => $this->user->id,
            'plan_type' => '7-day',
            'status' => 'pending',
        ]);

        // Verify credits were deducted (8 credits for 7-day plan)
        $this->user->refresh();
        $this->assertEquals(92, $this->user->credits);
    }

    public function test_user_cannot_create_plan_with_insufficient_credits(): void
    {
        $this->user->update(['credits' => 5]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/dropservicing/marketing-plans', [
                'plan_type' => '7-day',
                'input_summary' => [
                    'audience' => 'Small business owners',
                    'platforms' => ['facebook'],
                    'goal' => 'brand awareness',
                ],
            ]);

        $response->assertStatus(402)
            ->assertJson([
                'message' => 'Insufficient credits. You need 8 credits to generate this marketing plan.',
            ]);
    }

    public function test_user_can_list_their_marketing_plans(): void
    {
        DropservicingMarketingPlan::create([
            'user_id' => $this->user->id,
            'plan_type' => '7-day',
            'input_summary' => [
                'audience' => 'Test audience',
                'platforms' => ['facebook'],
                'goal' => 'brand awareness',
            ],
            'status' => 'completed',
            'credit_cost' => 8,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dropservicing/marketing-plans');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'plan_type',
                    'status',
                ],
            ]);
    }

    public function test_user_can_regenerate_marketing_plan(): void
    {
        $plan = DropservicingMarketingPlan::create([
            'user_id' => $this->user->id,
            'plan_type' => '7-day',
            'input_summary' => [
                'audience' => 'Test audience',
                'platforms' => ['facebook'],
                'goal' => 'brand awareness',
            ],
            'status' => 'completed',
            'credit_cost' => 8,
            'ai_output' => 'Previous output',
        ]);

        $initialCredits = $this->user->credits;

        $response = $this->actingAs($this->user)
            ->postJson("/api/dropservicing/marketing-plans/{$plan->id}/regenerate");

        $response->assertStatus(200);

        // Verify credits were deducted again
        $this->user->refresh();
        $this->assertEquals($initialCredits - 8, $this->user->credits);

        // Verify plan was reset
        $plan->refresh();
        $this->assertEquals('pending', $plan->status);
        $this->assertNull($plan->ai_output);
    }
}


