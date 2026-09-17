import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './Frontend/App'

const element = document.getElementById('wrs-frontend-app');

console.log('Frontend')

if (element) {
    ReactDOM.createRoot(element).render(
        <React.StrictMode>
            <App />
        </React.StrictMode>
    );
}