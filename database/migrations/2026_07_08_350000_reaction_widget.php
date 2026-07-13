<?php

use App\Models\ReactionType;
use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('emoji', 16);
            $table->string('label', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('series_reaction_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->foreignId('reaction_type_id')->constrained('reaction_types')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('voter_key', 64)->nullable();
            $table->timestamps();

            $table->unique(['series_id', 'user_id']);
            $table->unique(['series_id', 'voter_key']);
            $table->index(['series_id', 'reaction_type_id']);
        });

        $defaults = [
            ['emoji' => '👍', 'label' => 'Понравилось', 'sort_order' => 10],
            ['emoji' => '👎', 'label' => 'Не понравилось', 'sort_order' => 20],
            ['emoji' => '😄', 'label' => 'Забавный', 'sort_order' => 30],
            ['emoji' => '😡', 'label' => 'Ужасный', 'sort_order' => 40],
            ['emoji' => '🤯', 'label' => 'Шедевр', 'sort_order' => 50],
            ['emoji' => '😴', 'label' => 'Скучно', 'sort_order' => 60],
        ];

        foreach ($defaults as $row) {
            ReactionType::query()->create($row + ['is_active' => true]);
        }

        foreach ([
            'reactions_enabled' => '1',
            'reactions_badge' => 'ОЦЕНИТЕ',
            'reactions_title' => 'Как вам этот сериал?',
        ] as $key => $value) {
            SiteSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('series_reaction_votes');
        Schema::dropIfExists('reaction_types');
    }
};
