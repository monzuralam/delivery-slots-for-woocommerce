function Bar({ width, height = 12, className = '' }) {
    return <span className={`dsw-skeleton ${className}`} style={{ width, height }} />;
}

export function SkeletonStatsCards() {
    return (
        <div className="dsw-stats" aria-hidden="true">
            {[0, 1, 2, 3].map((i) => (
                <div className="dsw-stat-card" key={i}>
                    <div>
                        <Bar width={70} height={10} className="dsw-skeleton--label" />
                        <Bar width={40} height={26} className="dsw-skeleton--value" />
                    </div>
                    <span className="dsw-skeleton dsw-skeleton--circle" />
                </div>
            ))}
        </div>
    );
}

export function SkeletonToolbar() {
    return (
        <div className="dsw-toolbar" aria-hidden="true">
            <div className="dsw-toolbar__search">
                <Bar width="100%" height={34} className="dsw-skeleton--field" />
            </div>
            <Bar width={140} height={34} className="dsw-skeleton--field" />
            <span className="dsw-skeleton dsw-skeleton--icon" />
        </div>
    );
}

export function SkeletonTableRows({ rows = 5 }) {
    return (
        <div className="dsw-table-wrap" aria-hidden="true">
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
                    {Array.from({ length: rows }).map((_, i) => (
                        <tr key={i}>
                            <td><Bar width={90} /></td>
                            <td><Bar width={110} /></td>
                            <td><Bar width={60} /></td>
                            <td><Bar width={50} /></td>
                            <td><Bar width={64} height={18} className="dsw-skeleton--badge" /></td>
                            <td>
                                <div className="dsw-table__actions">
                                    <span className="dsw-skeleton dsw-skeleton--icon" />
                                    <span className="dsw-skeleton dsw-skeleton--icon" />
                                    <span className="dsw-skeleton dsw-skeleton--icon" />
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
