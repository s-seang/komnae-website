<komnot-guidelines>
=== foundation rules ===

# Komnot Guidelines

Komnot is a Khmer spelling checker. A user uploads text or a PDF, we extract the
Khmer text (OCR for scanned PDFs), segment it into words, check each word against
a dictionary, and surface misspellings with suggested corrections.

**This is a single-tenant application.** There is no tenancy, no per-tenant
database, no landlord/tenant split. Do not add one.

## Foundational Context

Installed ecosystem packages & versions. Abide by these versions.

- php - 8.4
- laravel/framework (LARAVEL) - v13
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/wayfinder (WAYFINDER) - v0
- laravel/chisel (CHISEL) - v0
- laravel/tinker - v3
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v5
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19 (React Compiler enabled via babel-plugin-react-compiler)
- tailwindcss (TAILWINDCSS) - v4
- shadcn/ui - `new-york` style, Radix primitives, `neutral` base, lucide icons
- vite (VITE) - v8 through `vite-plus` (`vp`), not plain `vite`

## Skills Activation

Project skills live in `.claude/skills/**` and `.agents/skills/**`. Activate the
relevant skill when you start work in that domain — don't wait until you're stuck.

## Conventions

- Follow existing code conventions. Before creating or editing a file, read a
  sibling file for structure, approach, and naming.
- Descriptive names: `hasDictionaryMatch`, not `check()`.
- Check for an existing component before writing a new one.

## Application Structure & Architecture

- Stick to the existing directory structure; don't create new base folders
  without approval.
- Do not change dependencies without approval.

## Frontend Bundling

- If a frontend change isn't showing up, the user may need `npm run dev`,
  `npm run build`, or `composer run dev` (which runs `php artisan dev`). Ask them.

## Documentation Files

- Only create documentation files when explicitly requested.

## Replies

- Be concise. Explain what matters, skip the obvious.

=== komnot domain rules ===

# Khmer text pipeline

The pipeline is: **upload → extract → segment → check → suggest**. Keep each
stage a separate, independently testable unit. A change to OCR must not require
touching the dictionary, and vice versa.

- **Khmer has no spaces between words.** Never `explode(' ', $text)` and never
  reach for `str_word_count`, `ucwords`, `strlen`, `substr`, or `strtolower` on
  Khmer. Use `mb_*` with UTF-8 everywhere, and go through the segmenter for word
  boundaries.
- Khmer is a complex script: subscripts (`◌្`), diacritics, and zero-width
  space (U+200B) are all real, meaningful characters. Normalize input to NFC and
  strip only what you can justify; a "cleanup" that drops a coeng silently
  corrupts a word.
- Store extracted text and every offset in **character** positions, not bytes,
  so the frontend can highlight the exact span it came from.
- OCR output is noisy by definition. Confidence is part of the result, not an
  afterthought — a low-confidence OCR word is a weaker signal than a
  high-confidence one and the UI must be able to tell them apart.
- Suggestions are ranked candidates, never a silent auto-fix. The user always
  accepts or rejects.
- Uploads are untrusted input at a trust boundary: validate mime type and size,
  store outside the webroot, and never pass a user-supplied filename to a shell
  command.
- Long extractions and OCR runs belong on the queue, not in the request cycle.

# Dictionary

- The dictionary is data, not code. It lives in the database (seeded) or in a
  versioned data file — not hardcoded in a class.
- Every dictionary change ships with a test that proves a known word passes and a
  known misspelling fails.

=== php rules ===

# PHP

- Always use curly braces for control structures, even single-line bodies.
- Use constructor property promotion: `public function __construct(private Dictionary $dictionary) {}`.
  No empty zero-parameter `__construct()` unless private.
- Explicit return types and parameter type hints everywhere:
  `function suggest(string $word, int $limit = 5): array`.
- TitleCase enum keys: `Pending`, `Ocr`, `Completed`.
- Prefer PHPDoc blocks over inline comments. Inline comments only for genuinely
  complex logic. Use array shape types in PHPDoc.

=== laravel rules ===

# Do Things the Laravel Way

- Use `php artisan make:` to create files (migrations, controllers, models,
  jobs). `php artisan list` to discover, `php artisan [command] --help` for
  options. Generic PHP class → `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands.
- New models get factories (and seeders where useful).
- Prefer named routes and `route()` for links.
- For APIs, use Eloquent API Resources — unless existing routes here don't, then
  follow the existing convention.
- Vite manifest error (`Unable to locate file in Vite manifest`): run
  `npm run build`, or ask the user to run `npm run dev` / `composer run dev`.

## Tinker

- `php artisan tinker --execute 'Your::code();'` — single quotes outside, double
  quotes for PHP strings inside. Don't create models without approval; prefer
  tests with factories.

=== boost rules ===

# Laravel Boost

## Tools

- Boost is an MCP server built for this app. Prefer Boost tools over shell
  commands and manual file reads.
- `database-query` for read-only queries instead of raw SQL in tinker.
- `database-schema` to inspect tables before writing migrations or models.
- `get-absolute-url` to resolve scheme/domain/port. Always use it before sharing
  a URL with the user.
- `browser-logs` for browser errors and exceptions. Only recent entries matter.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before code changes that call a Laravel-ecosystem API. It
  auto-scopes to the versions installed here.
- Pass a `packages` array when you know which packages are relevant.
- Multiple broad, topic-based queries: `['file upload', 'queue job', 'validation']`.
- Never put package names in the query — `test resource table`, not
  `laravel 13 test resource table`.

### Search Syntax

1. Words auto-stem with AND logic: `rate limit` matches "rate" AND "limit".
2. `"quoted phrases"` for exact adjacent matching.
3. Combine: `middleware "rate limit"`.
4. Multiple queries for OR: `queries=["authentication", "middleware"]`.

=== docs before code ===

The docs step fires only when the task actually calls a framework or package API.
Renames, copy changes, and pure business-logic refactors skip it — say so and move on.

- **PHP / Laravel** → Boost `search-docs`. Prefer it over Context7 or web search
  for anything in the Laravel ecosystem.
- **JS / React** → Context7 (`resolve-library-id` → `query-docs`, one call per
  concept): React 19, `@inertiajs/react` 3, Tailwind 4.
- **shadcn components** → the shadcn docs page for the component before writing
  markup: `https://ui.shadcn.com/docs/components/<component>`. This project uses
  the default **Radix** variants (`new-york` style), not Base UI.

Report what the lookup _contradicted_, not just that you ran it.

=== tests rules ===

# Test Enforcement

- Every change is programmatically tested. Write or update a test, then run it.
- Pest v5: `php artisan make:test --pest {name}` — `{name}` excludes the suite
  directory (`SpellCheckTest`, not `Feature/SpellCheckTest`).
- Run the minimum: `php artisan test --compact --filter=name`.
- Use factories in tests; check for existing custom states first.
- Faker: `fake()->word()`. Follow the existing convention in the file.
- Do NOT delete tests without approval.
- Do not write verification scripts or tinker snippets when a test covers it.
  Tests are the proof.
- Khmer test fixtures go in `tests/Fixtures/` as real UTF-8 Khmer, not
  transliterations or escaped codepoints.

=== inertia + react rules ===

# Inertia

- Client-rendered SPA over server-side routing. Pages live in
  `resources/js/pages`. Use `Inertia::render()`, not Blade views.
- Use `search-docs` for version-specific Inertia examples.

# Inertia v3

- v3 adds: standalone HTTP requests (`useHttp`), optimistic updates with
  automatic rollback, layout props (`useLayoutProps`), instant visits, SSR via
  the `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried from v2: deferred props, infinite scroll, merging props, polling,
  prefetching, once props, flash data.
- Deferred props need an empty state with a pulsing/animated skeleton.
- Axios is gone — use the built-in XHR client with interceptors.
- `Inertia::lazy()` / `LazyProp` removed → use `Inertia::optional()`.
- Prop types (`optional`, `defer`, `merge`) work in nested arrays via dot paths.
- SSR runs automatically in Vite dev mode; no separate Node server in dev.
- Event renames: `invalid` → `httpException`, `exception` → `networkError`.
- `router.cancel()` → `router.cancelAll()`.

=== wayfinder rules ===

# Laravel Wayfinder

Generate TypeScript functions for routes with `php artisan wayfinder:generate`.
Import from `@/actions/` (controllers) or `@/routes/` (named routes). Regenerate
after changing controllers or routes — nothing does it for you.

=== pint rules ===

# Laravel Pint

- After modifying PHP files, run `vendor/bin/pint --dirty --format agent`.
- Never `pint --test`; just run pint so it fixes.

=== frontend rules ===

# UI rules (every component, big or small)

- Build from the shadcn primitives in `resources/js/components/ui/` first.
  Hand-roll markup only when no primitive fits, and say why.
- Missing a primitive? Add it via the shadcn CLI/MCP so it lands in this
  project's `new-york` style — don't paste from the docs.
- Before a new component, check `resources/js/components/` for one to reuse
  (`app-shell`, `heading`, `input-error`, `nav-*`, `password-input`).
- Match the surrounding UI, not a fresh design: read a sibling page and reuse its
  layout, spacing, variants, empty/loading states, and toasts (`sonner`).
- Style with existing Tailwind tokens and `cn()`. No ad-hoc hex colors, no
  one-off spacing scales, no raw `emerald-600` + hand-written `dark:` override —
  use the semantic tokens.
- **Never disable a submit button to enforce validation.** Keep it enabled; on
  submit mark invalid fields with `aria-invalid`, focus the first one, and say
  what's missing. A disabled button that won't say why is the bug this rule
  prevents.
- Khmer text in the UI needs a font stack with real Khmer coverage and enough
  line-height for subscripts — do not let the default Latin stack render it.
- Misspelling highlights must be keyboard-reachable and readable at a glance;
  color alone is never the only signal.

=== git rules ===

# Git: never commit or push

Leave changes uncommitted in the working tree. The user reviews, commits, and
chooses the branch. Do not run `git commit`, `git push`, `git merge`,
`git rebase`, or create branches/tags — not even when a task looks finished.
`git status` / `diff` / `log`, and `git rm` for files being deleted as part of
the change, are fine.
</komnot-guidelines>
