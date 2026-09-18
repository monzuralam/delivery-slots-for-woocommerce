function StatusBadge({ status }) {
    return <span className={`dsw-badge dsw-badge--${status}`}>{status}</span>;
}

export default StatusBadge;
