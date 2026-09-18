import { checkSlotConflict } from './api';

function getSwal() {
    if (typeof window === 'undefined' || !window.Swal) {
        throw new Error('SweetAlert2 is not loaded.');
    }

    return window.Swal;
}

export function notifySuccess(message) {
    getSwal().fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        customClass: { popup: 'dsw-swal' },
    });
}

export function notifyError(message) {
    getSwal().fire({
        toast: true,
        position: 'top-end',
        icon: 'error',
        title: message,
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        customClass: { popup: 'dsw-swal' },
    });
}

function escapeAttr(value) {
    return String(value).replace(/"/g, '&quot;');
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[c]));
}

const STATUS_OPTIONS = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'full', label: 'Full' },
    { value: 'closed', label: 'Closed' },
];

export async function openSlotForm(slot) {
    const Swal = getSwal();
    const isEditing = Boolean(slot);
    const values = {
        slot_date: '',
        start_time: '',
        end_time: '',
        capacity: 10,
        price: 0,
        status: 'active',
        ...slot,
    };

    const { value } = await Swal.fire({
        title: isEditing ? 'Edit Slot' : 'Add Slot',
        confirmButtonText: isEditing ? 'Save changes' : 'Create slot',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#6b7280',
        showCancelButton: true,
        focusConfirm: false,
        customClass: { popup: 'dsw-swal' },
        html: `
            <div class="dsw-swal-form">
                <div class="dsw-swal-form__field">
                    <label for="dsw-slot-date">Date</label>
                    <input id="dsw-slot-date" type="date" class="swal2-input" value="${escapeAttr(values.slot_date)}">
                </div>
                <div class="dsw-swal-form__row">
                    <div class="dsw-swal-form__field">
                        <label for="dsw-start-time">Start time</label>
                        <input id="dsw-start-time" type="time" class="swal2-input" value="${escapeAttr(values.start_time)}">
                    </div>
                    <div class="dsw-swal-form__field">
                        <label for="dsw-end-time">End time</label>
                        <input id="dsw-end-time" type="time" class="swal2-input" value="${escapeAttr(values.end_time)}">
                    </div>
                </div>
                <div class="dsw-swal-form__row">
                    <div class="dsw-swal-form__field">
                        <label for="dsw-capacity">Capacity</label>
                        <input id="dsw-capacity" type="number" min="0" class="swal2-input" value="${escapeAttr(values.capacity)}">
                    </div>
                    <div class="dsw-swal-form__field">
                        <label for="dsw-price">Price</label>
                        <input id="dsw-price" type="number" min="0" step="0.01" class="swal2-input" value="${escapeAttr(values.price)}">
                    </div>
                </div>
                <div class="dsw-swal-form__field">
                    <label for="dsw-status">Status</label>
                    <select id="dsw-status" class="swal2-select">
                        ${STATUS_OPTIONS.map(
                            (option) =>
                                `<option value="${option.value}"${option.value === values.status ? ' selected' : ''}>${option.label}</option>`
                        ).join('')}
                    </select>
                </div>
            </div>
        `,
        didOpen: () => {
            document.getElementById('dsw-slot-date').focus();
        },
        preConfirm: async () => {
            const data = {
                slot_date: document.getElementById('dsw-slot-date').value,
                start_time: document.getElementById('dsw-start-time').value,
                end_time: document.getElementById('dsw-end-time').value,
                capacity: document.getElementById('dsw-capacity').value,
                price: document.getElementById('dsw-price').value,
                status: document.getElementById('dsw-status').value,
            };

            let error = null;

            if (!data.slot_date) error = 'Date is required.';
            else if (!data.start_time) error = 'Start time is required.';
            else if (!data.end_time) error = 'End time is required.';
            else if (data.start_time >= data.end_time) error = 'End time must be after start time.';
            else if (data.capacity === '' || Number(data.capacity) < 0) error = 'Enter a valid capacity.';
            else if (data.price === '' || Number(data.price) < 0) error = 'Enter a valid price.';

            if (error) {
                Swal.showValidationMessage(error);
                return false;
            }

            try {
                const conflict = await checkSlotConflict({
                    slotDate: data.slot_date,
                    startTime: data.start_time,
                    excludeId: slot?.id,
                });

                if (conflict) {
                    Swal.showValidationMessage('A slot already exists for this date and time.');
                    return false;
                }
            } catch (err) {
                Swal.showValidationMessage(err.message);
                return false;
            }

            return {
                ...data,
                capacity: Number(data.capacity),
                price: Number(data.price),
            };
        },
    });

    return value || null;
}

export function showSlotOrders(slot, orders) {
    const Swal = getSwal();

    const rowsHtml = orders.length
        ? orders
              .map(
                  (order) => `
                    <tr>
                        <td><a href="${escapeAttr(order.edit_url)}" target="_blank" rel="noopener noreferrer">#${escapeHtml(order.order_number)}</a></td>
                        <td>${escapeHtml(order.customer || '—')}</td>
                        <td>${escapeHtml(order.status_label)}</td>
                        <td>${order.total}</td>
                        <td>${escapeHtml(order.date_created)}</td>
                    </tr>
                `
              )
              .join('')
        : `<tr><td colspan="5" class="dsw-swal-orders__empty">No orders booked into this slot yet.</td></tr>`;

    return Swal.fire({
        title: 'Orders for this slot',
        html: `
            <p class="dsw-swal-orders__subtitle">${escapeHtml(slot.slot_date)}, ${escapeHtml(slot.start_time)}–${escapeHtml(slot.end_time)}</p>
            <div class="dsw-swal-orders">
                <table class="dsw-swal-orders__table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
            </div>
        `,
        width: 640,
        confirmButtonText: 'Close',
        confirmButtonColor: '#4f46e5',
        customClass: { popup: 'dsw-swal' },
    });
}

export async function confirmDelete({ title, text }) {
    const result = await getSwal().fire({
        icon: 'warning',
        title,
        text,
        showCancelButton: true,
        confirmButtonText: 'Delete slot',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
        focusCancel: true,
        customClass: { popup: 'dsw-swal' },
    });

    return result.isConfirmed;
}
