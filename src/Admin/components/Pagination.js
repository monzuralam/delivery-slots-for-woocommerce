import { ChevronLeft, ChevronRight } from 'lucide-react';

function Pagination({ page, totalPages, total, onChange }) {
    if (total === 0) {
        return null;
    }

    return (
        <div className="dsw-pagination">
            <span>
                Page {page} of {Math.max(totalPages, 1)} · {total} slot{total === 1 ? '' : 's'}
            </span>
            <div className="dsw-pagination__controls">
                <button
                    type="button"
                    className="dsw-btn dsw-btn--secondary dsw-btn--icon"
                    disabled={page <= 1}
                    onClick={() => onChange(page - 1)}
                >
                    <ChevronLeft size={15} />
                </button>
                <button
                    type="button"
                    className="dsw-btn dsw-btn--secondary dsw-btn--icon"
                    disabled={page >= totalPages}
                    onClick={() => onChange(page + 1)}
                >
                    <ChevronRight size={15} />
                </button>
            </div>
        </div>
    );
}

export default Pagination;
