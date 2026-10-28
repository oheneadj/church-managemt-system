import { Button } from '@/components/ui/button';
import type { StartedChurch } from '@/types/onboarding';

interface ChurchStartedProps {
    started: StartedChurch;
}

/**
 * Shown once the church exists: its address, where to sign in, and a nudge to add a second admin so the
 * church is never locked out when the first one changes (docs/12, risk R5).
 */
export function ChurchStarted({ started }: ChurchStartedProps) {
    return (
        <div className="flex flex-col gap-4">
            <p>
                <strong>{started.church.name}</strong> is ready, {started.admin.name}. Your church lives at{' '}
                <span className="font-medium break-all">{started.church.url.replace(/^https?:\/\//, '')}</span>.
            </p>
            <p className="text-sm text-muted-foreground">
                Sign in there with the same phone number. We will text you a new code. We recommend adding a second admin once you are in,
                in case of staff changes.
            </p>
            <Button asChild>
                <a href={started.login_url}>Go to my church</a>
            </Button>
        </div>
    );
}
