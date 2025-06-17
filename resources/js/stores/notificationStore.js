import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useNotificationStore = defineStore('notification', () => {
	const notifications = ref([])
	let nextId = 1

	function addNotification(notification) {
		const notif = {
			id: nextId++,
			type: notification.type || 'info',
			message: notification.message
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
