/**
 * The answer to asking for a CSV export (docs/08-api.md, "Export Members" and "Export Contributions"). The file is built by a
 * worker; the person who asked is emailed a download link that works for a short time.
 */
export interface ExportQueued {
    export_id: string;
    message: string;
}
