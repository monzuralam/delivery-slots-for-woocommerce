import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './Settings/App'

const element = document.getElementById('dsw-settings');

if (element) {
  ReactDOM.createRoot(element).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  );
}
