<?php

namespace App\Http\Controllers;

use App\Enums\MisspellingStatus;
use App\Models\Misspelling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MisspellingController extends Controller
{
    /**
     * Accept one of the ranked suggestions, or ignore the flag.
     *
     * The replacement is recorded against the misspelling; documents.text keeps
     * the original wording so every stored character offset stays valid.
     */
    public function update(Request $request, Misspelling $misspelling): RedirectResponse
    {
        $accepting = $request->input('status') === MisspellingStatus::Accepted->value;

        $validated = $request->validate([
            'status' => ['required', Rule::enum(MisspellingStatus::class)],
            'replacement' => [
                Rule::requiredIf($accepting),
                'nullable',
                Rule::in($misspelling->suggestions),
            ],
        ]);

        $misspelling->update([
            'status' => $validated['status'],
            'replacement' => $accepting ? $validated['replacement'] : null,
        ]);

        return back();
    }
}
