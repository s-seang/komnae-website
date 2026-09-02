<?php

namespace App\Models;

use App\Enums\MisspellingStatus;
use Database\Factories\MisspellingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_id
 * @property string $word
 * @property int $start Character offset into the document text, not bytes.
 * @property int $length Length in characters.
 * @property list<string> $suggestions
 * @property MisspellingStatus $status
 * @property string|null $replacement
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Document $document
 */
class Misspelling extends Model
{
    /** @use HasFactory<MisspellingFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MisspellingStatus::class,
            'suggestions' => 'array',
            'start' => 'integer',
            'length' => 'integer',
        ];
    }
}
