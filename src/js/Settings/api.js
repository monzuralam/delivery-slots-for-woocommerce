const getConfig = () => {
    const config = typeof window !== 'undefined' && window.dsw;

    if (!config) {
        throw new Error('Delivery Slots: configuration (window.dsw) is missing.');
    }

    return config;
};

async function ajaxRequest(action, body = {}) {
    const { ajaxUrl, ajaxNonce } = getConfig();

    const params = new URLSearchParams({ action, nonce: ajaxNonce });

    Object.entries(body).forEach(([key, value]) => {
        // PHP's empty()/!empty() checks treat "0" as falsy but "false" as
        // truthy, so booleans must be sent as '1'/'0', not stringified as-is.
        params.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : value);
    });

    const response = await fetch(ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params,
    });

    const json = await response.json();

    if (!json.success) {
        throw new Error((json.data && json.data.message) || 'Something went wrong. Please try again.');
    }

    return json.data;
}

export function saveSettings(data) {
    return ajaxRequest('dsw_save_settings', data);
}

export function exportSettings(scope = 'everything') {
    const { ajaxUrl, ajaxNonce } = getConfig();

    window.location.href = `${ajaxUrl}?action=dsw_export_settings&scope=${encodeURIComponent(scope)}&nonce=${encodeURIComponent(ajaxNonce)}`;
}

export function importSettings(payload) {
    return ajaxRequest('dsw_import_settings', { payload });
}
