import { CheckCircle2Icon, ImageIcon, RotateCcwIcon, UploadCloudIcon, XIcon } from 'lucide-react';
import { useId, useRef, useState, type ChangeEvent, type DragEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Picture } from '@/components/ui/Picture';
import { imageRulesText, validateImageFile, type ImageRules } from '@/lib/validateImageFile';
import { cn } from 'cn';

/** One chosen image and where it stands. The parent owns this list and does the sending. */
export interface UploadItem {
    id: string;
    name: string;
    /** The size as people read it: "340 KB". */
    sizeText: string;
    /** A local preview address (an object URL) so the image shows at once. */
    previewUrl?: string;
    status: 'ready' | 'uploading' | 'success' | 'error';
    /** How far the upload really is, 0 to 100, from the request itself. Leave out when it is not known: no number is then shown, and none is made up. */
    progress?: number;
    /** Why it failed, in a plain sentence. */
    error?: string;
}

interface ImageUploadProps extends ImageRules {
    label: string;
    items: readonly UploadItem[];
    /** Called with the files that passed the checks. A batch keeps its good files even when another is refused. */
    onFiles: (files: File[]) => void;
    onRemove: (id: string) => void;
    onRetry?: (id: string) => void;
    onCancel?: (id: string) => void;
    multiple?: boolean;
    disabled?: boolean;
}

/** The status line under a file's name, in words (and a real progress bar while it is sending). */
function ItemStatus({ item }: { item: UploadItem }) {
    if (item.status === 'uploading') {
        return (
            <div className="flex items-center gap-2">
                <progress
                    aria-label={`Uploading ${item.name}`}
                    value={item.progress}
                    max={100}
                    className="h-1.5 w-32 overflow-hidden rounded-full accent-primary"
                />
                <span className="text-body-sm text-muted-foreground">
                    {item.progress === undefined ? 'Uploading…' : `${Math.round(item.progress)}%`}
                </span>
            </div>
        );
    }

    if (item.status === 'success') {
        return (
            <p className="flex items-center gap-1 text-body-sm text-feedback-success">
                <CheckCircle2Icon aria-hidden="true" className="size-4" /> Uploaded
            </p>
        );
    }

    if (item.status === 'error') {
        return <p className="text-body-sm text-feedback-error">{item.error ?? 'It could not be uploaded.'}</p>;
    }

    return <p className="text-body-sm text-muted-foreground">Ready to upload</p>;
}

/**
 * An image upload (docs/design-system/interactions.md, section 17): choose with a button or drag onto the area, the rules shown up
 * front, each file checked before anything is sent, files that pass kept when another in the same batch is refused, a thumbnail,
 * real progress, and Retry, Cancel and Remove. It does not send anything itself: the parent gets the good files and reports back
 * each file's state, so the progress shown is the request's own.
 */
export function ImageUpload({
    label,
    items,
    onFiles,
    onRemove,
    onRetry,
    onCancel,
    multiple = false,
    disabled = false,
    ...rules
}: ImageUploadProps) {
    const inputId = useId();
    const input = useRef<HTMLInputElement | null>(null);
    const [dragging, setDragging] = useState(false);
    const [refused, setRefused] = useState<string[]>([]);

    const take = (chosen: FileList | null) => {
        const files = Array.from(chosen ?? []);
        const checked = files.map((file) => [file, validateImageFile(file, rules)] as const);

        setRefused(checked.flatMap(([, problem]) => (problem === null ? [] : [problem])));

        const good = checked.flatMap(([file, problem]) => (problem === null ? [file] : []));

        if (good.length > 0) {
            onFiles(multiple ? good : good.slice(0, 1));
        }
    };

    const drop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragging(false);

        if (!disabled) {
            take(event.dataTransfer.files);
        }
    };

    const change = (event: ChangeEvent<HTMLInputElement>) => {
        take(event.target.files);
        event.target.value = '';
    };

    return (
        <div className="flex flex-col gap-3">
            <p id={`${inputId}-label`} className="text-body-sm font-medium">
                {label}
            </p>
            <div
                data-slot="image-upload-zone"
                data-dragging={dragging || undefined}
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(!disabled);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={drop}
                className={cn(
                    'flex flex-col items-center gap-2 rounded-xl border border-dashed p-6 text-center transition-colors',
                    dragging ? 'border-primary bg-accent' : 'bg-card',
                    disabled && 'opacity-50',
                )}
            >
                <UploadCloudIcon aria-hidden="true" className="size-8 text-muted-foreground" />
                <input
                    ref={input}
                    id={inputId}
                    type="file"
                    accept={rules.types.join(',')}
                    multiple={multiple}
                    disabled={disabled}
                    onChange={change}
                    aria-labelledby={`${inputId}-label`}
                    aria-describedby={`${inputId}-rules`}
                    className="sr-only"
                />
                <Button type="button" variant="outline" disabled={disabled} onClick={() => input.current?.click()}>
                    <ImageIcon aria-hidden="true" /> {multiple ? 'Choose images' : 'Choose an image'}
                </Button>
                <p className="text-body-sm text-muted-foreground">or drag {multiple ? 'them' : 'it'} here</p>
                <p id={`${inputId}-rules`} className="text-body-sm text-muted-foreground">
                    {imageRulesText(rules)}
                </p>
            </div>
            {refused.length > 0 && (
                <ul role="alert" className="flex flex-col gap-1 text-body-sm text-feedback-error">
                    {refused.map((problem) => (
                        <li key={problem}>{problem}</li>
                    ))}
                </ul>
            )}
            {items.length > 0 && (
                <ul aria-label="Chosen images" className="flex flex-col gap-2">
                    {items.map((item) => (
                        <li key={item.id} className="flex items-center gap-3 rounded-xl border bg-card p-3">
                            <div className="w-14 shrink-0">
                                <Picture src={item.previewUrl} alt="" ratio="1/1" fallbackText="No preview" />
                            </div>
                            <div className="flex min-w-0 flex-1 flex-col gap-1">
                                <p className="truncate text-body-sm font-medium">
                                    {item.name} <span className="font-normal text-muted-foreground">· {item.sizeText}</span>
                                </p>
                                <ItemStatus item={item} />
                            </div>
                            {item.status === 'error' && onRetry && (
                                <Button size="sm" variant="outline" onClick={() => onRetry(item.id)}>
                                    <RotateCcwIcon aria-hidden="true" /> Retry
                                </Button>
                            )}
                            {item.status === 'uploading' && onCancel ? (
                                <Button size="sm" variant="ghost" onClick={() => onCancel(item.id)}>
                                    Cancel
                                </Button>
                            ) : (
                                <Button size="icon-sm" variant="ghost" aria-label={`Remove ${item.name}`} onClick={() => onRemove(item.id)}>
                                    <XIcon />
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
