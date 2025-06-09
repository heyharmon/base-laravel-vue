<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTeamStore } from '@/stores/teamStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'

const router = useRouter()
const teamStore = useTeamStore()

onMounted(async () => {
	await teamStore.fetchTeams()
})

const acceptInvitation = async (teamId) => {
	try {
		await teamStore.acceptInvitation(teamId)
	} catch (error) {
		console.error('Error accepting invitation:', error)
	}
}

const declineInvitation = async (teamId) => {
	try {
		await teamStore.declineInvitation(teamId)
	} catch (error) {
		console.error('Error declining invitation:', error)
	}
}
</script>

<template>
	<DefaultLayout>
		<div class="container mx-auto py-8">
			<!-- Loading state -->
			<div v-if="teamStore.isLoading" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
			</div>

			<!-- Error state -->
			<div v-else-if="teamStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
				{{ teamStore.error }}
			</div>

			<div v-else>
				<!-- Pending invitations -->
				<div>
					<h1 class="text-2xl font-bold mb-3">Pending invitations</h1>
					<div v-if="teamStore.pendingInvitations.length === 0" class="text-neutral-500">You don't have any pending invitations.</div>
					<div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
						<div
							v-for="team in teamStore.pendingInvitations"
							:key="team.id"
							class="bg-neutral-100 p-4 rounded-lg shadow border-l-4 border-blue-500"
						>
							<h3 class="text-lg font-medium">{{ team.name }}</h3>
							<div class="mt-4 flex space-x-2">
								<Button @click="acceptInvitation(team.id)" variant="success"> Accept </Button>
								<Button @click="declineInvitation(team.id)" variant="muted"> Decline </Button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
