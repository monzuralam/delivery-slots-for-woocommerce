import { useState } from 'react';
import { Settings as SettingsIcon, Wrench } from 'lucide-react';

import { SettingsProvider } from './context/SettingsContext';
import Header from './components/Header';
import General from './components/General';
import Tools from './components/Tools';

const TABS = [
    { id: 'general', label: 'General', icon: SettingsIcon },
    { id: 'tools', label: 'Tools', icon: Wrench },
];

function App() {
    const [activeTab, setActiveTab] = useState('general');

    return (
        <SettingsProvider>
            <div className="dsw-app">
                <Header showSave={activeTab === 'general'} />

                <div className="dsw-content">
                    <div className="dsw-settings-layout">
                        <nav className="dsw-sidebar">
                            {TABS.map((tab) => {
                                const Icon = tab.icon;
                                const isActive = tab.id === activeTab;

                                return (
                                    <button
                                        type="button"
                                        key={tab.id}
                                        className={`dsw-sidebar__item${isActive ? ' dsw-sidebar__item--active' : ''}`}
                                        onClick={() => setActiveTab(tab.id)}
                                    >
                                        <Icon size={16} />
                                        {tab.label}
                                    </button>
                                );
                            })}
                        </nav>

                        <div className="dsw-settings-main">
                            {activeTab === 'general' && <General />}
                            {activeTab === 'tools' && <Tools />}
                        </div>
                    </div>
                </div>
            </div>
        </SettingsProvider>
    );
}

export default App;
