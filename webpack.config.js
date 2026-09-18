/**
 * External Dependencies
 */
const path = require('path');

/**
 * WordPress Dependencies
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config.js');
const glob = require('glob');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts');

// Dynamically include only lowercase JS entry files from src/js
const jsEntries = glob.sync('./src/js/*.js').reduce((entries, file) => {
    const filename = path.basename(file); // Get the filename
    // Check if the filename is completely lowercase
    if (filename === filename.toLowerCase()) {
        const name = path.basename(file, '.js'); // Use the file name without extension as the key
        entries[name] = file;
    }
    return entries;
}, {});

// Standalone stylesheets: src/css/*.scss compiles to assets/build/*.css.
// When a JS entry shares its name (e.g. dashboard.js + dashboard.scss),
// merge them into one entry so both the .js and .css get emitted, instead
// of the scss entry silently overwriting the js entry at the same key.
const entry = { ...jsEntries };

glob.sync('./src/css/*.scss').forEach((file) => {
    const name = path.basename(file, '.scss');
    entry[name] = entry[name] ? [].concat(entry[name], file) : file;
});

module.exports = {
    ...defaultConfig,
    entry,
    output: {
        path: path.resolve(__dirname, 'assets/build'),
        filename: '[name].js', // Outputs multiple files with their respective names
    },
    // A CSS-only entry (like dashboard.scss above) still gets an empty .js
    // chunk emitted by default; this plugin drops those empty outputs.
    plugins: [...defaultConfig.plugins, new RemoveEmptyScriptsPlugin()],
}