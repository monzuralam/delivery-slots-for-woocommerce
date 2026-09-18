import { CalendarCheck2, ClipboardList, CreditCard, ListChecks, ShieldCheck } from 'lucide-react';

import SectionHead from './SectionHead';
import ResourceCard from './ResourceCard';

const DASHBOARD_URL = 'admin.php?page=delivery-slots-for-woocommerce';

const STEPS = [
    {
        icon: ListChecks,
        title: 'Create a delivery slot',
        text: 'Open the Dashboard and click "Add Slot". Set the date, start/end time, capacity, price, and status.',
        cta: 'Open Dashboard',
        href: DASHBOARD_URL,
        external: false,
    },
    {
        icon: CalendarCheck2,
        title: 'Customers pick a slot at checkout',
        text: 'A two-step delivery date/time picker appears automatically on both the classic and block checkout — no setup required.',
    },
    {
        icon: CreditCard,
        title: 'Pricing and capacity are automatic',
        text: 'A slot’s price is added to the order total as soon as it’s selected, and capacity is enforced with a database-level lock so two customers can never book the same last space.',
    },
    {
        icon: ShieldCheck,
        title: 'Capacity releases itself',
        text: 'If an order is cancelled, fails, is refunded, or is left unpaid for more than 6 hours, its reserved space is returned to the slot.',
    },
    {
        icon: ClipboardList,
        title: 'See which orders belong to a slot',
        text: 'Use "View orders" on any slot in the Dashboard, or check the "Delivery Slot" column on the WooCommerce Orders screen.',
    },
];

function HowToUse() {
    return (
        <div className="dsw-gs-section">
            <SectionHead
                title="How Delivery Slots works"
                subtitle="Five steps from an empty dashboard to slots your customers can book."
            />

            <div className="dsw-gs-grid dsw-gs-grid--three">
                {STEPS.map((step, i) => (
                    <ResourceCard key={i} {...step} />
                ))}
            </div>
        </div>
    );
}

export default HowToUse;
