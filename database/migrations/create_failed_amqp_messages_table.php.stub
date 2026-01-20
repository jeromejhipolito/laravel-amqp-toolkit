<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_amqp_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('queue_name');
            $table->string('routing_key');
            $table->string('exchange');
            $table->longText('payload');
            $table->longText('exception');
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            $table->timestamp('failed_at');
            $table->timestamp('last_retry_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->enum('status', ['failed', 'retrying', 'dead_letter'])->default('failed');
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index(['queue_name', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_amqp_messages');
    }
};
