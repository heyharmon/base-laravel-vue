import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useNotificationStore = defineStore('notification', () => {
	const notifications = ref([])
	let nextId = 1

	function addNotification(notification) {
		const message = notification.message
		const type = notification.type || 'info'

		// Check if a notification with the same message and type already exists
		const existingNotification = notifications.value.find((n) => n.message === message && n.type === type)

		// If duplicate exists, don't add a new one
		if (existingNotification) {
			return
		}

		const notif = {
			id: nextId++,
			type,
			message
		}
		notifications.value.push(notif)

		// setTimeout(() => {
		// 	removeNotification(notif.id)
		// }, 5000)
	}

	function removeNotification(id) {
		notifications.value = notifications.value.filter((n) => n.id !== id)
	}

	return {
		notifications,
		addNotification,
		removeNotification
	}
})
