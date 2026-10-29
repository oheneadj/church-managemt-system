import { Link } from 'react-router';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { ROUTES } from '@/constants/routes';
import { formatDate } from '@/utils/formatDate';
import type { Service } from '@/types/services';

interface ServiceListItemProps {
    service: Service;
}

/**
 * One row of the service list: name, date and type, whether check-in is open, and how many people are in.
 */
export function ServiceListItem({ service }: ServiceListItemProps) {
    return (
        <li>
            <Link
                to={ROUTES.SERVICE(service.id)}
                className="flex items-center justify-between gap-3 rounded-xl border p-3 hover:bg-muted/50"
            >
                <span>
                    <span className="block font-medium">{service.name}</span>
                    <span className="block text-sm text-muted-foreground">
                        {formatDate(service.service_date)}
                        {service.starts_at ? ` · ${service.starts_at}` : ''} · {service.type_label}
                        {service.schedule_id ? ' · Weekly' : ''}
                    </span>
                </span>
                <span className="flex flex-col items-end gap-1">
                    <StatusBadge label={service.is_open ? 'Open' : 'Closed'} tone={service.is_open ? 'success' : 'neutral'} />
                    <span className="text-sm text-muted-foreground">{service.attendance_count ?? 0} checked in</span>
                </span>
            </Link>
        </li>
    );
}
