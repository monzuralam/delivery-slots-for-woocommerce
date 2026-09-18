import { useState } from 'react';
import { BookOpen, CalendarClock, HelpCircle, Home as HomeIcon, LifeBuoy, ScrollText } from 'lucide-react';

import Home from './components/Home';
import HowToUse from './components/HowToUse';
import Faq from './components/Faq';
import Changelog from './components/Changelog';
import Help from './components/Help';

const TABS = [
    { id: 'home', label: 'Home', icon: HomeIcon },
    { id: 'basic-use', label: 'Basic Use', icon: BookOpen },
    { id: 'faq', label: 'FAQ', icon: HelpCircle },
    { id: 'changelog', label: 'Changelog', icon: ScrollText },
    { id: 'help', label: 'Help', icon: LifeBuoy },
];

function getData() {
    return (typeof window !== 'undefined' && window.dsw) || {};
}

function App() {
    const [activeTab, setActiveTab] = useState(localStorage.getItem('dsw_getting_started_active_tab') || 'home');
    const data = getData();

    return (
        <div className="dsw-app">
            <div className="dsw-gs-hero">
                <a
                    className="dsw-gs-hero__support"
                    href={dsw.githubUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <LifeBuoy size={15} />
                    {wp.i18n.__('Support', 'delivery-slots-for-woocommerce')}
                </a>

                <div className="dsw-gs-hero__brand">
                    <span className="dsw-gs-hero__logo">
                        <CalendarClock size={24} />
                    </span>
                    {wp.i18n.__('Delivery Slots for WooCommerce', 'delivery-slots-for-woocommerce')}
                </div>

                <p className="dsw-gs-hero__tagline">
                    Welcome! Let customers pick a delivery date &amp; time at checkout. Let&apos;s get started.
                </p>

                <nav className="dsw-gs-hero__nav">
                    {TABS.map((tab) => {
                        const Icon = tab.icon;
                        const isActive = tab.id === activeTab;

                        return (
                            <button
                                type="button"
                                key={tab.id}
                                className={`dsw-gs-navitem${isActive ? ' dsw-gs-navitem--active' : ''}`}
                                onClick={() => {
                                    setActiveTab(tab.id);
                                    localStorage.setItem('dsw_getting_started_active_tab', tab.id);
                                }}
                            >
                                <Icon size={16} />
                                {tab.label}
                            </button>
                        );
                    })}
                </nav>
            </div>

            <div className="dsw-gs-body">
                {activeTab === 'home' && <Home data={data} />}
                {activeTab === 'basic-use' && <HowToUse />}
                {activeTab === 'faq' && <Faq />}
                {activeTab === 'changelog' && <Changelog />}
                {activeTab === 'help' && <Help />}

                {data.version ? (
                    <p className="dsw-gs-version">Delivery Slots for WooCommerce v{data.version}</p>
                ) : null}
            </div>
        </div>
    );
}

export default App;
