<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\Misspelling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class CheckController extends Controller
{
    /** The limit we would like to accept, in kilobytes. */
    private const PREFERRED_UPLOAD_KB = 10240;

    /**
     * The workspace, with nothing checked yet.
     */
    public function index(): Response
    {
        return Inertia::render('check', [
            'maxUploadKilobytes' => self::maxUploadKilobytes(),
        ]);
    }

    /**
     * Accept an upload or pasted text and start the pipeline.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required_without:text', 'nullable', 'file', 'mimes:pdf,txt', 'max:'.self::maxUploadKilobytes()],
            'text' => ['required_without:file', 'nullable', 'string', 'max:50000'],
        ]);

        $file = $request->file('file');

        $document = Document::create([
            'original_name' => $file?->getClientOriginalName(),
            'mime' => $file?->getMimeType() ?? 'text/plain',
            'size_bytes' => $file?->getSize(),
            'path' => $file?->store('documents', 'local'),
            'status' => DocumentStatus::Pending,
            'text' => $file !== null ? null : $validated['text'],
        ]);

        if ($file !== null) {
            ProcessDocument::dispatch($document);
        } else {
            ProcessDocument::dispatchSync($document);
        }

        return to_route('check.show', $document);
    }

    /**
     * The workspace, showing one document and whatever the check has found so far.
     */
    public function show(Document $document): Response
    {
        return Inertia::render('check', [
            'maxUploadKilobytes' => self::maxUploadKilobytes(),
            'document' => fn (): array => $this->payload($document->fresh()->load('misspellings')),
        ]);
    }

    /**
     * The largest upload this installation can actually take.
     *
     * PHP refuses a body over post_max_size in global middleware, before the
     * session starts and long before validation runs, so an oversized file
     * cannot be turned into a form error. Advertising more than the runtime
     * allows just trades a clear message for a bare 413 — so we advertise the
     * smaller of the two and let the client stop the request being made.
     */
    public static function maxUploadKilobytes(): int
    {
        return (int) min(self::PREFERRED_UPLOAD_KB, UploadedFile::getMaxFilesize() / 1024);
    }

    /**
     * @return array{
     *     id: int,
     *     name: string|null,
     *     status: string,
     *     finished: bool,
     *     text: string|null,
     *     error: string|null,
     *     misspellings: list<array{id: int, word: string, start: int, length: int, suggestions: list<string>, status: string, replacement: string|null}>
     * }
     */
    private function payload(Document $document): array
    {
        return [
            'id' => $document->id,
            'name' => $document->original_name,
            'status' => $document->status->value,
            'finished' => $document->status->isFinished(),
            'text' => $document->text,
            'error' => $document->error,
            'misspellings' => array_values($document->misspellings->map(fn (Misspelling $misspelling): array => [
                'id' => $misspelling->id,
                'word' => $misspelling->word,
                'start' => $misspelling->start,
                'length' => $misspelling->length,
                'suggestions' => $misspelling->suggestions,
                'status' => $misspelling->status->value,
                'replacement' => $misspelling->replacement,
            ])->all()),
        ];
    }
}
