import { configureEcho } from '@laravel/echo-vue'

// Configure Echo with the same settings as the original echo.js
configureEcho({
	broadcaster: 'reverb',
	cluster: null,
	key: import.meta.env.VITE_REVERB_APP_KEY || '123456789',
	wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
	wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
	wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
	forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
	enabledTransports: ['ws', 'wss'],
	authorizer: (channel) => {
		return {
			authorize: (socketId, callback) => {
				fetch('/broadcasting/auth', {
					method: 'POST',
					body: JSON.stringify({
						socket_id: socketId,
						channel_name: channel.name
					}),
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
						Authorization: localStorage.getItem('token') ? 'Bearer ' + localStorage.getItem('token') : ''
					}
				})
					.then((response) => response.json())
					.then((data) => {
						callback(null, data)
					})
					.catch((error) => {
						console.error('Error authorizing channel:', error)
						callback(error, null)
					})
			}
		}
	}
})
