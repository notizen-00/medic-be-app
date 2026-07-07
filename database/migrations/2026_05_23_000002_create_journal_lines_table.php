<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();

            $table->string('account_code', 64);
            $table->string('account_name')->nullable();
            $table->string('line_description')->nullable();

            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);

            $table->timestamps();

            $table->index(['journal_entry_id', 'account_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};

