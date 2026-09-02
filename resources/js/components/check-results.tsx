import { router } from '@inertiajs/react';
import { Check, CircleAlert, Undo2 } from 'lucide-react';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { update } from '@/actions/App/Http/Controllers/MisspellingController';
import type { CheckedDocument, Misspelling } from '@/types/check';

/**
 * The document text split into plain runs and flagged spans.
 *
 * Offsets arrive as character positions, so the text is walked as an array of
 * code points rather than sliced directly — `String.slice` counts UTF-16 units
 * and would drift on any character outside the basic plane.
 */
function splitOnMisspellings(
    text: string,
    misspellings: Misspelling[],
): Array<string | Misspelling> {
    const characters = Array.from(text);
    const parts: Array<string | Misspelling> = [];
    let cursor = 0;

    for (const misspelling of misspellings) {
        if (misspelling.start > cursor) {
            parts.push(characters.slice(cursor, misspelling.start).join(''));
        }

        parts.push(misspelling);
        cursor = misspelling.start + misspelling.length;
    }

    parts.push(characters.slice(cursor).join(''));

    return parts;
}

function decide(
    misspelling: Misspelling,
    status: string,
    replacement?: string,
) {
    router.patch(
        update(misspelling).url,
        { status, replacement },
        { preserveScroll: true, preserveState: true },
    );
}

/**
 * One flagged span. Always a button, so every flag is reachable by Tab, and
 * marked by an underline and an icon as well as colour.
 */
function Flag({ misspelling }: { misspelling: Misspelling }) {
    const accepted = misspelling.status === 'accepted';
    const ignored = misspelling.status === 'ignored';
    const shown = accepted ? misspelling.replacement : misspelling.word;

    const label = accepted
        ? `${misspelling.word}, corrected to ${misspelling.replacement}`
        : ignored
          ? `${misspelling.word}, kept as written`
          : `${misspelling.word}, possible misspelling`;

    return (
        <Popover>
            <PopoverTrigger
                aria-label={label}
                className={cn(
                    'focus-visible:ring-ring/50 rounded-xs underline decoration-2 underline-offset-4 focus-visible:ring-[3px] focus-visible:outline-none',
                    !accepted &&
                        !ignored &&
                        'text-destructive decoration-destructive decoration-wavy',
                    accepted && 'text-primary decoration-primary',
                    ignored && 'text-muted-foreground decoration-dotted',
                )}
            >
                {shown}
                {accepted ? (
                    <Check className="ml-0.5 inline size-3.5 align-baseline" />
                ) : ignored ? (
                    <Undo2 className="ml-0.5 inline size-3.5 align-baseline" />
                ) : (
                    <CircleAlert className="ml-0.5 inline size-3.5 align-baseline" />
                )}
            </PopoverTrigger>

            <PopoverContent align="start" className="w-64 p-2">
                <p className="text-muted-foreground px-2 py-1.5 text-xs">
                    {accepted || ignored ? (
                        <>
                            Was{' '}
                            <span className="font-khmer">
                                {misspelling.word}
                            </span>
                        </>
                    ) : (
                        'Suggestions'
                    )}
                </p>

                {misspelling.suggestions.length === 0 && (
                    <p className="px-2 py-1.5 text-sm">
                        No suggestions for this one.
                    </p>
                )}

                {misspelling.suggestions.map((suggestion) => (
                    <Button
                        key={suggestion}
                        variant="ghost"
                        className="font-khmer w-full justify-start text-base"
                        onClick={() =>
                            decide(misspelling, 'accepted', suggestion)
                        }
                    >
                        {suggestion === misspelling.replacement && (
                            <Check className="text-primary" />
                        )}
                        {suggestion}
                    </Button>
                ))}

                <Button
                    variant="ghost"
                    className="text-muted-foreground w-full justify-start"
                    onClick={() => decide(misspelling, 'ignored')}
                >
                    Keep as written
                </Button>
            </PopoverContent>
        </Popover>
    );
}

export default function CheckResults({
    document,
}: {
    document: CheckedDocument;
}) {
    const parts = useMemo(
        () => splitOnMisspellings(document.text ?? '', document.misspellings),
        [document.text, document.misspellings],
    );

    return (
        <p className="font-khmer text-lg leading-loose whitespace-pre-wrap">
            {parts.map((part, index) =>
                typeof part === 'string' ? (
                    <span key={index}>{part}</span>
                ) : (
                    <Flag key={part.id} misspelling={part} />
                ),
            )}
        </p>
    );
}
