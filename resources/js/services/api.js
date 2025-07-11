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

// Request interceptor for adding auth token and campaign_id
api.interceptors.request.use((config) => {
	const token = localStorage.getItem('token')
	if (token) {
		config.headers.Authorization = `Bearer ${token}`
	}

	// Check if request needs campaign_id
	const needsCampaignId = ['/prompts', '/organizations', '/articles', '/organization-visibility', '/prompt-run-batch']
	const shouldAddCampaignId = needsCampaignId.some((path) => {
		return config.url?.startsWith(path)
	})

	if (shouldAddCampaignId) {
		const currentCampaign = JSON.parse(localStorage.getItem('currentCampaign') || 'null')
		if (currentCampaign && currentCampaign.id) {
			if (config.method === 'get') {
				// Add campaign_id as query parameter for GET requests
				const separator = config.url.includes('?') ? '&' : '?'
				config.url = `${config.url}${separator}campaign_id=${currentCampaign.id}`
			} else if (config.method === 'post' || config.method === 'put' || config.method === 'patch') {
				// Add campaign_id to request body for POST/PUT/PATCH requests
				if (config.data) {
					config.data.campaign_id = currentCampaign.id
				} else {
					config.data = { campaign_id: currentCampaign.id }
				}
			}
		}
	}

	return config
})

// Response interceptor for handling errors globally
api.interceptors.response.use(
	(response) => response.data,
	(error) => {
		const notificationStore = useNotificationStore()

		if (error.response) {
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

			notificationStore.addNotification({
				type: 'error',
				message
			})

			return Promise.reject(error.response.data)
		}

		notificationStore.addNotification({
			type: 'error',
			message: error.message || 'Network error. Please check your connection.'
		})
		return Promise.reject(error)
	}
)

export default api
