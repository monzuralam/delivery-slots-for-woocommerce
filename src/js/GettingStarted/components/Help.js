import { Bug, CirclePlay, FileText, Headset, MessagesSquare } from 'lucide-react';

import SectionHead from './SectionHead';
import ResourceCard from './ResourceCard';

function Help() {
    const { docsUrl, issuesUrl, contactEmail, communityUrl } = dsw;

    return (
        <div className="dsw-gs-section">
            <SectionHead
                title={wp.i18n.__('Tutorial, Docs, Support & Community', 'delivery-slots-for-woocommerce')}
                subtitle={wp.i18n.__('Get all the Delivery Slots resources in one place.', 'delivery-slots-for-woocommerce')}
            />

            <div className="dsw-gs-grid">
                <ResourceCard
                    icon={FileText}
                    title={wp.i18n.__('Documentation', 'delivery-slots-for-woocommerce')}
                    text={wp.i18n.__('If you get stuck while exploring a feature, the README and source walk through how everything fits together.', 'delivery-slots-for-woocommerce')}
                    cta={wp.i18n.__('Open Docs', 'delivery-slots-for-woocommerce')}
                    href={docsUrl}
                />

                <ResourceCard
                    icon={Headset}
                    title={wp.i18n.__('Customer Support', 'delivery-slots-for-woocommerce')}
                    text={wp.i18n.__('Got a question this page doesn\'t answer? Send an email and we\'ll get back to you.', 'delivery-slots-for-woocommerce')}
                    cta={wp.i18n.__('Contact Support', 'delivery-slots-for-woocommerce')}
                    href={`mailto:${contactEmail}`}
                    external={false}
                />

                <ResourceCard
                    icon={Bug}
                    title={wp.i18n.__('Report an Issue', 'delivery-slots-for-woocommerce')}
                    text={wp.i18n.__('Found a bug or want to request a feature? Open an issue and it\'ll be picked up from there.', 'delivery-slots-for-woocommerce')}
                    cta={wp.i18n.__('Open an Issue', 'delivery-slots-for-woocommerce')}
                    href={issuesUrl}
                />

                <ResourceCard
                    icon={MessagesSquare}
                    title={wp.i18n.__('Join the Community', 'delivery-slots-for-woocommerce')}
                    text={wp.i18n.__('Connect with other users and contributors, share feedback, and follow along with what\'s next.', 'delivery-slots-for-woocommerce')}
                    cta={wp.i18n.__('Join Community', 'delivery-slots-for-woocommerce')}
                    href={communityUrl}
                />
            </div>
        </div>
    );
}

export default Help;
