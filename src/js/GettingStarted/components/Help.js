import { Bug, CirclePlay, FileText, Headset, MessagesSquare } from 'lucide-react';

import SectionHead from './SectionHead';
import ResourceCard from './ResourceCard';
import { CONTACT_EMAIL, GITHUB_URL, ISSUES_URL } from '../links';

function Help() {
    return (
        <div className="dsw-gs-section">
            <SectionHead
                title="Tutorial, Docs, Support & Community"
                subtitle="Get all the Delivery Slots resources in one place."
            />

            <div className="dsw-gs-grid">
                <ResourceCard
                    icon={FileText}
                    title="Documentation"
                    text="If you get stuck while exploring a feature, the README and source walk through how everything fits together."
                    cta="Open Docs"
                    href={GITHUB_URL}
                />

                <ResourceCard
                    icon={Headset}
                    title="Customer Support"
                    text="Got a question this page doesn't answer? Send an email and we'll get back to you."
                    cta="Contact Support"
                    href={`mailto:${CONTACT_EMAIL}`}
                    external={false}
                />

                <ResourceCard
                    icon={Bug}
                    title="Report an Issue"
                    text="Found a bug or want to request a feature? Open an issue and it'll be picked up from there."
                    cta="Open an Issue"
                    href={ISSUES_URL}
                />

                <ResourceCard
                    icon={MessagesSquare}
                    title="Join the Community"
                    text="Connect with other users and contributors, share feedback, and follow along with what's next."
                    cta="Join Community"
                    href={GITHUB_URL}
                />
            </div>
        </div>
    );
}

export default Help;
