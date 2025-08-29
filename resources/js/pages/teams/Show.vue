<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTeamStore } from '@/stores/teamStore'
import { useUsageStore } from '@/stores/usageStore'
import auth from '@/services/auth'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'

const route = useRoute()
const router = useRouter()
const teamStore = useTeamStore()
const usageStore = useUsageStore()
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
const activeDropdown = ref(null)
const usage = computed(() => usageStore.usage)
const usagePercent = computed(() => {
        if (!usage.value || !usage.value.limit_price) return 0
        return Math.min((usage.value.usage_price / usage.value.limit_price) * 100, 100)
})

onMounted(async () => {
        await loadTeam()
        await usageStore.fetchUsage(route.params.teamId)
        // Close dropdown when clicking outside
        document.addEventListener('click', handleClickOutside)
})

watch(
        () => route.params.teamId,
        async () => {
                await loadTeam()
                await usageStore.fetchUsage(route.params.teamId)
        }
)

const handleClickOutside = (event) => {
	if (!event.target.closest('.dropdown-container')) {
		activeDropdown.value = null
	}
}

const toggleDropdown = (id) => {
	activeDropdown.value = activeDropdown.value === id ? null : id
}

const loadTeam = async () => {
	await teamStore.fetchTeam(route.params.teamId)
	if (teamStore.currentTeam) {
		editTeamName.value = teamStore.currentTeam.name
	}
}

const updateTeam = async () => {
	if (!editTeamName.value) return

	isSubmitting.value = true
	try {
		await teamStore.updateTeam(route.params.teamId, { name: editTeamName.value })
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
		await teamStore.inviteUser(route.params.teamId, {
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
		await teamStore.removeMember(route.params.teamId, userId)
		activeDropdown.value = null
	} catch (error) {
		console.error('Error removing member:', error)
	}
}

const updateRole = async (userId, role) => {
	try {
		await teamStore.updateMemberRole(route.params.teamId, userId, { role })
		activeDropdown.value = null
	} catch (error) {
		console.error('Error updating role:', error)
	}
}

const deleteTeam = async () => {
	if (!confirm('Are you sure you want to delete this team? This action cannot be undone.')) return

	try {
		await teamStore.deleteTeam(route.params.teamId)

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
		activeDropdown.value = null
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
		activeDropdown.value = null
		setTimeout(() => {
			delete copiedInviteUrls.value[memberId]
		}, 2000)
	}
}

const generatePasswordResetUrl = async (userId) => {
	try {
		const resetUrl = await teamStore.generatePasswordResetUrl(route.params.teamId, userId)
		// Copy the URL
		await navigator.clipboard.writeText(resetUrl)
		// Set copied state
		copiedResetUrls.value[userId] = true
		activeDropdown.value = null
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
			activeDropdown.value = null
			setTimeout(() => {
				delete copiedResetUrls.value[userId]
			}, 2000)
		} catch (fallbackError) {
			console.error('Fallback copy also failed:', fallbackError)
		}
	}
}

const showChangeRoleModal = ref(false)
const selectedMemberId = ref(null)
const selectedMemberRole = ref('member')

const openChangeRoleModal = (member) => {
	selectedMemberId.value = member.id
	selectedMemberRole.value = member.pivot.role
	showChangeRoleModal.value = true
	activeDropdown.value = null
}

const changeRole = async () => {
	if (!selectedMemberId.value) return

	try {
		await updateRole(selectedMemberId.value, selectedMemberRole.value)
		showChangeRoleModal.value = false
		selectedMemberId.value = null
		selectedMemberRole.value = 'member'
	} catch (error) {
		console.error('Error changing role:', error)
	}
}

const cancelInvitation = async (userId) => {
	if (!confirm('Are you sure you want to cancel this invitation?')) return

	try {
		await teamStore.removeMember(route.params.teamId, userId)
		activeDropdown.value = null
	} catch (error) {
		console.error('Error canceling invitation:', error)
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

                        <!-- Usage information -->
                        <div v-if="usage" class="mb-8">
                                <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-4">
                                        <div class="flex justify-between mb-2 text-sm">
                                                <span>Monthly Usage</span>
                                                <span v-if="usage.limit_price">
                                                        ${{ usage.usage_price.toFixed(2) }} / ${{ usage.limit_price.toFixed(2) }}
                                                </span>
                                                <span v-else>
                                                        ${{ usage.usage_price.toFixed(2) }} / Unlimited
                                                </span>
                                        </div>
                                        <div class="w-full bg-neutral-200 rounded h-2">
                                                <div class="h-2 bg-blue-500 rounded" :style="{ width: usagePercent + '%' }"></div>
                                        </div>
                                </div>
                        </div>

                        <!-- Team Members -->
                        <div class="mb-8">
                                <h2 class="text-xl font-semibold mb-2">Team Members</h2>
                                <p class="text-neutral-600 mb-6">Manage your existing team members and their roles.</p>

					<div class="bg-white rounded-lg shadow-sm border border-neutral-200">
						<table class="w-full">
							<thead>
								<tr class="border-b border-neutral-200">
									<th class="text-left px-6 py-3 text-sm font-medium text-neutral-700">Name</th>
									<th class="text-left px-6 py-3 text-sm font-medium text-neutral-700">Role</th>
									<th class="w-16"></th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="member in teamStore.members" :key="member.id" class="border-b border-neutral-200 last:border-b-0">
									<td class="px-6 py-4">
										<div class="flex items-center">
											<div class="w-10 h-10 bg-neutral-200 rounded-full flex items-center justify-center mr-3">
												<span class="text-neutral-600 font-medium">{{ member.name.charAt(0).toUpperCase() }}</span>
											</div>
											<div>
												<div class="font-medium">{{ member.name }}</div>
												<div class="text-sm text-neutral-500">{{ member.email }}</div>
											</div>
										</div>
									</td>
									<td class="px-6 py-4">
										<span
											v-if="member.id === teamStore.currentTeam.owner_id"
											class="bg-neutral-900 text-white px-3 py-1 rounded-full text-xs font-medium"
										>
											Owner
										</span>
										<span v-else class="text-sm font-medium">
											{{ member.pivot.role === 'admin' ? 'Admin' : 'Member' }}
										</span>
									</td>
									<td class="px-6 py-4">
										<div v-if="isOwner || isAdmin" class="relative dropdown-container">
											<button @click="toggleDropdown(`member-${member.id}`)" class="p-1 hover:bg-neutral-100 rounded cursor-pointer">
												<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
													<path
														stroke-linecap="round"
														stroke-linejoin="round"
														stroke-width="2"
														d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"
													/>
												</svg>
											</button>
											<div
												v-if="activeDropdown === `member-${member.id}`"
												class="absolute right-0 mt-2 w-56 bg-white rounded-md shadow-lg border border-neutral-200 z-10"
											>
												<div class="py-1">
													<h3 class="px-4 py-2 text-sm font-medium text-neutral-900">Actions</h3>
													<button
														@click="generatePasswordResetUrl(member.id)"
														class="w-full text-left px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100 cursor-pointer"
													>
														Copy password reset URL
													</button>
													<button
														v-if="member.id !== currentUser?.id && member.id !== teamStore.currentTeam.owner_id"
														@click="openChangeRoleModal(member)"
														class="w-full text-left px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100 cursor-pointer"
													>
														Change role
													</button>
													<button
														v-if="member.id !== currentUser?.id && member.id !== teamStore.currentTeam.owner_id"
														@click="removeMember(member.id)"
														class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-neutral-100 cursor-pointer"
													>
														Remove member
													</button>
												</div>
											</div>
										</div>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Pending Invitations -->
				<div v-if="teamStore.pendingMembers.length > 0" class="mb-28">
					<h2 class="text-xl font-semibold mb-2">Pending Invitations</h2>
					<p class="text-neutral-600 mb-6">Manage pending invitations for new team members.</p>

					<div class="bg-white rounded-lg shadow-sm border border-neutral-200">
						<table class="w-full">
							<thead>
								<tr class="border-b border-neutral-200">
									<th class="text-left px-6 py-3 text-sm font-medium text-neutral-700">Email</th>
									<th class="text-left px-6 py-3 text-sm font-medium text-neutral-700">Invited</th>
									<th class="text-left px-6 py-3 text-sm font-medium text-neutral-700">Expires</th>
									<th class="text-left px-6 py-3 text-sm font-medium text-neutral-700">Status</th>
									<th class="w-16"></th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="member in teamStore.pendingMembers" :key="member.id" class="border-b border-neutral-200 last:border-b-0">
									<td class="px-6 py-4">
										<div class="font-medium">{{ member.email }}</div>
									</td>
									<td class="px-6 py-4 text-sm text-neutral-600">
										{{ new Date(member.pivot.invitation_sent_at).toLocaleDateString() }}
									</td>
									<td class="px-6 py-4 text-sm text-neutral-600">
										<span v-if="member.token_expired" class="text-red-600">
											{{ new Date(member.token_expires_at).toLocaleDateString() }}
										</span>
										<span v-else>
											{{ member.token_expires_at ? new Date(member.token_expires_at).toLocaleDateString() : '-' }}
										</span>
									</td>
									<td class="px-6 py-4">
										<span v-if="!member.invitation_url" class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium">
											Existing user
										</span>
										<span v-else-if="member.token_expired" class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-medium">
											Expired
										</span>
										<span v-else class="text-sm font-medium"> Pending </span>
									</td>
									<td class="px-6 py-4">
										<div v-if="isOwner || isAdmin" class="relative dropdown-container">
											<button @click="toggleDropdown(`pending-${member.id}`)" class="p-1 hover:bg-neutral-100 rounded cursor-pointer">
												<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
													<path
														stroke-linecap="round"
														stroke-linejoin="round"
														stroke-width="2"
														d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"
													/>
												</svg>
											</button>
											<div
												v-if="activeDropdown === `pending-${member.id}`"
												class="absolute right-0 mt-2 w-56 bg-white rounded-md shadow-lg border border-neutral-200 z-10"
											>
												<div class="py-1">
													<h3 class="px-4 py-2 text-sm font-medium text-neutral-900">Actions</h3>
													<button
														v-if="member.invitation_url"
														@click="copyInviteUrl(member.invitation_url, member.id)"
														class="w-full text-left px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100 cursor-pointer"
													>
														Copy invite URL
													</button>
													<button
														v-else
														@click="generatePasswordResetUrl(member.id)"
														class="w-full text-left px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100 cursor-pointer"
													>
														Copy password reset URL
													</button>
													<button
														@click="cancelInvitation(member.id)"
														class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-neutral-100 cursor-pointer"
													>
														Cancel invitation
													</button>
												</div>
											</div>
										</div>
									</td>
								</tr>
							</tbody>
						</table>
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

			<!-- Change Role Modal -->
			<div v-if="showChangeRoleModal" class="fixed inset-0 bg-neutral-300/50 flex items-center justify-center z-50">
				<div class="bg-white rounded-lg p-6 w-full max-w-md">
					<h2 class="text-xl font-bold mb-4">Change Role</h2>
					<div class="mb-4">
						<label class="block text-sm font-medium text-neutral-700 mb-1">Select Role</label>
						<select
							v-model="selectedMemberRole"
							class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
						>
							<option value="member">Member</option>
							<option value="admin">Admin</option>
						</select>
					</div>
					<div class="flex justify-end space-x-2">
						<Button @click="showChangeRoleModal = false" variant="neutral"> Cancel </Button>
						<Button @click="changeRole" variant="dark"> Change Role </Button>
					</div>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
