import { useId } from 'react';
import { MoneyDisplay } from '@/components/ui/MoneyDisplay';
import { OFFERING_METHOD_ORDER, PAYMENT_METHOD_LABELS, ZERO_AMOUNT } from '@/constants/giving';
import type { OfferingSection } from '@/types/giving';

interface OfferingTableProps {
    /** What the table is about, read out by screen readers and shown as its heading. */
    title: string;
    section: OfferingSection;
}

/**
 * One block of the offering report: a line for each fund with what came in by each payment method, then the total
 * line, then how many gifts there were. A payment method nobody used is left out so the table stays narrow on a
 * phone. Every amount is the API's own text; nothing is added up here.
 */
export function OfferingTable({ title, section }: OfferingTableProps) {
    const headingId = useId();
    const methods = OFFERING_METHOD_ORDER.filter((method) => section.by_method[method] !== ZERO_AMOUNT);

    return (
        <section aria-labelledby={headingId} className="flex flex-col gap-2">
            <h2 id={headingId} className="font-medium">
                {title}
            </h2>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left text-muted-foreground">
                            <th scope="col" className="py-2 pr-3 font-normal">
                                Fund
                            </th>
                            {methods.map((method) => (
                                <th key={method} scope="col" className="px-3 py-2 text-right font-normal">
                                    {PAYMENT_METHOD_LABELS[method]}
                                </th>
                            ))}
                            <th scope="col" className="py-2 pl-3 text-right font-normal">
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {section.by_fund.map((fund) => (
                            <tr key={fund.fund_type} className="border-b">
                                <th scope="row" className="py-2 pr-3 text-left font-normal">
                                    {fund.fund_type}
                                </th>
                                {methods.map((method) => (
                                    <td key={method} className="px-3 py-2 text-right">
                                        <MoneyDisplay formatted={fund.by_method_formatted[method]} />
                                    </td>
                                ))}
                                <td className="py-2 pl-3 text-right font-medium">
                                    <MoneyDisplay formatted={fund.total_formatted} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="font-semibold">
                            <th scope="row" className="py-2 pr-3 text-left">
                                Total
                            </th>
                            {methods.map((method) => (
                                <td key={method} className="px-3 py-2 text-right">
                                    <MoneyDisplay formatted={section.by_method_formatted[method]} />
                                </td>
                            ))}
                            <td className="py-2 pl-3 text-right">
                                <MoneyDisplay formatted={section.grand_total_formatted} />
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p className="text-sm text-muted-foreground">
                {section.transaction_count} {section.transaction_count === 1 ? 'gift' : 'gifts'}, {section.anonymous_count} anonymous
            </p>
        </section>
    );
}
