import { EyeIcon, EyeOffIcon } from 'lucide-react';
import { useLayoutEffect, useRef, useState, type ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from 'cn';

type PasswordInputProps = Omit<ComponentProps<'input'>, 'type'>;

/** Where the caret was when the eye was pressed, so it can be put back after the field changes type. */
interface Caret {
    start: number | null;
    end: number | null;
}

/**
 * The only way to type a password (docs/design-system/interactions.md, section 8). The text is hidden until the person presses the
 * eye, which shows it and offers to hide it again. Showing or hiding never changes what was typed, keeps the field focused where
 * it was, and puts the caret back where it was, so the person can carry on typing. It never reveals the password by itself.
 * Pasting and password managers work as they do in any field.
 */
function PasswordInput({ className, id, ref, ...props }: PasswordInputProps) {
    const [visible, setVisible] = useState(false);
    const input = useRef<HTMLInputElement | null>(null);
    const caret = useRef<Caret | null>(null);

    // The field changes type on the same element and some browsers send the caret to the end when that happens, so the caret is put back once the new type is in place. The field never loses focus: the eye does not take it (see onMouseDown).
    useLayoutEffect(() => {
        const node = input.current;
        const saved = caret.current;

        if (node !== null && saved !== null) {
            node.setSelectionRange(saved.start, saved.end);
            caret.current = null;
        }
    }, [visible]);

    const setRefs = (node: HTMLInputElement | null) => {
        input.current = node;

        if (typeof ref === 'function') {
            ref(node);
        } else if (ref) {
            ref.current = node;
        }
    };

    const toggle = () => {
        const node = input.current;

        // Only a person who was typing in the field gets the focus and caret back; pressing the eye with the keyboard leaves focus on the eye.
        caret.current = node !== null && document.activeElement === node ? { start: node.selectionStart, end: node.selectionEnd } : null;
        setVisible((shown) => !shown);
    };

    return (
        <div className="relative">
            <Input
                ref={setRefs}
                id={id}
                type={visible ? 'text' : 'password'}
                autoCapitalize="off"
                autoCorrect="off"
                spellCheck={false}
                className={cn('pe-11', className)}
                {...props}
            />
            <Button
                type="button"
                variant="ghost"
                aria-label={visible ? 'Hide password' : 'Show password'}
                aria-pressed={visible}
                aria-controls={id}
                // Pressing with a mouse or a finger must not take the focus out of the field.
                onMouseDown={(event) => event.preventDefault()}
                onClick={toggle}
                disabled={props.disabled}
                className="absolute inset-y-0 end-0 h-full w-11 rounded-s-none text-muted-foreground"
            >
                {visible ? <EyeOffIcon aria-hidden="true" /> : <EyeIcon aria-hidden="true" />}
            </Button>
        </div>
    );
}

export { PasswordInput };
