import { useCallback, useEffect, useRef, useState } from 'react';
import { Loader2 } from 'lucide-react';

import { createSlot, deleteSlot, fetchSlotOrders, fetchSlots, fetchStats, updateSlot } from './api';
import { confirmDelete, notifyError, notifySuccess, openSlotForm, showSlotOrders } from './swal';
import Header from './components/Header';
import StatsCards from './components/StatsCards';
import Toolbar from './components/Toolbar';
import SlotsTable from './components/SlotsTable';
import Pagination from './components/Pagination';

const PER_PAGE = 10;

function App() {
    const [slots, setSlots] = useState([]);
    const [stats, setStats] = useState(null);
    const [total, setTotal] = useState(0);
    const [totalPages, setTotalPages] = useState(1);
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [loading, setLoading] = useState(true);
    const [initializing, setInitializing] = useState(true);

    const loadStats = useCallback(async () => {
        try {
            const data = await fetchStats();
            setStats(data);
        } catch (error) {
            notifyError(error.message);
        }
    }, []);

    const loadSlots = useCallback(async () => {
        setLoading(true);

        try {
            const data = await fetchSlots({ search, status, page, perPage: PER_PAGE });
            setSlots(data.items);
            setTotal(data.total);
            setTotalPages(data.total_pages || 1);
        } catch (error) {
            notifyError(error.message);
        } finally {
            setLoading(false);
            setInitializing(false);
        }
    }, [search, status, page]);

    const searchDebounce = useRef(null);

    useEffect(() => {
        loadStats();
    }, [loadStats]);

    useEffect(() => {
        clearTimeout(searchDebounce.current);
        searchDebounce.current = setTimeout(loadSlots, search ? 300 : 0);

        return () => clearTimeout(searchDebounce.current);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, status, page]);

    useEffect(() => {
        setPage(1);
    }, [search, status]);

    const handleRefresh = () => {
        loadSlots();
        loadStats();
    };

    const handleAddClick = async () => {
        const values = await openSlotForm(null);

        if (!values) {
            return;
        }

        try {
            await createSlot(values);
            notifySuccess('Slot created.');
            loadSlots();
            loadStats();
        } catch (error) {
            notifyError(error.message);
        }
    };

    const handleEditClick = async (slot) => {
        const values = await openSlotForm(slot);

        if (!values) {
            return;
        }

        try {
            await updateSlot(slot.id, values);
            notifySuccess('Slot updated.');
            loadSlots();
            loadStats();
        } catch (error) {
            notifyError(error.message);
        }
    };

    const handleViewOrders = async (slot) => {
        try {
            const data = await fetchSlotOrders(slot.id);
            showSlotOrders(slot, data.items || []);
        } catch (error) {
            notifyError(error.message);
        }
    };

    const handleDeleteClick = async (slot) => {
        const confirmed = await confirmDelete({
            title: 'Delete this slot?',
            text: `This will permanently remove the slot on ${slot.slot_date} (${slot.start_time}–${slot.end_time}).`,
        });

        if (!confirmed) {
            return;
        }

        try {
            await deleteSlot(slot.id);
            notifySuccess('Slot deleted.');
            loadSlots();
            loadStats();
        } catch (error) {
            notifyError(error.message);
        }
    };

    return (
        <div className="dsw-app">
            <Header onAdd={handleAddClick} />

            {initializing ? (
                <div className="dsw-app__loading-page">
                    <Loader2 size={18} />
                    Loading dashboard…
                </div>
            ) : (
                <>
                    <StatsCards stats={stats} />

                    <div className="dsw-panel">
                        <Toolbar
                            search={search}
                            onSearchChange={setSearch}
                            status={status}
                            onStatusChange={setStatus}
                            onRefresh={handleRefresh}
                            refreshing={loading}
                        />

                        <SlotsTable
                            slots={slots}
                            loading={loading}
                            onAdd={handleAddClick}
                            onEdit={handleEditClick}
                            onDelete={handleDeleteClick}
                            onViewOrders={handleViewOrders}
                        />

                        <Pagination page={page} totalPages={totalPages} total={total} onChange={setPage} />
                    </div>
                </>
            )}
        </div>
    );
}

export default App;
