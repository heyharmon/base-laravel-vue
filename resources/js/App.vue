<script setup>
import { onMounted } from 'vue'
import { useTeamStore } from '@/stores/teamStore'
import { useRouter } from 'vue-router'
import auth from '@/services/auth'
import Notifications from '@/components/Notifications.vue'

const router = useRouter()

// Check if user has teams
const teamStore = useTeamStore()

onMounted(async () => {
	if (auth.isAuthenticated()) {
		// Load teams if not already loaded
		if (teamStore.ownedTeams.length === 0 && teamStore.joinedTeams.length === 0) {
			try {
				await teamStore.fetchTeams()
			} catch (error) {
				console.error('Error fetching teams:', error)
				// Don't redirect here - let the router handle navigation
			}
		}
		// Remove redirect logic from here - router handles all navigation
	}
})
</script>

<template>
	<div class="min-h-screen bg-neutral-100">
		<Notifications />
		<router-view />
	</div>
</template>
