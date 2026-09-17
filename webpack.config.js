/**
 * External Dependencies
 */
const path = require('path');

/**
 * WordPress Dependencies
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config.js');
const glob = require('glob');

module.exports = {
    ...defaultConfig,
    entry: {
        // Dynamically include only lowercase JS files from the src folder
        ...glob.sync('./src/*.js').reduce((entries, file) => {
            const filename = path.basename(file); // Get the filename
            // Check if the filename is completely lowercase
            if (filename === filename.toLowerCase()) {
                const name = path.basename(file, '.js'); // Use the file name without extension as the key
                entries[name] = file;
            }
            return entries;
        }, {}),
    },
    output: {
        path: path.resolve(__dirname, 'assets/build'),
        filename: '[name].js', // Outputs multiple files with their respective names
    },
}