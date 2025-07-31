import api from './api'

const auth = {
	async login(credentials) {
		console.log('Logging in...')
		const response = await api.post('/login', credentials)
		localStorage.setItem('token', response.token)
		localStorage.setItem('user', JSON.stringify(response.user))
		return response
	},

	async register(userData) {
		console.log('Registering...')
		const response = await api.post('/register', userData)
		localStorage.setItem('token', response.token)
		localStorage.setItem('user', JSON.stringify(response.user))
		return response
	},

	async logout() {
		console.log('Logging out...')
		try {
			await api.post('/logout')
		} catch (error) {
			console.error('Logout error:', error)
		} finally {
			localStorage.removeItem('token')
			localStorage.removeItem('user')
		}
	},

	getToken() {
		return localStorage.getItem('token')
	},

	getUser() {
		const user = localStorage.getItem('user')
		return user ? JSON.parse(user) : null
	},

	isAuthenticated() {
		return !!this.getToken()
	},

	async forgotPassword(email) {
		const response = await api.post('/forgot-password', { email })
		return response
	},

	async resetPassword(resetData) {
		return await api.post('/reset-password', resetData)
	}
}

export default auth
