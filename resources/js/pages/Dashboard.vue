<script setup>
import { onMounted, ref, watch, computed } from 'vue'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import { useOrganizationStore } from '@/stores/organizationStore'
import DefaultLayout from '@/layouts/DefaultLayout.vue'

const jobStatusStore = useJobStatusStore()
const organizationStore = useOrganizationStore()

const processingJobsByClass = computed(() => jobStatusStore.processingJobsByClass)

// Watch for job status changes
watch(
	() => jobStatusStore.jobs,
	() => {
		// Check if there are any newly completed jobs
		const hasCompletedJobs = jobStatusStore.completedJobs.length > 0
		if (hasCompletedJobs) {
			console.log('Jobs completed, refreshing visibility metrics')
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
		<!-- Jobs currently processing message -->
		<div v-if="Object.keys(processingJobsByClass).length > 0" class="p-4 my-6 bg-green-50 border border-green-200 text-green-800 rounded-lg">
			<div class="flex items-center gap-4 mb-2">
				<span class="animate-spin h-4 w-4 border-t-2 border-b-2 border-green-700 rounded-full"></span>
				<span class="font-semibold">Setting up your team</span>
			</div>
			<div class="pl-8 space-y-1">
				<div v-for="(jobs, jobClass) in processingJobsByClass" :key="jobClass">
					<div class="flex items-center justify-between">
						<span>{{ jobs[0].output }}</span>
					</div>
					<div v-if="jobs.length > 1" class="flex items-center justify-between">
						<span>{{ jobs[1].output }}</span>
					</div>
					<div v-if="jobs.length > 2" class="flex items-center justify-between">
						<span>{{ jobs[2].output }}</span>
					</div>
				</div>
			</div>
		</div>

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
	</DefaultLayout>
</template>
