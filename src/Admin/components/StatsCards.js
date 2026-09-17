import { CalendarCheck2, Package, TrendingUp, Users } from 'lucide-react';

function StatCard({ variant, icon, label, value }) {
    return (
        <div className={`dsw-stat-card dsw-stat-card--${variant}`}>
            <div>
                <p className="dsw-stat-card__label">{label}</p>
                <p className="dsw-stat-card__value">{value}</p>
            </div>
            <div className="dsw-stat-card__icon">{icon}</div>
        </div>
    );
}

function StatsCards({ stats }) {
    const { total = 0, active = 0, total_capacity: totalCapacity = 0, total_booked: totalBooked = 0 } = stats || {};

    return (
        <div className="dsw-stats">
            <StatCard variant="indigo" icon={<Package size={20} />} label="Total Slots" value={total} />
            <StatCard variant="green" icon={<CalendarCheck2 size={20} />} label="Active Slots" value={active} />
            <StatCard variant="blue" icon={<Users size={20} />} label="Total Capacity" value={totalCapacity} />
            <StatCard variant="amber" icon={<TrendingUp size={20} />} label="Booked" value={totalBooked} />
        </div>
    );
}

export default StatsCards;
