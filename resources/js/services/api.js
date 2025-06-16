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
