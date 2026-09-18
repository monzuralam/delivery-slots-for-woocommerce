const getConfig = () => {
    const config = typeof window !== 'undefined' && window.dsw;

    if (!config) {
        throw new Error('Delivery Slots: REST configuration (window.dsw) is missing.');
    }

    return config;
};

async function request(path, options = {}) {
    const { root, nonce } = getConfig();

    const response = await fetch(`${root}${path}`, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
            ...(options.headers || {}),
        },
    });

    let body = null;

    try {
        body = await response.json();
    } catch (e) {
        body = null;
    }

    if (!response.ok) {
        const message = (body && body.message) || 'Something went wrong. Please try again.';
        throw new Error(message);
    }

    return body;
}

export function fetchSlots({ search = '', status = '', page = 1, perPage = 10 } = {}) {
    const params = new URLSearchParams();

    if (search) params.set('search', search);
    if (status) params.set('status', status);
    params.set('page', page);
    params.set('per_page', perPage);

    return request(`slots?${params.toString()}`);
}

export function fetchStats() {
    return request('slots/stats');
}

export function createSlot(data) {
    return request('slots', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export function updateSlot(id, data) {
    return request(`slots/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
}

export function deleteSlot(id) {
    return request(`slots/${id}`, {
        method: 'DELETE',
    });
}

export function fetchSlotOrders(id) {
    return request(`slots/${id}/orders`);
}
