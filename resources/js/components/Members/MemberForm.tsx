import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect, useState } from 'react';
import { FormProvider, useForm, type FieldErrors } from 'react-hook-form';
import { useBlocker, useNavigate } from 'react-router';
import { toast } from 'sonner';
import { ChurchFields } from '@/components/Members/ChurchFields';
import { ContactFields } from '@/components/Members/ContactFields';
import { IdentityFields } from '@/components/Members/IdentityFields';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { StepIndicator } from '@/components/ui/StepIndicator';
import {
    blankValues,
    FORM_STEPS,
    memberFormSchema,
    stepOfField,
    toMemberInput,
    valuesFromMember,
    type MemberFormValues,
} from '@/components/Members/memberFormSchema';
import { ROUTES } from '@/constants/routes';
import { useMemberMutations } from '@/hooks/useMemberMutations';
import { useUrlStep } from '@/hooks/useUrlStep';
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { Member } from '@/types/members';

interface MemberFormProps {
    /** The member to change. Leave out to add a new one. */
    member?: Member;
}

const ALL_FIELDS = FORM_STEPS.flatMap((step) => step.fields);

/**
 * Add or edit a member in three short steps, which is easier to finish on a phone than one long page.
 * Each step is checked before moving on, the step is kept in the URL, and a message from the server lands
 * next to its box and takes the user back to the step that needs fixing.
 */
export function MemberForm({ member }: MemberFormProps) {
    const editing = member !== undefined;
    const navigate = useNavigate();
    const { create, update } = useMemberMutations();
    const [stepIndex, setStepIndex] = useUrlStep(FORM_STEPS.length);
    const form = useForm<MemberFormValues>({
        resolver: zodResolver(memberFormSchema),
        defaultValues: member ? valuesFromMember(member) : blankValues(),
    });

    const isLastStep = stepIndex === FORM_STEPS.length - 1;
    const saving = create.isPending || update.isPending;
    const { isDirty } = form.formState;
    // Set once the member is saved. Going to their page waits for the next draw, so the leave check below already knows the changes are saved.
    const [savedId, setSavedId] = useState<string | null>(null);
    const hasUnsavedChanges = isDirty && savedId === null;

    // Leaving to another page with changes not saved asks first. Moving between the steps (same page) never does.
    const blocker = useBlocker(
        ({ currentLocation, nextLocation }) => hasUnsavedChanges && currentLocation.pathname !== nextLocation.pathname,
    );

    // Closing or reloading the tab with changes not saved asks too: the browser shows its own wording.
    useEffect(() => {
        const warn = (event: BeforeUnloadEvent) => {
            if (hasUnsavedChanges) {
                event.preventDefault();
            }
        };
        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [hasUnsavedChanges]);

    useEffect(() => {
        if (savedId !== null) {
            void navigate(ROUTES.MEMBER(savedId));
        }
    }, [savedId, navigate]);

    async function goToNextStep() {
        const stepIsValid = await form.trigger([...(FORM_STEPS[stepIndex]?.fields ?? [])], { shouldFocus: true });

        if (stepIsValid) {
            setStepIndex(stepIndex + 1);
        }
    }

    /** After a failed save, shows the step that holds the first box with an error. */
    function showStepWithError(fields: readonly string[]) {
        const first = fields[0];

        if (first !== undefined) {
            setStepIndex(stepOfField(first));
        }
    }

    const save = form.handleSubmit(
        (values) => {
            const input = toMemberInput(values, editing);
            const callbacks = {
                onSuccess: (savedMember: Member) => {
                    toast.success(editing ? 'Member updated.' : 'Member added.');
                    setSavedId(savedMember.id);
                },
                onError: (error: Error) => {
                    const shown = showFieldErrors(error, form.setError, ALL_FIELDS);

                    if (shown.length === 0) {
                        toast.error(error.message);
                    }

                    showStepWithError(shown);
                },
            };

            if (editing) {
                update.mutate({ id: member.id, input }, callbacks);
            } else {
                create.mutate(input, callbacks);
            }
        },
        (errors: FieldErrors<MemberFormValues>) => showStepWithError(Object.keys(errors)),
    );

    // Enter on an early step moves on; only the last step saves.
    function submit(event: React.SyntheticEvent<HTMLFormElement>) {
        if (isLastStep) {
            void save(event);
        } else {
            event.preventDefault();
            void goToNextStep();
        }
    }

    return (
        <FormProvider {...form}>
            <form onSubmit={submit} noValidate className="flex flex-col gap-6 rounded-xl border bg-card p-6 shadow-xs">
                <StepIndicator titles={FORM_STEPS.map((step) => step.title)} current={stepIndex} />
                {stepIndex === 0 && <IdentityFields />}
                {stepIndex === 1 && <ContactFields />}
                {stepIndex === 2 && <ChurchFields editing={editing} />}
                <div className="flex justify-between gap-2 border-t pt-6">
                    <Button type="button" variant="outline" disabled={stepIndex === 0} onClick={() => setStepIndex(stepIndex - 1)}>
                        Back
                    </Button>
                    {isLastStep ? (
                        <Button type="submit" loading={saving} loadingLabel="Saving…">
                            {editing ? 'Save changes' : 'Add member'}
                        </Button>
                    ) : (
                        <Button type="submit">Next</Button>
                    )}
                </div>
            </form>
            <ConfirmDialog
                open={blocker.state === 'blocked'}
                onOpenChange={(open) => !open && blocker.reset?.()}
                title="Leave without saving?"
                description="You have changes that are not saved. If you leave now, they are lost."
                confirmLabel="Leave without saving"
                destructive
                onConfirm={() => blocker.proceed?.()}
            />
        </FormProvider>
    );
}
