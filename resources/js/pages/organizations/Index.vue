<script setup>
import { onMounted, computed, watch } from 'vue'
import { useOrganizationStore } from '@/stores/organizationStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { useRouter, useRoute } from 'vue-router'
import { useCampaignStore } from '@/stores/campaignStore'
import moment from 'moment'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'
import CampaignSwitcher from '@/components/CampaignSwitcher.vue'

const organizationStore = useOrganizationStore()
const jobStatusStore = useJobStatusStore()
const campaignStore = useCampaignStore()
const router = useRouter()
const route = useRoute()
const teamId = computed(() => route.params.teamId)
const campaignId = computed(() => route.params.campaignId)

// Get active jobs related to competitors
const activeCompetitorJobs = computed(() => {
	return jobStatusStore.jobs.filter(
		(job) => job.job_class.includes('FindCompetitorsInResponseJob') && (job.status === 'pending' || job.status === 'processing')
	)
})

// Watch for competitor job completions
watch(
	() => jobStatusStore.jobs,
	(newJobs, oldJobs) => {
		// Check if any competitor job has just completed
		const completedCompetitorJob = newJobs.some(
			(job) =>
				job.job_class.includes('FindCompetitorsInResponseJob') &&
				job.status === 'completed' &&
				oldJobs.find((oldJob) => oldJob.job_id === job.job_id)?.status !== 'completed'
		)

		if (completedCompetitorJob) {
			console.log('Competitor job completed, refreshing organizations')
			organizationStore.fetchOrganizations(teamId.value, campaignId.value)
		}
	},
	{ deep: true }
)

// Check if organization was created within the last 24 hours
const isNewOrganization = (createdAt) => {
	if (!createdAt) return false
	return moment().diff(moment(createdAt), 'hours') <= 24
}

onMounted(async () => {
	await campaignStore.fetchCampaigns(teamId.value)
	if (campaignId.value) {
		await campaignStore.switchCampaign(teamId.value, campaignId.value)
	}
	await organizationStore.fetchOrganizations(teamId.value, campaignId.value)
	await jobStatusStore.pollTeamJobs(teamId.value)
})

watch(campaignId, async (newId) => {
	if (newId) {
		await campaignStore.switchCampaign(teamId.value, newId)
		await organizationStore.fetchOrganizations(teamId.value, newId)
	}
})
</script>

<template>
	<DefaultLayout>
		<!-- Header -->
		<div class="flex justify-between items-center mb-3 py-6">
			<div class="flex items-center gap-4">
				<h1 class="text-2xl font-bold">Organizations</h1>
			</div>
			<CampaignSwitcher />
		</div>

		<!-- Active jobs message -->
		<div v-if="activeCompetitorJobs.length > 0" class="p-4 mt-4 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center gap-2">
			<span class="animate-spin h-4 w-4 mr-2 border-t-2 border-b-2 border-green-700 rounded-full"></span>
			<span>
				Looking for new competitors in {{ activeCompetitorJobs.length }} prompt {{ activeCompetitorJobs.length === 1 ? 'response' : 'responses' }}.
			</span>
		</div>

		<!-- Loading state -->
		<div v-if="organizationStore.isLoading" class="flex justify-center py-8">
			<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
		</div>

		<div v-else>
			<!-- Your Organization -->
			<div class="mt-6 mb-6">
				<h2 class="text-xl font-semibold mb-4">Your organization</h2>
				<div v-if="organizationStore.ownedOrganizations.length === 0" class="text-neutral-500">You don't have an organization yet.</div>
				<div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
					<router-link
						v-for="org in organizationStore.ownedOrganizations"
						:key="org.id"
						:to="{ name: 'organizations.edit', params: { teamId: teamId, campaignId: campaignId, id: org.id } }"
						class="bg-white border border-neutral-200 p-4 rounded-lg shadow cursor-pointer hover:bg-neutral-50 transition-all"
					>
						<div class="flex justify-between items-start">
							<h3 class="text-lg font-medium">{{ org.name || 'Unnamed Organization' }}</h3>
							<img
								v-if="org.website"
								:src="`https://cdn.brandfetch.io/${org.website}/w/400/h/400?c=1idaplhOcH8x9kYGESa`"
								:alt="org.name + ' logo'"
								class="h-10 w-10 object-contain bg-white rounded-md border border-neutral-200"
							/>
						</div>
						<div class="text-sm text-neutral-600">
							<div v-if="org.website">{{ org.website }}</div>
							<div v-if="org.founded">Founded: {{ org.founded }}</div>
							<div v-if="org.employee_count">Employees: {{ org.employee_count }}</div>
							<div class="mt-1 flex items-center">
								<span class="font-medium text-neutral-700">{{ org.terms_count }}</span>
								<span class="ml-1 text-neutral-500">{{ org.terms_count === 1 ? 'term' : 'terms' }}</span>
							</div>
						</div>
						<div class="mt-4 flex space-x-2">
							<button class="text-blue-600 hover:text-blue-800 text-sm font-medium cursor-pointer">Edit</button>
						</div>
					</router-link>
				</div>
			</div>

			<!-- Competitor Organizations -->
			<div class="mb-8">
				<div class="flex items-center justify-between mb-4">
					<h2 class="text-xl font-semibold">Competitors</h2>
					<div class="flex space-x-2">
						<Button
							v-if="organizationStore.ownedOrganizations.length > 0"
							@click="organizationStore.findCompetitors(teamId)"
							:disabled="activeCompetitorJobs.length > 0"
							variant="outline"
							size="sm"
						>
							{{ activeCompetitorJobs.length > 0 ? 'Finding competitors...' : 'Find competitors' }}
						</Button>
						<Button
							@click="router.push({ name: 'organizations.create', params: { teamId: teamId, campaignId: campaignId } })"
							variant="outline"
							size="sm"
						>
							{{ organizationStore.ownedOrganizations.length === 0 ? 'Add your organization' : 'Add competitor' }}
						</Button>
					</div>
				</div>
				<div v-if="organizationStore.competitorOrganizations.length === 0" class="text-neutral-500">You haven't added any competitors yet.</div>
				<div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
					<div
						v-for="org in organizationStore.competitorOrganizations"
						:key="org.id"
						@click="router.push({ name: 'organizations.edit', params: { teamId: teamId, campaignId: campaignId, id: org.id } })"
						class="bg-white border border-neutral-200 p-4 rounded-lg shadow cursor-pointer hover:bg-neutral-50 transition-all"
					>
						<div class="flex justify-between items-start">
							<div>
								<div v-if="isNewOrganization(org.created_at)" class="mb-1">
									<span class="inline-block px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">
										Added {{ moment(org.created_at).fromNow() }}
									</span>
								</div>
								<p class="text-lg font-medium hover:underline">
									{{ org.name || 'Unnamed Competitor' }}
								</p>
							</div>
							<img
								v-if="org.website"
								:src="`https://cdn.brandfetch.io/${org.website}/w/400/h/400?c=1idaplhOcH8x9kYGESa`"
								:alt="org.name + ' logo'"
								class="h-10 w-10 object-contain bg-white rounded-md border border-neutral-200"
							/>
						</div>
						<div class="text-sm text-neutral-600">
							<div v-if="org.website">{{ org.website }}</div>
							<div v-if="org.founded">Founded: {{ org.founded }}</div>
							<div v-if="org.employee_count">Employees: {{ org.employee_count }}</div>
							<div class="mt-1 flex items-center">
								<span class="font-medium text-neutral-700">{{ org.terms_count }}</span>
								<span class="ml-1 text-neutral-500">{{ org.terms_count === 1 ? 'term' : 'terms' }}</span>
							</div>
						</div>
						<div class="flex space-x-2 mt-4">
							<router-link
								:to="{ name: 'organizations.edit', params: { teamId: teamId, campaignId: campaignId, id: org.id } }"
								class="text-blue-600 hover:text-blue-800 text-sm font-medium"
							>
								Edit
							</router-link>
							<button
								@click.stop="organizationStore.deleteOrganization(teamId, org.id, campaignId)"
								class="text-red-600 hover:text-red-800 text-sm font-medium cursor-pointer"
							>
								Remove
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>
