import { CalendarCheck2, ClipboardList, CreditCard, LayoutDashboard, Settings as SettingsIcon, ShieldCheck } from 'lucide-react';

import SectionHead from './SectionHead';
import ResourceCard from './ResourceCard';

const DASHBOARD_URL = 'admin.php?page=delivery-slots-for-woocommerce';
const SETTINGS_URL = 'admin.php?page=delivery-slots-for-woocommerce-settings';

const FEATURES = [
    {
        icon: CalendarCheck2,
        title: 'Two-step delivery picker',
        text: 'Customers pick a date, then a time — live on both the classic and block checkout, no setup required.',
    },
    {
        icon: CreditCard,
        title: 'Per-slot pricing',
        text: 'Each slot can add its own delivery fee, applied to the order total the moment it’s selected.',
    },
    {
        icon: ShieldCheck,
        title: 'Overselling-safe capacity',
        text: 'A database-level lock means two customers can never book the same last space in a slot.',
    },
    {
        icon: ClipboardList,
        title: 'Order ↔ slot visibility',
        text: 'See every order booked into a slot, and each order’s slot right in the WooCommerce Orders list.',
    },
];

function StatusLine({ data }) {
    const slotCount = Number(data.slotCount) || 0;
    const checkoutType = data.checkoutType;

    let checkoutText;

    if (checkoutType === 'block') {
        checkoutText = 'Your checkout uses the WooCommerce block checkout — the picker works automatically, no setup needed.';
    } else if (checkoutType === 'classic') {
        checkoutText = 'Your checkout uses the classic (shortcode) checkout — the picker works automatically, no setup needed.';
    } else {
        checkoutText = 'We couldn’t detect the WooCommerce Checkout block or the [woocommerce_checkout] shortcode on your Checkout page — add one of those for the picker to appear.';
    }

    return (
        <div className="dsw-gs-status">
            <p>
                You currently have <strong>{slotCount}</strong> delivery slot{slotCount === 1 ? '' : 's'} configured.
            </p>
            <p>{checkoutText}</p>
        </div>
    );
}

function Home({ data }) {
    return (
        <div className="dsw-gs-section">
            <SectionHead
                title="Welcome to Delivery Slots for WooCommerce"
                subtitle="Let customers pick a delivery date & time at checkout. Here's where things stand."
            />

            <StatusLine data={data} />

            <SectionHead title="Why Delivery Slots" subtitle="What you get out of the box." />

            <div className="dsw-gs-grid dsw-gs-grid--three">
                {FEATURES.map((feature, i) => (
                    <ResourceCard key={i} {...feature} />
                ))}
            </div>

            <SectionHead title="Get started" subtitle="Jump straight to what you need." />

            <div className="dsw-gs-grid">
                <ResourceCard
                    icon={LayoutDashboard}
                    title="Manage Slots"
                    text="Create, edit, and monitor the delivery time slots customers can choose at checkout."
                    cta="Open Dashboard"
                    href={DASHBOARD_URL}
                    external={false}
                />
                <ResourceCard
                    icon={SettingsIcon}
                    title="Settings"
                    text="Configure plugin behavior — enable/disable, checkout labels, and stale hold hours."
                    cta="Open Settings"
                    href={SETTINGS_URL}
                    external={false}
                />
            </div>
        </div>
    );
}

export default Home;
