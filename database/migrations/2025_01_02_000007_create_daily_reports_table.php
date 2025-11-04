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
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->nullable()->constrained()->onDelete('cascade');
            $table->date('report_date');
            $table->integer('total_clicks')->default(0);
            $table->integer('total_conversions')->default(0);
            $table->decimal('total_earnings', 10, 2)->default(0);
            $table->text('summary')->nullable(); // JSON for additional stats
            $table->timestamps();
            
            $table->unique(['affiliate_id', 'report_date']);
            $table->index('report_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};

