import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './GettingStarted/App'

const element = document.getElementById('dsw-getting-started');

if (element) {
  ReactDOM.createRoot(element).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  );
}
