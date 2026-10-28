import { StartChurchForm } from '@/components/Onboarding/StartChurchForm';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

/**
 * The public page where a new church signs up. It works on the platform's own address, before any church exists.
 */
export function StartChurchPage() {
    return (
        <main className="flex min-h-svh items-center justify-center p-4">
            <Card className="w-full max-w-lg">
                <CardHeader>
                    <CardTitle>
                        <h1>Start your church</h1>
                    </CardTitle>
                    <CardDescription>It takes a few minutes. There is nothing to pay to begin.</CardDescription>
                </CardHeader>
                <CardContent>
                    <StartChurchForm />
                </CardContent>
            </Card>
        </main>
    );
}
