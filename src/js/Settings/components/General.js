import { useSettings } from '../context/SettingsContext';

function General() {
    const { settings, updateField } = useSettings();

    return (
        <div className="dsw-gs-section">
            <div className="dsw-settings-card">
                <div className="dsw-settings-field dsw-settings-field--toggle">
                    <div>
                        <label htmlFor="dsw-enable">Enable delivery slots</label>
                        <p>Show the delivery date &amp; time picker at checkout.</p>
                    </div>
                    <label className="dsw-switch">
                        <input
                            id="dsw-enable"
                            type="checkbox"
                            checked={Boolean(settings.enable_dsw)}
                            onChange={(e) => updateField('enable_dsw', e.target.checked)}
                        />
                        <span className="dsw-switch__track" />
                    </label>
                </div>

                <div className="dsw-settings-field">
                    <label htmlFor="dsw-title">Delivery slot section title</label>
                    <p>Heading shown above the picker at checkout.</p>
                    <input
                        id="dsw-title"
                        type="text"
                        value={settings.default_delivery_slot_title || ''}
                        onChange={(e) => updateField('default_delivery_slot_title', e.target.value)}
                    />
                </div>

                <div className="dsw-settings-field">
                    <label htmlFor="dsw-date-title">Delivery date title</label>
                    <p>Label shown above the date field at checkout.</p>
                    <input
                        id="dsw-date-title"
                        type="text"
                        value={settings.delivery_date_title || ''}
                        onChange={(e) => updateField('delivery_date_title', e.target.value)}
                    />
                </div>

                <div className="dsw-settings-field">
                    <label htmlFor="dsw-time-title">Delivery time title</label>
                    <p>Label shown above the time field at checkout.</p>
                    <input
                        id="dsw-time-title"
                        type="text"
                        value={settings.delivery_time_title || ''}
                        onChange={(e) => updateField('delivery_time_title', e.target.value)}
                    />
                </div>

                <div className="dsw-settings-field">
                    <label htmlFor="dsw-stale-hours">Stale hold hours</label>
                    <p>Release a slot automatically if an order stays unpaid this many hours.</p>
                    <input
                        id="dsw-stale-hours"
                        type="number"
                        min="1"
                        value={settings.stale_hold_hours || ''}
                        onChange={(e) => updateField('stale_hold_hours', e.target.value)}
                    />
                </div>
            </div>
        </div>
    );
}

export default General;
