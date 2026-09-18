import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './Dashboard/App'

const element = document.getElementById('dsw-app');

if (element) {
  ReactDOM.createRoot(element).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  );
}