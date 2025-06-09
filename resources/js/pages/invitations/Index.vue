<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTeamStore } from '@/stores/teamStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'

const router = useRouter()
const teamStore = useTeamStore()
const isSubmitting = ref(false)

onMounted(async () => {
	await teamStore.fetchTeams()
})

const acceptInvitation = async (teamId) => {
	isSubmitting.value = true
	try {
		await teamStore.acceptInvitation(teamId)
		window.location.reload()
	} catch (error) {
		console.error('Error accepting invitation:', error)
	} finally {
		isSubmitting.value = false
	}
}

const declineInvitation = async (teamId) => {
	if (!confirm('Are you sure you want to decline this invitation?')) return

	isSubmitting.value = true
	try {
		await teamStore.declineInvitation(teamId)
	} catch (error) {
		console.error('Error declining invitation:', error)
	} finally {
		isSubmitting.value = false
	}
}
</script>

<template>
	<DefaultLayout>
		<div class="container mx-auto py-8">
			<div class="flex justify-between items-center mb-8">
				<h1 class="text-2xl font-bold">Team Invitations</h1>
			</div>

			<!-- Loading state -->
			<div v-if="teamStore.isLoading" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
			</div>

			<!-- Error state -->
			<div v-else-if="teamStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
				{{ teamStore.error }}
			</div>

			<!-- Pending invitations -->
			<div v-else class="bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden">
				<div class="px-6 py-4 bg-neutral-100 border-b border-neutral-200">
					<h2 class="text-lg font-semibold">Pending Invitations ({{ teamStore.pendingInvitations.length }})</h2>
				</div>

				<div v-if="teamStore.pendingInvitations.length === 0" class="px-6 py-8 text-center text-neutral-500">
					You don't have any pending invitations.
				</div>

				<div v-else class="divide-y divide-neutral-200">
					<div v-for="team in teamStore.pendingInvitations" :key="team.id" class="px-6 py-4 flex items-center justify-between">
						<div>
							<div class="font-medium">{{ team.name }}</div>
							<div v-if="team.owner" class="text-sm text-neutral-500">Owner: {{ team.owner.name }}</div>
							<div v-if="team.pivot?.created_at" class="text-xs text-neutral-400 mt-1">
								Invited: {{ new Date(team.pivot.created_at).toLocaleDateString() }}
							</div>
						</div>
						<div class="flex items-center space-x-2">
							<Button @click="acceptInvitation(team.id)" variant="success" :disabled="isSubmitting"> Accept </Button>
							<Button @click="declineInvitation(team.id)" variant="destructive" :disabled="isSubmitting"> Decline </Button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
