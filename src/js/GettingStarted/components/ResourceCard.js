import { ChevronRight } from 'lucide-react';

function ResourceCard({ icon: Icon, title, text, cta, href, external = true }) {
    return (
        <div className="dsw-gs-card">
            <span className="dsw-gs-card__icon">
                <Icon size={24} />
            </span>

            <div className="dsw-gs-card__body">
                <h3>{title}</h3>
                <p>{text}</p>

                {cta ? (
                    href ? (
                        <a
                            className="dsw-gs-card__cta"
                            href={href}
                            {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                        >
                            {cta}
                            <ChevronRight size={16} />
                        </a>
                    ) : (
                        <span className="dsw-gs-card__cta dsw-gs-card__cta--muted">{cta}</span>
                    )
                ) : null}
            </div>
        </div>
    );
}

export default ResourceCard;
