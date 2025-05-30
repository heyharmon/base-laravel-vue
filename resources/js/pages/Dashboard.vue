<script setup>
import { onMounted, ref, watch, computed } from 'vue'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { useOrganizationStore } from '@/stores/organizationStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'

const jobStatusStore = useJobStatusStore()
const organizationStore = useOrganizationStore()

// Get the most recently completed job
const mostRecentCompletedJobs = computed(() => {
	if (jobStatusStore.jobs.length === 0) return null

	// Filter for completed jobs (status === 'completed')
	const completedJobs = jobStatusStore.jobs.filter((job) => job.status === 'processing')
	if (completedJobs.length === 0) return null

	// return the 3 most recent
	return completedJobs.slice(0, 3)
})

watch(
	() => jobStatusStore.activeJobs,
	(newJobs, oldJobs) => {
		if (oldJobs.length > newJobs.length || newJobs.length === 0) {
			// At least one job completed, or all jobs are done
			organizationStore.fetchVisibilityMetrics()
		}
	},
	{ deep: true }
)

// Computed property for the owned organization
const ownedOrg = computed(() => {
	if (!organizationStore.visibilityMetrics.length) return null
	return organizationStore.visibilityMetrics.find((org) => !org.is_competitor)
})

onMounted(async () => {
	await organizationStore.fetchVisibilityMetrics()
})
</script>

<template>
	<DefaultLayout>
		<!-- Visibility -->
		<div v-if="ownedOrg" class="mt-6 w-2/5 bg-white rounded-lg p-6 border border-neutral-200 shadow-sm">
			<div class="flex items-center justify-between">
				<div class="flex items-center gap-3">
					<img
						:src="`https://cdn.brandfetch.io/${ownedOrg?.website}/w/400/h/400?c=1idaplhOcH8x9kYGESa`"
						:alt="ownedOrg?.name + ' logo'"
						class="size-12 object-contain bg-white rounded-lg border border-neutral-200"
					/>
					<div>
						<h1 class="text-lg font-bold">Visibility score</h1>
						<p class="text-neutral-500">{{ ownedOrg?.name || 'Your Organization' }}</p>
					</div>
				</div>
				<div class="text-6xl font-medium text-green-600 flex items-start gap-1">
					{{ ownedOrg?.visibility || 0 }}
					<span class="text-2xl">%</span>
				</div>
				<!-- <div v-if="organizationStore.isLoadingVisibility" class="animate-spin rounded-full size-5 border-b-2 border-neutral-800"></div> -->
			</div>
		</div>

		<!-- Rankings -->
		<div class="mt-6 bg-white rounded-lg p-6 border border-neutral-200 shadow-sm">
			<div class="flex items-center gap-2 mb-4">
				<h2 class="text-xl font-bold">Rankings</h2>
				<div v-if="organizationStore.isLoadingVisibility" class="animate-spin rounded-full size-4 border-b-2 border-neutral-800"></div>
			</div>

			<div v-if="organizationStore.visibilityMetrics && organizationStore.visibilityMetrics.length">
				<table class="min-w-full divide-y divide-neutral-200">
					<thead>
						<tr>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/10">Org</th>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/3">Visibility</th>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/12"></th>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/12">Mentions</th>
							<th class="px-3 py-2 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider w-1/12">Total responses</th>
						</tr>
					</thead>
					<tbody class="bg-white divide-y divide-neutral-200">
						<tr v-for="org in organizationStore.visibilityMetrics.sort((a, b) => b.visibility - a.visibility)" :key="org.id">
							<td class="px-3 py-2 flex items-center gap-2 whitespace-nowrap font-medium">
								<img
									:src="`https://cdn.brandfetch.io/${org.website}/w/400/h/400?c=1idaplhOcH8x9kYGESa`"
									:alt="org.name + ' logo'"
									class="size-6 object-contain bg-white rounded-md border border-neutral-200"
								/>
								<span>{{ org.name || (org.is_competitor ? 'Unnamed Competitor' : 'Your Organization') }}</span>
								<span v-if="!org.is_competitor" class="bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded-md">You</span>
							</td>
							<td class="pl-3 pr-4 py-2 whitespace-nowrap text-sm">
								<div class="w-full bg-neutral-200 rounded-full h-2 mr-2">
									<div
										class="h-2 rounded-full"
										:class="org.is_competitor ? 'bg-red-500' : 'bg-green-500'"
										:style="{ width: `${org.visibility}%` }"
									></div>
								</div>
							</td>
							<td class="py-2 whitespace-nowrap text-sm flex items-start gap-0.5">{{ org.visibility }}<span class="text-xs">%</span></td>
							<td class="px-3 py-2 whitespace-nowrap text-sm">{{ org.total_mentions }}</td>
							<td class="px-3 py-2 whitespace-nowrap text-sm">{{ org.total_responses }}</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div v-else class="text-center py-4 text-neutral-500 text-sm">No organization data available</div>
		</div>

		<!-- No competitors yet, show a nice large empty state -->
		<!-- <div v-else class="mt-6 bg-white rounded-lg p-6 border border-neutral-200 shadow-sm">
			<div v-if="jobStatusStore.activeJobs.length > 0 && mostRecentCompletedJobs" class="container mx-auto px-4 py-2">
				<div class="flex items-center justify-between">
					<div class="flex items-center space-x-3">
						<div class="relative size-4">
							<svg
								class="text-green-500"
								viewBox="0 0 24 24"
								fill="none"
								stroke="currentColor"
								stroke-width="2"
								stroke-linecap="round"
								stroke-linejoin="round"
							>
								<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
								<polyline points="22 4 12 14.01 9 11.01"></polyline>
							</svg>
						</div>
						<div class="flex flex-col">
							<div class="flex items-center space-x-2">
								<span class="text-xs font-medium">{{ mostRecentCompletedJob.output }}</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div> -->
	</DefaultLayout>
</template>
