import { MessageSquare } from 'lucide-react';
import { Link, useSearchParams } from 'react-router';
import { MessageFilterBar } from '@/components/Messages/MessageFilterBar';
import { MessageListItem } from '@/components/Messages/MessageListItem';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Pagination } from '@/components/ui/Pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { ROUTES } from '@/constants/routes';
import { useMessageFilters } from '@/hooks/useMessageFilters';
import { useMessages } from '@/hooks/useMessages';

/** How many placeholder rows to show while the first page loads. */
const LOADING_ROWS = 5;

/**
 * Every message the platform sent for the church (story 5.12), newest first, with filters by how it was sent, its kind
 * and the days it was sent between. Only staff who may send messages can open it (the server answers 403 to anyone else,
 * and the menu hides the link), because it lists phone numbers and what was said. The filters and the page are in the address.
 */
export function MessagesPage() {
    const { filters, setFilter, setPage } = useMessageFilters();
    const [, setParams] = useSearchParams();
    const messages = useMessages(filters);

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <PageHeader
                title="Messages"
                description={messages.data ? `${messages.data.meta.total} messages found` : 'Everything sent to your members'}
                actions={
                    <Button asChild size="lg">
                        <Link to={ROUTES.BROADCAST_NEW}>New broadcast</Link>
                    </Button>
                }
            />
            <MessageFilterBar filters={filters} onChange={setFilter} onClear={() => setParams({})} />

            {messages.isPending && (
                <div aria-busy="true" className="flex flex-col gap-2">
                    {Array.from({ length: LOADING_ROWS }, (_, row) => (
                        <Skeleton key={row} className="h-[80px] w-full rounded-xl" />
                    ))}
                </div>
            )}

            {messages.isError && <ErrorNotice message={messages.error.message} onRetry={() => void messages.refetch()} />}

            {messages.data && messages.data.data.length === 0 && (
                <EmptyState icon={MessageSquare} title="No messages found" description="Messages sent to your members will appear here." />
            )}

            {messages.data && messages.data.data.length > 0 && (
                <>
                    <ul className="flex flex-col gap-2" aria-live="polite">
                        {messages.data.data.map((message) => (
                            <MessageListItem key={message.id} message={message} />
                        ))}
                    </ul>
                    <Pagination
                        page={messages.data.meta.current_page}
                        lastPage={messages.data.meta.last_page}
                        onPageChange={setPage}
                        disabled={messages.isPlaceholderData}
                    />
                </>
            )}
        </div>
    );
}
