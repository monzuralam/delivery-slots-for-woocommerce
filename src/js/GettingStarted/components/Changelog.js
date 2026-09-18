import { CheckCircle2 } from 'lucide-react';

import SectionHead from './SectionHead';

function Changelog() {
    const changelog = [
        {
            version: '1.0.0',
            date: '2026-09-18',
            items: [
                'Initial release',
            ],
        },
    ];

    return (
        <div className="dsw-gs-section">
            <SectionHead title="What's new" subtitle="Every release and what changed in it." />

            <div className="dsw-gs-changelog">
                {changelog.map((entry) => (
                    <div className="dsw-gs-changelog-card" key={entry.version}>
                        <div className="dsw-gs-changelog-card__head">
                            <span className="dsw-badge dsw-badge--active">v{entry.version}</span>
                            <span className="dsw-gs-changelog-card__date">{entry.date}</span>
                        </div>
                        <ul>
                            {entry.items.map((item, i) => (
                                <li key={i}>
                                    <CheckCircle2 size={14} />
                                    <span>{item}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default Changelog;
