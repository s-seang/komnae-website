<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\MisspellingStatus;
use App\Http\Controllers\CheckController;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\Misspelling;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_visit_the_landing_page()
    {
        $response = $this->get(route('home'));
        $response->assertOk();
    }

    public function test_guests_can_visit_the_check_workspace()
    {
        $response = $this->get(route('check'));
        $response->assertOk();
    }

    public function test_authenticated_users_can_visit_the_check_workspace()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('check'));
        $response->assertOk();
    }

    public function test_pasted_text_is_checked_without_waiting_for_the_queue()
    {
        $response = $this->post(route('check.store'), ['text' => 'សួស្តីពិភពលោគ']);

        $document = Document::sole();
        $response->assertRedirect(route('check.show', $document));

        $this->assertSame(DocumentStatus::Completed, $document->status);
        $this->assertSame('លោគ', $document->misspellings->sole()->word);
        $this->assertContains('លោក', $document->misspellings->sole()->suggestions);
    }

    public function test_an_uploaded_file_is_stored_and_queued()
    {
        Storage::fake('local');
        Queue::fake();

        $this->post(route('check.store'), [
            'file' => UploadedFile::fake()->createWithContent('អត្ថបទ.txt', 'សួស្តីពិភពលោក'),
        ]);

        $document = Document::sole();

        $this->assertSame('អត្ថបទ.txt', $document->original_name);
        $this->assertSame(DocumentStatus::Pending, $document->status);
        Storage::disk('local')->assertExists($document->path);
        Queue::assertPushed(ProcessDocument::class);
    }

    /** The stored name is generated, so a crafted filename never reaches the extractor. */
    public function test_the_stored_path_does_not_use_the_uploaded_filename()
    {
        Storage::fake('local');
        Queue::fake();

        $this->post(route('check.store'), [
            'file' => UploadedFile::fake()->createWithContent('; rm -rf /.txt', 'សួស្តី'),
        ]);

        $this->assertStringNotContainsString('rm -rf', Document::sole()->path);
    }

    public function test_an_upload_runs_the_whole_pipeline_through_the_queue()
    {
        Storage::fake('local');

        $this->post(route('check.store'), [
            'file' => UploadedFile::fake()->createWithContent('a.txt', 'ខ្ញុំចូលចិត្តរៀនភាសាខ្មែរណាស'),
        ]);

        $document = Document::sole();

        $this->assertSame(DocumentStatus::Completed, $document->status);
        $this->assertSame('ណាស', $document->misspellings->sole()->word);
    }

    /**
     * The headline case: a PDF that already carries a Khmer text layer, read
     * through poppler. The fixture embeds a real CID Khmer font, so this
     * exercises the extractor rather than a plain-text short cut.
     */
    public function test_a_pdf_with_a_text_layer_is_extracted_and_checked()
    {
        Storage::fake('local');

        $this->post(route('check.store'), [
            'file' => new UploadedFile(
                base_path('tests/Fixtures/khmer-text-layer.pdf'),
                'khmer.pdf',
                'application/pdf',
                test: true,
            ),
        ]);

        $document = Document::sole();

        $this->assertSame(DocumentStatus::Completed, $document->status);
        $this->assertStringContainsString('ភាសាខ្មែរ', (string) $document->text);
        $this->assertEqualsCanonicalizing(
            ['លោគ', 'ណាស'],
            $document->misspellings->pluck('word')->all(),
        );
    }

    /** Latin-only text extracts fine but has nothing Khmer to check. */
    public function test_a_file_with_no_khmer_is_reported_rather_than_checked()
    {
        Storage::fake('local');

        $this->post(route('check.store'), [
            'file' => UploadedFile::fake()->createWithContent('a.txt', 'Nothing Khmer in here at all.'),
        ]);

        $document = Document::sole();

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertNotNull($document->error);
        $this->assertSame(0, $document->misspellings()->count());
    }

    public function test_a_file_with_no_text_layer_asks_for_ocr()
    {
        Storage::fake('local');

        $this->post(route('check.store'), [
            'file' => UploadedFile::fake()->createWithContent('scan.txt', '   '),
        ]);

        $this->assertSame(DocumentStatus::NeedsOcr, Document::sole()->status);
    }

    public function test_it_rejects_an_unsupported_file_type()
    {
        $this->post(route('check.store'), ['file' => UploadedFile::fake()->create('notes.docx')])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_it_rejects_a_file_over_the_size_limit()
    {
        $overLimit = CheckController::maxUploadKilobytes() + 1;

        $this->post(route('check.store'), ['file' => UploadedFile::fake()->create('big.pdf', $overLimit)])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    /**
     * PHP drops an oversized body before validation runs, so the limit the page
     * advertises has to be one the runtime will actually accept.
     */
    public function test_the_advertised_limit_never_exceeds_what_php_accepts()
    {
        $this->assertLessThanOrEqual(
            UploadedFile::getMaxFilesize() / 1024,
            CheckController::maxUploadKilobytes(),
        );

        $this->get(route('check'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('check')
                ->where('maxUploadKilobytes', CheckController::maxUploadKilobytes())
            );
    }

    public function test_it_rejects_a_submission_with_neither_a_file_nor_text()
    {
        $this->post(route('check.store'), [])->assertSessionHasErrors(['file', 'text']);
    }

    public function test_the_workspace_returns_the_document_and_its_misspellings()
    {
        $misspelling = Misspelling::factory()->create();

        $this->get(route('check.show', $misspelling->document))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('check')
                ->where('document.status', DocumentStatus::Completed->value)
                ->where('document.finished', true)
                ->where('document.misspellings.0.word', 'លោគ')
            );
    }

    public function test_accepting_a_suggestion_records_the_replacement()
    {
        $misspelling = Misspelling::factory()->create();

        $this->patch(route('misspellings.update', $misspelling), [
            'status' => 'accepted',
            'replacement' => 'លោក',
        ])->assertRedirect();

        $misspelling->refresh();

        $this->assertSame(MisspellingStatus::Accepted, $misspelling->status);
        $this->assertSame('លោក', $misspelling->replacement);
    }

    public function test_ignoring_a_misspelling_clears_any_replacement()
    {
        $misspelling = Misspelling::factory()->create([
            'status' => MisspellingStatus::Accepted,
            'replacement' => 'លោក',
        ]);

        $this->patch(route('misspellings.update', $misspelling), ['status' => 'ignored']);

        $misspelling->refresh();

        $this->assertSame(MisspellingStatus::Ignored, $misspelling->status);
        $this->assertNull($misspelling->replacement);
    }

    /** A replacement is user input: only a word we actually suggested may be stored. */
    public function test_it_rejects_a_replacement_that_was_never_suggested()
    {
        $misspelling = Misspelling::factory()->create();

        $this->patch(route('misspellings.update', $misspelling), [
            'status' => 'accepted',
            'replacement' => 'ដំរី',
        ])->assertSessionHasErrors('replacement');

        $this->assertSame(MisspellingStatus::Open, $misspelling->refresh()->status);
    }

    public function test_the_document_text_is_left_untouched_when_a_suggestion_is_accepted()
    {
        $misspelling = Misspelling::factory()->create();
        $original = $misspelling->document->text;

        $this->patch(route('misspellings.update', $misspelling), [
            'status' => 'accepted',
            'replacement' => 'លោក',
        ]);

        $this->assertSame($original, $misspelling->document->refresh()->text);
    }
}
