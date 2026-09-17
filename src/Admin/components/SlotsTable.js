import { CalendarOff, ClipboardList, Clock, Loader2, Pencil, Plus, Trash2, Users } from 'lucide-react';
import StatusBadge from './StatusBadge';

function formatDate(value) {
    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatPrice(value) {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(value);
}

function SlotsTable({ slots, loading, onEdit, onDelete, onAdd, onViewOrders }) {
    if (loading) {
        return (
            <div className="dsw-table-loading">
                <Loader2 size={18} />
                Loading slots…
            </div>
        );
    }

    if (!slots.length) {
        return (
            <div className="dsw-empty">
                <CalendarOff size={40} />
                <h3>No delivery slots yet</h3>
                <p>Create your first slot so customers can pick a delivery window at checkout.</p>
                <button type="button" className="dsw-btn dsw-btn--primary" onClick={onAdd}>
                    <Plus size={16} />
                    Add Slot
                </button>
            </div>
        );
    }

    return (
        <div className="dsw-table-wrap">
            <table className="dsw-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Capacity</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {slots.map((slot) => (
                        <tr key={slot.id}>
                            <td className="dsw-table__cell-primary">{formatDate(slot.slot_date)}</td>
                            <td>
                                <span className="dsw-table__time">
                                    <Clock size={13} />
                                    {slot.start_time} – {slot.end_time}
                                </span>
                            </td>
                            <td>
                                <span className="dsw-table__capacity">
                                    <Users size={13} />
                                    {slot.booked} / {slot.capacity}
                                </span>
                            </td>
                            <td>{formatPrice(slot.price)}</td>
                            <td>
                                <StatusBadge status={slot.status} />
                            </td>
                            <td>
                                <div className="dsw-table__actions">
                                    <button
                                        type="button"
                                        className="dsw-icon-btn"
                                        title="View orders"
                                        onClick={() => onViewOrders(slot)}
                                    >
                                        <ClipboardList size={14} />
                                    </button>
                                    <button
                                        type="button"
                                        className="dsw-icon-btn"
                                        title="Edit slot"
                                        onClick={() => onEdit(slot)}
                                    >
                                        <Pencil size={14} />
                                    </button>
                                    <button
                                        type="button"
                                        className="dsw-icon-btn dsw-icon-btn--danger"
                                        title="Delete slot"
                                        onClick={() => onDelete(slot)}
                                    >
                                        <Trash2 size={14} />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default SlotsTable;
