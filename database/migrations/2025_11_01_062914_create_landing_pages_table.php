<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('content')->nullable(); // HTML content of the landing page
            $table->string('subdomain')->unique(); // e.g., username.affnet.app
            $table->string('domain')->nullable(); // Full domain if custom
            $table->enum('status', ['draft', 'active', 'expired'])->default('draft');
            $table->timestamp('expires_at')->nullable();
            $table->integer('credit_cost')->default(1); // Credits cost per 30 days
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('conversions')->default(0);
            $table->string('type')->default('template'); // 'template' or 'ai-generated'
            $table->json('metadata')->nullable(); // Additional data like template name, AI prompts, etc.
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('subdomain');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_pages');
    }
};
