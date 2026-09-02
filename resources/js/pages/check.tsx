import { Head, useForm, usePoll } from '@inertiajs/react';
import {
    CircleAlert,
    FileText,
    ScanText,
    SpellCheck,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/CheckController';
import CheckResults from '@/components/check-results';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';
import { check } from '@/routes';
import type { CheckedDocument } from '@/types/check';

const ACCEPTED = '.pdf,.txt';

/** How the file could not be read, in the words we show the user. */
const UNREADABLE: Partial<
    Record<CheckedDocument['status'], { title: string; icon: typeof ScanText }>
> = {
    needs_ocr: { title: 'This looks like a scan', icon: ScanText },
    legacy_encoding: { title: 'Legacy Khmer encoding', icon: CircleAlert },
    failed: { title: 'Nothing to check', icon: CircleAlert },
};

export default function Check({
    document,
    maxUploadKilobytes,
}: {
    document?: CheckedDocument;
    maxUploadKilobytes: number;
}) {
    const [file, setFile] = useState<File | null>(null);
    const [dragging, setDragging] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);
    const textInput = useRef<HTMLTextAreaElement>(null);

    const form = useForm({ file: null as File | null, text: '' });

    const limit = `${Math.round(maxUploadKilobytes / 1024)} MB`;

    /**
     * PHP rejects an oversized body before the app can turn it into a form
     * error, so the file is measured here and never sent in the first place.
     */
    const chooseFile = (chosen: File | null) => {
        if (chosen && chosen.size > maxUploadKilobytes * 1024) {
            setFile(null);
            form.setError(
                'file',
                `That file is over ${limit}. Try a smaller one.`,
            );

            return;
        }

        form.clearErrors('file');
        setFile(chosen);
    };

    // A queued upload has no result yet; stop asking as soon as it lands.
    const { start, stop } = usePoll(
        2000,
        { only: ['document'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (document && !document.finished) {
            start();
        } else {
            stop();
        }
    }, [document?.id, document?.finished, start, stop]);

    const submit = () => {
        if (!file && form.data.text.trim() === '') {
            form.setError('text', 'Add a PDF or paste some Khmer text first.');
            textInput.current?.focus();

            return;
        }

        form.transform((data) => ({ ...data, file }));
        form.submit(store(), { forceFormData: true });
    };

    const unreadable = document ? UNREADABLE[document.status] : undefined;
    const working = document !== undefined && !document.finished;

    return (
        <>
            <Head title="Check" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Upload a document</CardTitle>
                            <CardDescription>
                                A PDF or plain text file, up to {limit}. Scanned
                                PDFs need OCR, which is not built yet.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Label
                                htmlFor="document"
                                onDragOver={(event) => {
                                    event.preventDefault();
                                    setDragging(true);
                                }}
                                onDragLeave={() => setDragging(false)}
                                onDrop={(event) => {
                                    event.preventDefault();
                                    setDragging(false);
                                    chooseFile(
                                        event.dataTransfer.files[0] ?? null,
                                    );
                                }}
                                className={cn(
                                    'flex min-h-40 cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-6 text-center transition-colors',
                                    dragging
                                        ? 'border-ring bg-accent'
                                        : 'hover:bg-accent/50',
                                )}
                            >
                                <Upload className="text-muted-foreground size-6" />
                                <span className="text-sm font-medium">
                                    Drop a file here, or browse
                                </span>
                                <span className="text-muted-foreground text-xs font-normal">
                                    PDF or TXT, up to {limit}
                                </span>
                            </Label>
                            <input
                                ref={fileInput}
                                id="document"
                                type="file"
                                accept={ACCEPTED}
                                className="sr-only"
                                aria-invalid={!!form.errors.file}
                                onChange={(event) =>
                                    chooseFile(event.target.files?.[0] ?? null)
                                }
                            />

                            {file && (
                                <div className="mt-4 flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                                    <FileText className="text-muted-foreground size-4 shrink-0" />
                                    <span className="truncate">
                                        {file.name}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="ml-auto size-7"
                                        onClick={() => {
                                            chooseFile(null);

                                            if (fileInput.current) {
                                                fileInput.current.value = '';
                                            }
                                        }}
                                    >
                                        <X />
                                        <span className="sr-only">
                                            Remove {file.name}
                                        </span>
                                    </Button>
                                </div>
                            )}

                            <InputError message={form.errors.file} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Or paste text</CardTitle>
                            <CardDescription>
                                Khmer text you already have on hand. Checked
                                straight away.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Label htmlFor="text" className="sr-only">
                                Khmer text
                            </Label>
                            <Textarea
                                ref={textInput}
                                id="text"
                                value={form.data.text}
                                aria-invalid={!!form.errors.text}
                                onChange={(event) => {
                                    form.clearErrors('text');
                                    form.setData('text', event.target.value);
                                }}
                                placeholder="សូមបញ្ចូលអត្ថបទជាភាសាខ្មែរនៅទីនេះ"
                                className="font-khmer min-h-40 leading-loose"
                            />
                            <InputError message={form.errors.text} />
                        </CardContent>
                    </Card>
                </div>

                <div className="flex items-center gap-3">
                    <Button onClick={submit} disabled={form.processing}>
                        {form.processing ? <Spinner /> : <SpellCheck />}
                        Check spelling
                    </Button>
                    <p className="text-muted-foreground text-sm">
                        Suggestions are proposals — you accept or reject each
                        one.
                    </p>
                </div>

                <Card className="flex-1">
                    <CardContent className="h-full">
                        {!document && (
                            <div className="text-muted-foreground flex h-full min-h-40 flex-col items-center justify-center gap-2 text-center text-sm">
                                <SpellCheck className="size-6" />
                                <p>
                                    Results will show up here once you run a
                                    check.
                                </p>
                            </div>
                        )}

                        {working && (
                            <div className="space-y-3">
                                <p className="text-muted-foreground flex items-center gap-2 text-sm">
                                    <Spinner className="size-4" />
                                    {document.status === 'extracting'
                                        ? 'Reading the document…'
                                        : 'Checking the spelling…'}
                                </p>
                                <Skeleton className="h-5 w-full" />
                                <Skeleton className="h-5 w-11/12" />
                                <Skeleton className="h-5 w-4/5" />
                            </div>
                        )}

                        {document && unreadable && (
                            <Alert variant="destructive">
                                <unreadable.icon />
                                <AlertTitle>{unreadable.title}</AlertTitle>
                                <AlertDescription>
                                    {document.error}
                                </AlertDescription>
                            </Alert>
                        )}

                        {document?.status === 'completed' && (
                            <div className="space-y-4">
                                <p className="text-muted-foreground text-sm">
                                    {document.misspellings.length === 0
                                        ? 'No misspellings found.'
                                        : `${document.misspellings.length} possible ${
                                              document.misspellings.length === 1
                                                  ? 'misspelling'
                                                  : 'misspellings'
                                          } found. Select one to see suggestions.`}
                                </p>
                                <CheckResults document={document} />
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Check.layout = {
    breadcrumbs: [
        {
            title: 'Check',
            href: check(),
        },
    ],
};
