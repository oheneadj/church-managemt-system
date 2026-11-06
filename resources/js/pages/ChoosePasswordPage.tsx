import { useState } from 'react';
import { Link } from 'react-router';
import { AuthCard } from '@/components/auth/AuthCard';
import { ChoosePasswordForm } from '@/components/auth/ChoosePasswordForm';
import { Button } from '@/components/ui/button';
import { ROUTES } from '@/constants/routes';

/**
 * Where an invited staff member turns the code from their invitation email into their first password (D-055). The server
 * treats the code like a password reset code, so this page and "Forgot your password?" share one form. Choosing a password
 * does not sign anyone in; the person goes on to the sign-in page.
 */
export function ChoosePasswordPage() {
    const [saved, setSaved] = useState(false);

    if (saved) {
        return (
            <AuthCard title="Password saved" description="You can now sign in with your email and your new password.">
                <Button asChild className="w-full">
                    <Link to={ROUTES.LOGIN}>Go to sign in</Link>
                </Button>
            </AuthCard>
        );
    }

    return (
        <AuthCard
            title="Choose your password"
            description="Enter the email your invitation was sent to, the code from that email, and the password you want to use."
        >
            <ChoosePasswordForm
                submitLabel="Save password"
                onDone={() => setSaved(true)}
                extra={
                    <Button asChild variant="link">
                        <Link to={ROUTES.LOGIN}>Back to sign in</Link>
                    </Button>
                }
            />
        </AuthCard>
    );
}
