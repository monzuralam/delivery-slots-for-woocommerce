import { useRef, useState } from 'react';
import { FileDown, FileUp } from 'lucide-react';

import { exportSettings, importSettings } from '../api';
import { useSettings } from '../context/SettingsContext';
import { notifySuccess, notifyError } from '../../Dashboard/swal';

const SCOPE_OPTIONS = [
    { value: 'everything', label: 'Everything' },
    { value: 'settings', label: 'Settings' },
    { value: 'slots', label: 'Slots' },
    { value: 'bookings', label: 'Booking' },
];

function Tools() {
    const { settings, updateField } = useSettings();
    const fileInputRef = useRef(null);
    const [exportScope, setExportScope] = useState('everything');
    const [importing, setImporting] = useState(false);

    const handleImportClick = () => {
        fileInputRef.current?.click();
    };

    const handleFileChange = async (e) => {
        const file = e.target.files[0];
        e.target.value = '';

        if (!file) {
            return;
        }

        const text = await file.text();

        const confirmed = await window.Swal.fire({
            icon: 'warning',
            title: 'Import slots & settings?',
            html: 'This replaces plugin settings and existing slot definitions.<br>Slots with active orders are kept and skipped.',
            showCancelButton: true,
            confirmButtonText: 'Import',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
            customClass: { popup: 'dsw-swal' },
        });

        if (!confirmed.isConfirmed) {
            return;
        }

        setImporting(true);

        try {
            const result = await importSettings(text);
            notifySuccess(`Imported ${result.imported} slot(s). Skipped ${result.skipped_with_bookings} with active bookings.`);
        } catch (error) {
            notifyError(error.message);
        } finally {
            setImporting(false);
        }
    };

    return (
        <div className="dsw-gs-section">
            <div className="dsw-settings-card">
                <div className="dsw-settings-field dsw-settings-field--toggle">
                    <div>
                        <label htmlFor="dsw-autosave">AutoSave</label>
                        <p>Automatically save General settings changes without clicking Save.</p>
                    </div>
                    <label className="dsw-switch">
                        <input
                            id="dsw-autosave"
                            type="checkbox"
                            checked={Boolean(settings.auto_save)}
                            onChange={(e) => updateField('auto_save', e.target.checked)}
                        />
                        <span className="dsw-switch__track" />
                    </label>
                </div>

                <div className="dsw-settings-field">
                    <label htmlFor="dsw-export-scope">Export</label>
                    <p>Choose what to include, then download it as a JSON file. Bookings are for backup only and are never restored by import.</p>
                    <select
                        id="dsw-export-scope"
                        value={exportScope}
                        onChange={(e) => setExportScope(e.target.value)}
                    >
                        {SCOPE_OPTIONS.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>

                    <button type="button" className="dsw-btn dsw-btn--secondary" onClick={() => exportSettings(exportScope)}>
                        <FileDown size={16} />
                        Export
                    </button>
                </div>

                <div className="dsw-settings-field">
                    <label htmlFor="dsw-import">Import</label>
                    <p>Restore settings and slot definitions from a previously exported file.</p>
                    <button
                        id="dsw-import"
                        type="button"
                        className="dsw-btn dsw-btn--secondary"
                        disabled={importing}
                        onClick={handleImportClick}
                    >
                        <FileUp size={16} />
                        {importing ? 'Importing…' : 'Import from file'}
                    </button>
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept="application/json"
                        hidden
                        onChange={handleFileChange}
                    />
                </div>

                <div className="dsw-settings-field dsw-settings-field--toggle">
                    <div>
                        <label htmlFor="dsw-delete-on-uninstall">Delete data on uninstall</label>
                        <p>Permanently remove all delivery slots, bookings, and settings when this plugin is deleted. Off by default.</p>
                    </div>
                    <label className="dsw-switch">
                        <input
                            id="dsw-delete-on-uninstall"
                            type="checkbox"
                            checked={Boolean(settings.delete_data_on_uninstall)}
                            onChange={(e) => updateField('delete_data_on_uninstall', e.target.checked)}
                        />
                        <span className="dsw-switch__track" />
                    </label>
                </div>
            </div>
        </div>
    );
}

export default Tools;
