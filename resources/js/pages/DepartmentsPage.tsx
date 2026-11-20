import { UsersRound } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router';
import { toast } from 'sonner';
import { DepartmentDialog } from '@/components/Departments/DepartmentDialog';
import { DepartmentListItem } from '@/components/Departments/DepartmentListItem';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Skeleton } from '@/components/ui/skeleton';
import { ROUTES } from '@/constants/routes';
import { useAllDepartments, useDepartmentMutations } from '@/hooks/useDepartments';
import type { Department } from '@/types/members';

/** How many placeholder rows to show while the departments load. */
const LOADING_ROWS = 4;

/**
 * The church's departments, the groups members belong to: add one, change its name and what it does, and retire or bring it
 * back. Staff who may edit members can open it (`members.edit`; the server answers 403 to anyone else). Retiring asks first,
 * and only stops the department being offered as a choice: members already in it stay in it. The head of a department is
 * not set here, because choosing one needs the staff list, which only the church admin may read.
 */
export function DepartmentsPage() {
    const departments = useAllDepartments();
    const { setActive } = useDepartmentMutations();
    const [adding, setAdding] = useState(false);
    const [changing, setChanging] = useState<Department | undefined>(undefined);
    const [retiring, setRetiring] = useState<Department | undefined>(undefined);

    const switchTo = (department: Department, isActive: boolean, done: string) => {
        setActive.mutate(
            { id: department.id, isActive },
            {
                onSuccess: () => {
                    toast.success(done);
                    setRetiring(undefined);
                },
                onError: (error) => {
                    toast.error(error.message);
                    setRetiring(undefined);
                },
            },
        );
    };

    const toggle = (department: Department) => {
        if (department.is_active) {
            setRetiring(department);
        } else {
            switchTo(department, true, `${department.name} is back in use.`);
        }
    };

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <PageHeader
                title="Departments"
                description="The groups members belong to"
                actions={<Button onClick={() => setAdding(true)}>Add a department</Button>}
            />
            <Link to={ROUTES.MEMBERS} className="self-start text-sm underline underline-offset-4">
                Back to members
            </Link>

            {departments.isPending && (
                <div aria-busy="true" className="flex flex-col gap-2">
                    {Array.from({ length: LOADING_ROWS }, (_, row) => (
                        <Skeleton key={row} className="h-[68px] w-full rounded-xl" />
                    ))}
                </div>
            )}

            {departments.isError && <ErrorNotice message={departments.error.message} onRetry={() => void departments.refetch()} />}

            {departments.data && departments.data.length === 0 && (
                <EmptyState
                    icon={UsersRound}
                    title="No departments yet"
                    description="Add the first department, such as the choir, so members can be placed in it."
                />
            )}

            {departments.data && departments.data.length > 0 && (
                <ul className="flex flex-col gap-2" aria-live="polite">
                    {departments.data.map((department) => (
                        <DepartmentListItem key={department.id} department={department} onChange={setChanging} onToggleActive={toggle} />
                    ))}
                </ul>
            )}

            {/* Mounted only while open, so each opening starts blank or from the department chosen. */}
            {adding && <DepartmentDialog onClose={() => setAdding(false)} />}
            {changing !== undefined && <DepartmentDialog department={changing} onClose={() => setChanging(undefined)} />}
            <ConfirmDialog
                open={retiring !== undefined}
                onOpenChange={(open) => !open && setRetiring(undefined)}
                title={`Retire ${retiring?.name ?? ''}?`}
                description="It will no longer be offered as a choice when members are added or filtered. Members already in it stay in it. You can bring it back."
                confirmLabel="Retire"
                destructive
                pending={setActive.isPending}
                onConfirm={() => retiring !== undefined && switchTo(retiring, false, `${retiring.name} is retired.`)}
            />
        </div>
    );
}
