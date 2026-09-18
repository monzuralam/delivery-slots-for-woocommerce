import { useState } from 'react';
import { ChevronDown } from 'lucide-react';

import SectionHead from './SectionHead';

const FAQS = [
    {
        q: 'What happens when a delivery slot is full?',
        a: 'The slot is still shown at checkout but marked "Sold out" and can’t be selected until capacity frees up.',
    },
    {
        q: 'What happens if an order with a booked slot is cancelled or refunded?',
        a: 'The slot’s booked count is automatically decreased, freeing up the space for other customers.',
    },
    {
        q: 'What if a customer never completes payment?',
        a: 'An hourly background task releases the slot automatically once the order has been unpaid for more than 6 hours.',
    },
    {
        q: 'Can I lower a slot’s capacity below the number of orders already booked into it?',
        a: 'No — the Dashboard blocks this to prevent overbooking. Cancel or reassign the existing orders first.',
    },
    {
        q: 'Can I delete a slot that already has bookings?',
        a: 'No — slots with active bookings can’t be deleted. Set the slot’s status to "Inactive" or "Closed" instead.',
    },
    {
        q: 'Does this work with both the classic and block checkout?',
        a: 'Yes — the delivery date/time picker supports both, and pricing/capacity enforcement works identically either way.',
    },
    {
        q: 'Does this support WooCommerce’s High-Performance Order Storage (HPOS)?',
        a: 'Yes — order lookups and the Orders list column work whether HPOS is enabled or not.',
    },
];

function Faq() {
    const [openItems, setOpenItems] = useState(() => new Set());

    function toggle(index) {
        setOpenItems((prev) => {
            const next = new Set(prev);

            if (next.has(index)) {
                next.delete(index);
            } else {
                next.add(index);
            }

            return next;
        });
    }

    return (
        <div className="dsw-gs-section">
            <SectionHead
                title="Frequently asked questions"
                subtitle="The things store owners ask most about delivery slots."
            />

            <div className="dsw-gs-faq">
                {FAQS.map((item, i) => {
                    const isOpen = openItems.has(i);

                    return (
                        <div className={`dsw-gs-faq-card${isOpen ? ' dsw-gs-faq-card--open' : ''}`} key={i}>
                            <button
                                type="button"
                                className="dsw-gs-faq-card__question"
                                onClick={() => toggle(i)}
                                aria-expanded={isOpen}
                            >
                                <span>{item.q}</span>
                                <ChevronDown size={16} className="dsw-gs-faq-card__chevron" />
                            </button>

                            {isOpen ? <p className="dsw-gs-faq-card__answer">{item.a}</p> : null}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

export default Faq;
