import { Link } from 'react-router';
import { ChurchProfileForm } from '@/components/Settings/ChurchProfileForm';
import { ChurchSettingsForm } from '@/components/Settings/ChurchSettingsForm';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Skeleton } from '@/components/ui/skeleton';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { useCan } from '@/hooks/useCan';
import { useChurchProfile, useChurchSettings } from '@/hooks/useChurchSettings';

/**
 * The church's settings (story 5.14): its details, and how it wants the platform to behave. Only the church admin may open
 * it (the server answers 403 to anyone else, and the menu hides the link). Each part is its own form with its own Save, so
 * changing a phone number never saves a half-edited preference. The forms are drawn only once their data has arrived, so
 * each starts from what is saved.
 */
export function SettingsPage() {
    const profile = useChurchProfile();
    const settings = useChurchSettings();
    const canManageFunds = useCan(PERMISSIONS.FUND_TYPES_MANAGE);

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <PageHeader title="Settings" description="Your church's details and preferences" />

            {canManageFunds && (
                <nav aria-label="Lists you can change" className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <Link to={ROUTES.FUNDS} className="underline underline-offset-4">
                        Giving funds
                    </Link>
                </nav>
            )}

            {profile.isPending && <Skeleton aria-busy="true" className="h-[360px] w-full rounded-xl" />}
            {profile.isError && <ErrorNotice message={profile.error.message} onRetry={() => void profile.refetch()} />}
            {profile.data && <ChurchProfileForm profile={profile.data} />}

            {settings.isPending && <Skeleton aria-busy="true" className="h-[300px] w-full rounded-xl" />}
            {settings.isError && <ErrorNotice message={settings.error.message} onRetry={() => void settings.refetch()} />}
            {settings.data && <ChurchSettingsForm settings={settings.data} />}
        </div>
    );
}
