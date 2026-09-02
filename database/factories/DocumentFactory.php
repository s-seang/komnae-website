<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'original_name' => null,
            'path' => null,
            'mime' => 'text/plain',
            'size_bytes' => null,
            'status' => DocumentStatus::Completed,
            'text' => 'សួស្តីពិភពលោក',
            'error' => null,
        ];
    }

    /**
     * An upload waiting on the queue: a stored file and no text yet.
     */
    public function uploaded(): static
    {
        return $this->state(fn (): array => [
            'original_name' => 'komnot.pdf',
            'path' => 'documents/komnot.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 1024,
            'status' => DocumentStatus::Pending,
            'text' => null,
        ]);
    }
}
