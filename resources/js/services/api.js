import axios from 'axios'
import { useNotificationStore } from '@/stores/notificationStore'

const api = axios.create({
	baseURL: '/api',
	headers: {
		'Content-Type': 'application/json',
		Accept: 'application/json',
		'X-Requested-With': 'XMLHttpRequest'
	},
	withCredentials: true // Required for CSRF cookie to be sent with requests
})

// Request interceptor for adding auth token
api.interceptors.request.use((config) => {
	const token = localStorage.getItem('token')
	if (token) {
		config.headers.Authorization = `Bearer ${token}`
	}
	return config
})

// Response interceptor for handling errors globally
api.interceptors.response.use(
	(response) => response.data,
	(error) => {
		const notificationStore = useNotificationStore()

		// Check if it's a network/connection error
		if (!error.response) {
			// This catches network errors including ERR_CONNECTION_CLOSED
			let message = 'Network error. Please check your internet connection.'

			// Check for specific connection error types
			if (
				error.code === 'ERR_NETWORK' ||
				error.code === 'ERR_CONNECTION_CLOSED' ||
				error.message.includes('ERR_CONNECTION_CLOSED') ||
				error.message.includes('Network Error')
			) {
				message = 'Connection lost. Please check your internet connection and refresh.'
			}

			notificationStore.addNotification({
				type: 'error',
				message
			})

			// return Promise.reject(error)
		}

		// Handle server response errors (when error.response exists)
		const status = error.response.status

		if (status === 401) {
			localStorage.removeItem('token')
			localStorage.removeItem('user')
			window.location.href = '/login'
		}

		let message = 'An error occurred. Please try again.'
		const data = error.response.data
		if (data) {
			if (typeof data === 'string') {
				message = data
			} else if (data.message) {
				message = data.message
			} else if (data.error) {
				message = data.error
			}
			if (status === 422 && data.errors) {
				const firstKey = Object.keys(data.errors)[0]
				message = data.errors[firstKey][0] || message
			}
		}

		// Create a proper error object with the message
		const errorObj = new Error(message)
		errorObj.response = error.response
		errorObj.status = status

		return Promise.reject(errorObj)
	}
)

export default api
