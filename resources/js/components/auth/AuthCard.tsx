import type { ReactNode } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface AuthCardProps {
    title: string;
    description: string;
    children: ReactNode;
}

/**
 * The centred card around the sign-in and password screens: one page heading, a line saying what to do, then the form.
 */
export function AuthCard({ title, description, children }: AuthCardProps) {
    return (
        <main className="flex min-h-svh items-center justify-center p-4">
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle>
                        <h1>{title}</h1>
                    </CardTitle>
                    <CardDescription>{description}</CardDescription>
                </CardHeader>
                <CardContent>{children}</CardContent>
            </Card>
        </main>
    );
}
