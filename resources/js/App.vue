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
				console.error('Error fetching teams in router guard:', error)
			}
		}

		// Redirect to teams.create if user has no teams
		if (teamStore.ownedTeams.length === 0 && teamStore.joinedTeams.length === 0) {
			router.push({ name: 'teams.create' })
		}
	}
})
</script>

<template>
	<div class="min-h-screen bg-neutral-100">
		<Notifications />
		<router-view />
	</div>
</template>
