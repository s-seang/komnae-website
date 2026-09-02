<?php

namespace Database\Factories;

use App\Enums\MisspellingStatus;
use App\Models\Document;
use App\Models\Misspelling;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Misspelling>
 */
class MisspellingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'word' => 'លោគ',
            'start' => 0,
            'length' => 3,
            'suggestions' => ['លោក', 'លោភ'],
            'status' => MisspellingStatus::Open,
            'replacement' => null,
        ];
    }
}
