export type MisspellingStatus = 'open' | 'accepted' | 'ignored';

export type DocumentStatus =
    | 'pending'
    | 'extracting'
    | 'checking'
    | 'completed'
    | 'needs_ocr'
    | 'legacy_encoding'
    | 'failed';

export type Misspelling = {
    id: number;
    word: string;
    /** Character offset into the document text — not bytes, and not UTF-16 units. */
    start: number;
    /** Length in characters. */
    length: number;
    suggestions: string[];
    status: MisspellingStatus;
    replacement: string | null;
};

export type CheckedDocument = {
    id: number;
    name: string | null;
    status: DocumentStatus;
    finished: boolean;
    text: string | null;
    error: string | null;
    misspellings: Misspelling[];
};
