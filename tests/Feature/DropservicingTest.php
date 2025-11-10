<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Modules\Dropservicing\ServiceCategory;
use App\Models\Modules\Dropservicing\Service;
use App\Models\Modules\Dropservicing\UserGig;
use App\Models\Modules\Dropservicing\GigOrder;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DropservicingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ServiceCategory $category;
    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'credits' => 100,
        ]);

        $this->category = ServiceCategory::create([
            'name' => 'Content Writing',
        ]);

        $this->service = Service::create([
            'category_id' => $this->category->id,
            'title' => 'Blog Post Writing',
            'description' => 'AI-powered blog post writing service',
            'base_credit_cost' => 10,
            'ai_prompt_template' => [
                'prompt' => 'Write a {word_count}-word blog post about {topic}',
                'model' => 'gpt-4o-mini',
                'max_tokens' => 1500,
            ],
        ]);
    }

    public function test_user_can_create_gig(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/dropservicing/gigs', [
                'service_id' => $this->service->id,
                'title' => 'Professional Blog Writing',
                'description' => 'I write professional blog posts',
                'pricing_tiers' => [
                    'basic' => [
                        'name' => 'Basic',
                        'price' => 10,
                        'description' => 'Basic package',
                        'delivery_time' => '24 hours',
                        'features' => [],
                    ],
                    'standard' => [
                        'name' => 'Standard',
                        'price' => 25,
                        'description' => 'Standard package',
                        'delivery_time' => '12 hours',
                        'features' => [],
                    ],
                    'premium' => [
                        'name' => 'Premium',
                        'price' => 50,
                        'description' => 'Premium package',
                        'delivery_time' => '6 hours',
                        'features' => [],
                    ],
                ],
                'paypal_email' => 'test@example.com',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'gig' => [
                    'id',
                    'title',
                    'slug',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('user_gigs', [
            'user_id' => $this->user->id,
            'title' => 'Professional Blog Writing',
        ]);

        // Verify credits were deducted (20 credits for gig creation)
        $this->user->refresh();
        $this->assertEquals(80, $this->user->credits);
    }

    public function test_user_cannot_create_gig_with_insufficient_credits(): void
    {
        $this->user->update(['credits' => 10]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/dropservicing/gigs', [
                'service_id' => $this->service->id,
                'title' => 'Professional Blog Writing',
                'description' => 'I write professional blog posts',
                'pricing_tiers' => [
                    'basic' => ['name' => 'Basic', 'price' => 10, 'description' => '', 'delivery_time' => '', 'features' => []],
                    'standard' => ['name' => 'Standard', 'price' => 25, 'description' => '', 'delivery_time' => '', 'features' => []],
                    'premium' => ['name' => 'Premium', 'price' => 50, 'description' => '', 'delivery_time' => '', 'features' => []],
                ],
                'paypal_email' => 'test@example.com',
            ]);

        $response->assertStatus(402)
            ->assertJson([
                'message' => 'Insufficient credits. You need 20 credits to create a gig.',
            ]);
    }

    public function test_user_can_list_their_gigs(): void
    {
        UserGig::create([
            'user_id' => $this->user->id,
            'service_id' => $this->service->id,
            'title' => 'Test Gig',
            'description' => 'Test description',
            'pricing_tiers' => [
                'basic' => ['name' => 'Basic', 'price' => 10, 'description' => '', 'delivery_time' => '', 'features' => []],
                'standard' => ['name' => 'Standard', 'price' => 25, 'description' => '', 'delivery_time' => '', 'features' => []],
                'premium' => ['name' => 'Premium', 'price' => 50, 'description' => '', 'delivery_time' => '', 'features' => []],
            ],
            'paypal_email' => 'test@example.com',
            'status' => 'active',
            'slug' => 'test-gig-' . uniqid(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dropservicing/gigs');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'title',
                    'slug',
                    'status',
                ],
            ]);
    }

    public function test_user_can_delete_their_gig(): void
    {
        $gig = UserGig::create([
            'user_id' => $this->user->id,
            'service_id' => $this->service->id,
            'title' => 'Test Gig',
            'description' => 'Test description',
            'pricing_tiers' => [
                'basic' => ['name' => 'Basic', 'price' => 10, 'description' => '', 'delivery_time' => '', 'features' => []],
                'standard' => ['name' => 'Standard', 'price' => 25, 'description' => '', 'delivery_time' => '', 'features' => []],
                'premium' => ['name' => 'Premium', 'price' => 50, 'description' => '', 'delivery_time' => '', 'features' => []],
            ],
            'paypal_email' => 'test@example.com',
            'status' => 'active',
            'slug' => 'test-gig-' . uniqid(),
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/dropservicing/gigs/{$gig->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('user_gigs', ['id' => $gig->id]);
    }
}

