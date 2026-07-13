import React from 'react'
import ReactDOM from 'react-dom/client'
import 'antd/dist/reset.css'
import './admin.css'
import App from './App'
import { resolveAdminBase } from './config/adminBase'

const savedTheme = localStorage.getItem('lordserial_admin_theme')
document.documentElement.dataset.adminTheme = savedTheme === 'dark' ? 'dark' : 'light'

const rootEl = document.getElementById('app')
if (!rootEl) {
  throw new Error('#app not found')
}

resolveAdminBase()
  .then((basename) => {
    const root = ReactDOM.createRoot(rootEl)
    root.render(
      React.createElement(React.StrictMode, null, React.createElement(App, { basename })),
    )
  })
  .catch((error: unknown) => {
    rootEl.innerHTML = `<div style="padding:24px;font-family:sans-serif;color:#cf1322">${String((error as Error).message)}</div>`
  })
