import Vue from 'vue'
import App from './App.vue'

Vue.config.errorHandler = (err, vm, info) => {
	console.error('[OV] Vue error in', vm?.$options?.name, '—', info, err)
}

window.addEventListener('unhandledrejection', (e) => {
	console.error('[OV] Unhandled promise rejection:', e.reason)
})

if (window.__obsidian_viewer_mounted) {
	console.warn('[OV] main.js re-executed by Nextcloud — blocking duplicate mount')
} else {
	window.__obsidian_viewer_mounted = true
	const View = Vue.extend(App)
	new View().$mount('#content')
}
