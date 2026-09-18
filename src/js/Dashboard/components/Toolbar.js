import { RefreshCw, Search } from 'lucide-react';

const STATUS_OPTIONS = [
    { value: '', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'full', label: 'Full' },
    { value: 'closed', label: 'Closed' },
];

function Toolbar({ search, onSearchChange, status, onStatusChange, onRefresh, refreshing }) {
    return (
        <div className="dsw-toolbar">
            <div className="dsw-toolbar__search">
                <Search size={15} />
                <input
                    type="text"
                    placeholder="Search by date (YYYY-MM-DD)…"
                    value={search}
                    onChange={(e) => onSearchChange(e.target.value)}
                />
            </div>

            <select
                className="dsw-toolbar__select"
                value={status}
                onChange={(e) => onStatusChange(e.target.value)}
            >
                {STATUS_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>

            <button
                type="button"
                className="dsw-btn dsw-btn--secondary dsw-btn--icon"
                onClick={onRefresh}
                disabled={refreshing}
                title="Refresh"
            >
                <RefreshCw size={15} className={refreshing ? 'dsw-spin' : ''} />
            </button>
        </div>
    );
}

export default Toolbar;
