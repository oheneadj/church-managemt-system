import { Button } from '@/components/ui/button';
import { MoneyDisplay } from '@/components/ui/MoneyDisplay';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { formatCount } from '@/utils/formatCount';
import type { SmsPackage } from '@/types/platform';

interface SmsPackageListItemProps {
    smsPackage: SmsPackage;
    onChange: (smsPackage: SmsPackage) => void;
    /** Asks to switch the package off (the page confirms first) or, for one that is off, switches it on. */
    onToggleActive: (smsPackage: SmsPackage) => void;
}

/** One package: its name, credits and price, whether churches can buy it, and the buttons to change or switch it. */
export function SmsPackageListItem({ smsPackage, onChange, onToggleActive }: SmsPackageListItemProps) {
    return (
        <li className="flex flex-wrap items-start justify-between gap-3 rounded-xl border p-3">
            <span className="min-w-0 flex-1">
                <span className="block font-medium">{smsPackage.name}</span>
                <span className="block text-sm text-muted-foreground">
                    {formatCount(smsPackage.credits)} credits · <MoneyDisplay formatted={smsPackage.price_formatted} />
                </span>
            </span>
            <span className="flex flex-wrap items-center gap-2">
                <StatusBadge
                    label={smsPackage.is_active ? 'On sale' : 'Switched off'}
                    tone={smsPackage.is_active ? 'success' : 'neutral'}
                />
                <Button type="button" variant="outline" size="sm" onClick={() => onChange(smsPackage)}>
                    Change
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={() => onToggleActive(smsPackage)}>
                    {smsPackage.is_active ? 'Switch off' : 'Switch on'}
                </Button>
            </span>
        </li>
    );
}
