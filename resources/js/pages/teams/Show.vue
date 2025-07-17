<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTeamStore } from '@/stores/teamStore'
import auth from '@/services/auth'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'

const route = useRoute()
const router = useRouter()
const teamStore = useTeamStore()
const currentUser = computed(() => auth.getUser())
const isOwner = computed(() => teamStore.currentTeam?.owner_id === currentUser.value?.id)
const isAdmin = computed(() => {
	if (!teamStore.members || !currentUser.value) return false
	return teamStore.members.some((member) => member.id === currentUser.value.id && member.pivot.role === 'admin')
})
const showEditModal = ref(false)
const showInviteModal = ref(false)
const editTeamName = ref('')
const inviteEmail = ref('')
const inviteRole = ref('member')
const isSubmitting = ref(false)
const copiedResetUrls = ref({})
const copiedInviteUrls = ref({})

onMounted(async () => {
	await loadTeam()
})

const loadTeam = async () => {
	await teamStore.fetchTeam(route.params.id)
	if (teamStore.currentTeam) {
		editTeamName.value = teamStore.currentTeam.name
	}
}

const updateTeam = async () => {
	if (!editTeamName.value) return

	isSubmitting.value = true
	try {
		await teamStore.updateTeam(route.params.id, { name: editTeamName.value })
		showEditModal.value = false
	} catch (error) {
		console.error('Error updating team:', error)
	} finally {
		isSubmitting.value = false
	}
}

const inviteUser = async () => {
	if (!inviteEmail.value) return

	isSubmitting.value = true
	try {
		await teamStore.inviteUser(route.params.id, {
			email: inviteEmail.value,
			role: inviteRole.value
		})
		inviteEmail.value = ''
		inviteRole.value = 'member'
		showInviteModal.value = false
	} catch (error) {
		console.error('Error inviting user:', error)
	} finally {
		isSubmitting.value = false
	}
}

const removeMember = async (userId) => {
	if (!confirm('Are you sure you want to remove this member?')) return

	try {
		await teamStore.removeMember(route.params.id, userId)
	} catch (error) {
		console.error('Error removing member:', error)
	}
}

const updateRole = async (userId, role) => {
	try {
		await teamStore.updateMemberRole(route.params.id, userId, { role })
	} catch (error) {
		console.error('Error updating role:', error)
	}
}

const deleteTeam = async () => {
	if (!confirm('Are you sure you want to delete this team? This action cannot be undone.')) return

	try {
		await teamStore.deleteTeam(route.params.id)

		// Find another team to switch to
		if (teamStore.ownedTeams.length > 0) {
			await teamStore.switchTeam(teamStore.ownedTeams[0].id)
			router.push(`/teams/${teamStore.ownedTeams[0].id}`)
		} else if (teamStore.joinedTeams.length > 0) {
			await teamStore.switchTeam(teamStore.joinedTeams[0].id)
			router.push(`/teams/${teamStore.joinedTeams[0].id}`)
		} else {
			// No teams left, redirect to teams index
			router.push('/')
		}
	} catch (error) {
		console.error('Error deleting team:', error)
	}
}

const copyInviteUrl = async (url, memberId) => {
	try {
		await navigator.clipboard.writeText(url)
		// Set copied state
		copiedInviteUrls.value[memberId] = true
		// Reset after 2 seconds
		setTimeout(() => {
			delete copiedInviteUrls.value[memberId]
		}, 2000)
		console.log('Invite URL copied to clipboard')
	} catch (error) {
		console.error('Failed to copy URL:', error)
		// Fallback for older browsers
		const textArea = document.createElement('textarea')
		textArea.value = url
		document.body.appendChild(textArea)
		textArea.select()
		document.execCommand('copy')
		document.body.removeChild(textArea)
		// Set copied state even for fallback
		copiedInviteUrls.value[memberId] = true
		setTimeout(() => {
			delete copiedInviteUrls.value[memberId]
		}, 2000)
	}
}

const generatePasswordResetUrl = async (userId) => {
	try {
		const resetUrl = await teamStore.generatePasswordResetUrl(route.params.id, userId)
		// Copy the URL
		await navigator.clipboard.writeText(resetUrl)
		// Set copied state
		copiedResetUrls.value[userId] = true
		// Reset after 2 seconds
		setTimeout(() => {
			delete copiedResetUrls.value[userId]
		}, 2000)
		console.log('Password reset URL copied to clipboard')
	} catch (error) {
		console.error('Error generating password reset URL:', error)
		// Try fallback if clipboard fails
		try {
			const textArea = document.createElement('textarea')
			textArea.value = resetUrl
			document.body.appendChild(textArea)
			textArea.select()
			document.execCommand('copy')
			document.body.removeChild(textArea)
			// Set copied state even for fallback
			copiedResetUrls.value[userId] = true
			setTimeout(() => {
				delete copiedResetUrls.value[userId]
			}, 2000)
		} catch (fallbackError) {
			console.error('Fallback copy also failed:', fallbackError)
		}
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

			<div v-else-if="teamStore.currentTeam">
				<div class="flex justify-between items-center mb-8">
					<div>
						<h1 class="text-2xl font-bold">{{ teamStore.currentTeam.name }}</h1>
						<p class="text-neutral-600 mt-1">Owner: {{ teamStore.currentTeam.owner?.name || 'Unknown' }}</p>
					</div>
					<div class="flex space-x-2">
						<Button v-if="isOwner || isAdmin" @click="showEditModal = true" variant="neutral"> Edit Team </Button>
						<Button v-if="isOwner || isAdmin" @click="showInviteModal = true" variant="dark"> Invite Member </Button>
						<Button v-if="isOwner" @click="deleteTeam" variant="destructive">Delete Team</Button>
					</div>
				</div>

				<!-- Team Members -->
				<div class="bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden">
					<div class="px-6 py-4 bg-neutral-100 border-b border-neutral-200">
						<h2 class="text-lg font-semibold">Team Members ({{ teamStore.members.length }})</h2>
					</div>
					<div class="divide-y divide-neutral-200">
						<div v-for="member in teamStore.members" :key="member.id" class="px-6 py-4 flex items-center justify-between">
							<div>
								<div class="font-medium">{{ member.name }}</div>
								<div class="text-sm text-neutral-500">{{ member.email }}</div>
							</div>
							<div class="flex items-center space-x-4">
								<div class="text-sm">
									<span
										v-if="member.id === teamStore.currentTeam.owner_id"
										class="bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs"
									>
										Owner
									</span>
									<span v-else class="bg-neutral-100 text-neutral-800 px-2 py-1 rounded-full text-xs">
										{{ member.pivot.role === 'admin' ? 'Admin' : 'Member' }}
									</span>
								</div>
								<div v-if="isOwner || isAdmin" class="flex space-x-2">
									<button @click="generatePasswordResetUrl(member.id)" class="text-neutral-800 hover:text-neutral-600 text-sm cursor-pointer">
										{{ copiedResetUrls[member.id] ? 'Copied' : 'Copy password reset URL' }}
									</button>
									<div v-if="member.id !== teamStore.currentTeam.owner_id" class="flex space-x-2">
										<select
											v-if="member.id !== $route.meta?.user?.id"
											:value="member.pivot.role"
											@change="updateRole(member.id, $event.target.value)"
											class="text-sm border border-neutral-300 rounded px-2 py-1 cursor-pointer"
										>
											<option value="member">Member</option>
											<option value="admin">Admin</option>
										</select>
										<button
											v-if="member.id !== $route.meta?.user?.id"
											@click="removeMember(member.id)"
											class="text-red-600 hover:text-red-800 text-sm cursor-pointer"
										>
											Remove
										</button>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Pending Invitations -->
				<div v-if="teamStore.pendingMembers.length > 0" class="mt-8 bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden">
					<div class="px-6 py-4 bg-neutral-100 border-b border-neutral-200">
						<h2 class="text-lg font-semibold">Pending Invitations ({{ teamStore.pendingMembers.length }})</h2>
					</div>
					<div class="divide-y divide-neutral-200">
						<div v-for="member in teamStore.pendingMembers" :key="member.id" class="px-6 py-4 flex items-center justify-between">
							<div>
								<div class="font-medium">{{ member.name }}</div>
								<div class="text-sm text-neutral-500">{{ member.email }}</div>
								<div class="text-xs text-neutral-400 mt-1">Invited: {{ new Date(member.pivot.invitation_sent_at).toLocaleDateString() }}</div>
								<div v-if="member.token_expires_at" class="text-xs text-neutral-400 mt-1">
									<span v-if="member.token_expired" class="text-red-600"
										>Token expired: {{ new Date(member.token_expires_at).toLocaleDateString() }}</span
									>
									<span v-else>Token expires: {{ new Date(member.token_expires_at).toLocaleDateString() }}</span>
								</div>
							</div>
							<div class="flex items-center space-x-4">
								<span v-if="!member.invitation_url" class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">Existing user</span>
								<span v-if="member.token_expired" class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs">Expired</span>
								<div v-if="isOwner || isAdmin" class="flex space-x-2">
									<button
										v-if="member.invitation_url"
										@click="copyInviteUrl(member.invitation_url, member.id)"
										class="text-blue-600 hover:text-blue-800 text-sm cursor-pointer"
									>
										{{ copiedInviteUrls[member.id] ? 'Copied' : 'Copy invite URL' }}
									</button>
									<button
										v-else
										@click="generatePasswordResetUrl(member.id)"
										class="text-neutral-800 hover:text-neutral-600 text-sm cursor-pointer"
									>
										{{ copiedResetUrls[member.id] ? 'Copied' : 'Copy password reset URL' }}
									</button>
									<button @click="removeMember(member.id)" class="text-red-600 hover:text-red-800 text-sm cursor-pointer">
										Cancel Invitation
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Edit Team Modal -->
			<div v-if="showEditModal" class="fixed inset-0 bg-neutral-300/50 flex items-center justify-center z-50">
				<div class="bg-white rounded-lg p-6 w-full max-w-md">
					<h2 class="text-xl font-bold mb-4">Edit Team</h2>
					<div class="mb-4">
						<label class="block text-sm font-medium text-neutral-700 mb-1">Team Name</label>
						<input
							v-model="editTeamName"
							type="text"
							class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
							placeholder="Enter team name"
						/>
					</div>
					<div class="flex justify-end space-x-2">
						<Button @click="showEditModal = false" variant="neutral"> Cancel </Button>
						<Button @click="updateTeam" :disabled="isSubmitting || !editTeamName" variant="dark">
							{{ isSubmitting ? 'Saving...' : 'Save Changes' }}
						</Button>
					</div>
				</div>
			</div>

			<!-- Invite Member Modal -->
			<div v-if="showInviteModal" class="fixed inset-0 bg-neutral-300/50 flex items-center justify-center z-50">
				<div class="bg-white rounded-lg p-6 w-full max-w-md">
					<h2 class="text-xl font-bold mb-4">Invite Team Member</h2>
					<div class="mb-4">
						<label class="block text-sm font-medium text-neutral-700 mb-1">Email Address</label>
						<input
							v-model="inviteEmail"
							type="email"
							class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
							placeholder="Enter email address"
						/>
					</div>
					<div class="mb-4">
						<label class="block text-sm font-medium text-neutral-700 mb-1">Role</label>
						<select
							v-model="inviteRole"
							class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
						>
							<option value="member">Member</option>
							<option value="admin">Admin</option>
						</select>
					</div>
					<div class="flex justify-end space-x-2">
						<Button @click="showInviteModal = false" variant="neutral"> Cancel </Button>
						<Button @click="inviteUser" :disabled="isSubmitting || !inviteEmail" variant="dark">
							{{ isSubmitting ? 'Sending Invitation...' : 'Send Invitation' }}
						</Button>
					</div>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
