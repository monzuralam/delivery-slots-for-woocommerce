import { CalendarClock, Plus } from 'lucide-react';

function Header({ onAdd }) {
    return (
        <div className="dsw-header">
            <div className="dsw-header__title">
                <div className="dsw-header__icon">
                    <CalendarClock size={22} />
                </div>
                <div>
                    <h1>Delivery Slots</h1>
                    <p>Create and manage the delivery time slots customers can choose at checkout.</p>
                </div>
            </div>

            <button type="button" className="dsw-btn dsw-btn--primary" onClick={onAdd}>
                <Plus size={16} />
                Add Slot
            </button>
        </div>
    );
}

export default Header;
