<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTeamStore } from '@/stores/teamStore'
import { useCampaignStore } from '@/stores/campaignStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { PopoverRoot, PopoverTrigger, PopoverContent, PopoverPortal, PopoverClose } from 'reka-ui'
import auth from '@/services/auth'
import JobStatusSheet from '@/components/jobs/JobStatusSheet.vue'
import SpinnerIcon from '@/components/icons/SpinnerIcon.vue'
import ChevronDownIcon from '@/components/icons/ChevronDownIcon.vue'

const router = useRouter()
const teamStore = useTeamStore()
const campaignStore = useCampaignStore()
const jobStatusStore = useJobStatusStore()
const isAuthenticated = computed(() => auth.isAuthenticated())
const user = computed(() => auth.getUser())
const isSuperAdmin = computed(() => user.value?.is_super_admin)

// Use computed properties to directly reference store values
const teams = computed(() => ({
	ownedTeams: teamStore.ownedTeams,
	joinedTeams: teamStore.joinedTeams,
	pendingInvitations: teamStore.pendingInvitations
}))

const currentTeam = computed(() => teamStore.currentTeam)
const campaigns = computed(() => campaignStore.campaigns)
const currentCampaign = computed(() => campaignStore.currentCampaign)

// Explicitly set popover to closed by default
const isTeamDropdownOpen = ref(false)
const isJobStatusSheetOpen = ref(false)
const searchQuery = ref('')
const campaignSearchQuery = ref('')

// Filtered teams based on search query
const filteredTeams = computed(() => {
	if (!teams.value?.joinedTeams?.length) return []
	if (!searchQuery.value.trim()) return teams.value.joinedTeams

	const query = searchQuery.value.toLowerCase().trim()
	return teams.value.joinedTeams.filter((team) => team.name.toLowerCase().includes(query))
})

// Filtered campaigns based on search query
const filteredCampaigns = computed(() => {
	if (!campaigns.value?.length) return []
	if (!campaignSearchQuery.value.trim()) return campaigns.value

	const query = campaignSearchQuery.value.toLowerCase().trim()
	return campaigns.value.filter((campaign) => campaign.name.toLowerCase().includes(query))
})

const logout = async () => {
	await auth.logout()
	router.push('/login')
}

const switchTeam = async (teamId) => {
	try {
		await teamStore.switchTeam(teamId)
		window.location.href = '/'
	} catch (error) {
		console.error('Error switching team:', error)
	}
}

const switchCampaign = async (campaignId) => {
	try {
		await campaignStore.switchCampaign(campaignId)
		// Refresh the page to reload data for the new campaign
		window.location.reload()
	} catch (error) {
		console.error('Error switching campaign:', error)
	}
}

onMounted(async () => {
	jobStatusStore.pollTeamJobs()
	campaignStore.initializeCampaign()
	isTeamDropdownOpen.value = false
})
</script>

<template>
	<nav class="bg-neutral-900 text-white">
		<div class="mx-auto px-6 py-3 flex items-center justify-between">
			<div class="flex items-center space-x-4">
				<router-link to="/" class="text-xl font-bold">Paraloom</router-link>
				<div v-if="isAuthenticated" class="flex items-center space-x-4 ml-6">
					<router-link to="/" class="text-sm hover:text-neutral-300">Rankings</router-link>
					<router-link to="/prompts" class="text-sm hover:text-neutral-300">Prompts</router-link>
					<router-link to="/organizations" class="text-sm hover:text-neutral-300">Organizations</router-link>
					<router-link to="/articles" class="text-sm hover:text-neutral-300">Articles</router-link>
				</div>
			</div>

			<div class="flex items-center gap-4">
				<template v-if="isAuthenticated">
					<!-- Jobs status button -->
					<button
						v-if="jobStatusStore.activeJobs.length > 0"
						@click="isJobStatusSheetOpen = true"
						class="flex items-center space-x-2 cursor-pointer -mr-1 px-2 py-1 rounded hover:bg-neutral-800"
					>
						<div class="relative size-5">
							<SpinnerIcon class="absolute inset-0" />
							<div class="absolute inset-0 flex items-center justify-center">
								<span class="text-xs font-medium">{{ jobStatusStore.activeJobs.length }}</span>
							</div>
						</div>
						<span class="text-sm">Runs</span>
					</button>

					<router-link v-if="isSuperAdmin" to="/super-admin/organizations" class="text-sm hover:text-neutral-300">Super Admin</router-link>

					<!-- Team settings link -->
					<router-link v-if="currentTeam" :to="`/teams/${currentTeam.id}`" class="text-sm hover:text-neutral-300"> Team settings </router-link>

					<!-- Teams dropdown -->
					<PopoverRoot>
						<PopoverTrigger as-child>
							<div class="flex items-center space-x-2 cursor-pointer px-3 py-1 rounded bg-neutral-800 hover:bg-neutral-700">
								<span class="text-sm font-medium">{{ currentTeam?.name || 'Select Team' }}</span>
								<ChevronDownIcon />
							</div>
						</PopoverTrigger>
						<PopoverPortal>
							<PopoverContent
								class="w-56 p-0 bg-neutral-800 rounded shadow-lg overflow-hidden border border-neutral-700 z-50"
								side="bottom"
								align="end"
								:side-offset="5"
							>
								<div class="p-2">
									<p class="text-xs font-medium text-neutral-300 mb-2">Your teams</p>
									<div class="mb-2">
										<input
											v-model="searchQuery"
											type="text"
											placeholder="Search teams..."
											class="w-full px-2 py-1 text-sm text-white bg-neutral-700 border border-neutral-600 rounded focus:outline-none focus:ring-1 focus:ring-neutral-500"
										/>
									</div>
									<div v-if="teams" class="space-y-1">
										<div v-if="filteredTeams.length > 0" class="space-y-1.5 max-h-[calc(100vh-250px)] overflow-y-auto">
											<PopoverClose as-child v-for="team in filteredTeams" :key="team.id">
												<div
													@click="switchTeam(team.id)"
													class="flex items-center px-2 py-1 rounded cursor-pointer hover:bg-neutral-700"
													:class="{ 'bg-neutral-700': currentTeam?.id === team.id }"
												>
													<span class="text-sm text-white">{{ team.name }}</span>
													<span v-if="currentTeam?.id === team.id" class="ml-auto text-xs text-neutral-400">Current</span>
												</div>
											</PopoverClose>
										</div>
										<div
											v-if="teams.joinedTeams && teams.joinedTeams.length > 0 && filteredTeams.length === 0"
											class="text-sm text-neutral-400 py-1"
										>
											No teams match your search
										</div>
									</div>
									<div v-else class="text-sm text-neutral-400 py-1">Loading teams...</div>
								</div>
								<div class="border-t border-neutral-700 mt-1">
									<PopoverClose as-child>
										<router-link to="/teams/create" class="block px-3 py-2 text-sm text-white hover:bg-neutral-700">
											Create team
										</router-link>
									</PopoverClose>
									<PopoverClose as-child>
										<router-link
											to="/invitations"
											class="flex items-center justify-between px-3 py-2 text-sm text-white hover:bg-neutral-700"
										>
											<span>Team invitations</span>
											<span
												v-if="teams?.pendingInvitations?.length"
												class="flex items-center justify-center bg-red-700 text-xs font-medium rounded-full h-5 min-w-5 px-1.5"
											>
												{{ teams.pendingInvitations.length }}
											</span>
										</router-link>
									</PopoverClose>
									<PopoverClose as-child>
										<a @click="logout" class="cursor-pointer w-full text-left block px-3 py-2 text-sm text-white hover:bg-neutral-700">
											Logout
										</a>
									</PopoverClose>
								</div>
							</PopoverContent>
						</PopoverPortal>
					</PopoverRoot>

					<!-- Campaigns dropdown -->
					<PopoverRoot v-if="currentTeam">
						<PopoverTrigger as-child>
							<div class="flex items-center space-x-2 cursor-pointer px-3 py-1 rounded bg-neutral-700 hover:bg-neutral-600">
								<span class="text-sm font-medium">{{ currentCampaign?.name || 'Select Campaign' }}</span>
								<ChevronDownIcon />
							</div>
						</PopoverTrigger>
						<PopoverPortal>
							<PopoverContent
								class="w-56 p-0 bg-neutral-800 rounded shadow-lg overflow-hidden border border-neutral-700 z-50"
								side="bottom"
								align="end"
								:side-offset="5"
							>
								<div class="p-2">
									<p class="text-xs font-medium text-neutral-300 mb-2">Your campaigns</p>
									<div class="mb-2">
										<input
											v-model="campaignSearchQuery"
											type="text"
											placeholder="Search campaigns..."
											class="w-full px-2 py-1 text-sm text-white bg-neutral-700 border border-neutral-600 rounded focus:outline-none focus:ring-1 focus:ring-neutral-500"
										/>
									</div>
									<div v-if="campaigns" class="space-y-1">
										<div v-if="filteredCampaigns.length > 0" class="space-y-1.5 max-h-[calc(100vh-250px)] overflow-y-auto">
											<PopoverClose as-child v-for="campaign in filteredCampaigns" :key="campaign.id">
												<div
													@click="switchCampaign(campaign.id)"
													class="flex items-center px-2 py-1 rounded cursor-pointer hover:bg-neutral-700"
													:class="{ 'bg-neutral-700': currentCampaign?.id === campaign.id }"
												>
													<span class="text-sm text-white">{{ campaign.name }}</span>
													<span v-if="campaign.is_default" class="ml-auto text-xs text-neutral-400">Default</span>
													<span v-else-if="currentCampaign?.id === campaign.id" class="ml-auto text-xs text-neutral-400"
														>Current</span
													>
												</div>
											</PopoverClose>
										</div>
										<div v-if="campaigns.length > 0 && filteredCampaigns.length === 0" class="text-sm text-neutral-400 py-1">
											No campaigns match your search
										</div>
									</div>
									<div v-else class="text-sm text-neutral-400 py-1">Loading campaigns...</div>
								</div>
								<div class="border-t border-neutral-700 mt-1">
									<PopoverClose as-child>
										<router-link to="/campaigns/create" class="block px-3 py-2 text-sm text-white hover:bg-neutral-700">
											Create campaign
										</router-link>
									</PopoverClose>
									<PopoverClose as-child v-if="currentCampaign">
										<router-link
											:to="`/campaigns/${currentCampaign.id}/edit`"
											class="block px-3 py-2 text-sm text-white hover:bg-neutral-700"
										>
											Edit campaign
										</router-link>
									</PopoverClose>
								</div>
							</PopoverContent>
						</PopoverPortal>
					</PopoverRoot>
				</template>
				<template v-else>
					<router-link to="/login" class="px-3 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-sm"> Login </router-link>
					<router-link to="/register" class="px-3 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-sm"> Register </router-link>
				</template>
			</div>
		</div>
	</nav>

	<JobStatusSheet v-if="teams?.ownedTeams.length" :is-open="isJobStatusSheetOpen" @close="isJobStatusSheetOpen = false" />
</template>
