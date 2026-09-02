import { Head, Link, usePage } from '@inertiajs/react';
import { FileSearch, ScanText, SpellCheck, Upload } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { check, login, register } from '@/routes';

const steps = [
    {
        icon: Upload,
        title: 'Upload',
        description: 'Drop in a PDF or paste Khmer text straight in.',
    },
    {
        icon: ScanText,
        title: 'Extract',
        description: 'Scanned pages go through OCR, with confidence attached.',
    },
    {
        icon: FileSearch,
        title: 'Check',
        description:
            'Text is segmented into words and matched against the dictionary.',
    },
    {
        icon: SpellCheck,
        title: 'Fix',
        description:
            'Ranked suggestions you accept or reject — never silently.',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Khmer spelling checker" />
            <div className="bg-background text-foreground flex min-h-screen flex-col">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 p-6">
                    <span className="flex items-center gap-2 font-semibold">
                        <AppLogoIcon className="size-5 fill-current" />
                        Komnot
                    </span>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild variant="ghost" size="sm">
                                <Link href={check()}>Check</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button asChild variant="outline" size="sm">
                                    <Link href={register()}>Register</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center gap-16 px-6 py-12">
                    <section className="max-w-2xl space-y-6">
                        <h1 className="text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                            Catch Khmer misspellings before anyone else does.
                        </h1>
                        <p className="text-muted-foreground text-lg text-pretty">
                            Komnot reads your document — typed or scanned —
                            splits the Khmer into words, and shows you every
                            spelling it can&rsquo;t find in the dictionary, with
                            corrections to choose from.
                        </p>
                        <p className="text-muted-foreground text-xl leading-loose">
                            កម្មវិធីពិនិត្យអក្ខរាវិរុទ្ធភាសាខ្មែរ
                        </p>
                        <div className="flex flex-wrap items-center gap-3">
                            <Button asChild size="lg">
                                <Link href={check()}>
                                    <SpellCheck />
                                    Check a document
                                </Link>
                            </Button>
                            <span className="text-muted-foreground text-sm">
                                No account needed.
                            </span>
                        </div>
                    </section>

                    <section className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {steps.map((step) => (
                            <div key={step.title} className="space-y-2">
                                <step.icon className="text-muted-foreground size-5" />
                                <h2 className="font-medium">{step.title}</h2>
                                <p className="text-muted-foreground text-sm">
                                    {step.description}
                                </p>
                            </div>
                        ))}
                    </section>
                </main>

                <footer className="text-muted-foreground mx-auto w-full max-w-5xl px-6 py-8 text-sm">
                    Komnot — Khmer spelling checker.
                </footer>
            </div>
        </>
    );
}
