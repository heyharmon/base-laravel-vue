<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import FullWidthLayout from '@/layouts/FullWidthLayout.vue'
import Button from '@/components/ui/Button.vue'

const router = useRouter()
const stats = ref({
	total_organizations: 0,
	owned_organizations: 0,
	competitor_organizations: 0,
	total_teams: 0
})
const isLoading = ref(false)

// Fetch dashboard statistics
const fetchStats = async () => {
	isLoading.value = true
	try {
		const orgStats = await api.get('/super-admin/organizations/stats')
		stats.value = orgStats
	} catch (error) {
		console.error('Error fetching dashboard stats:', error)
	} finally {
		isLoading.value = false
	}
}

const navigateToExport = () => {
	router.push('/super-admin/export')
}

const navigateToTeams = () => {
	router.push('/super-admin/teams')
}

onMounted(fetchStats)
</script>

<template>
	<FullWidthLayout>
		<div class="p-6">
			<!-- Header -->
			<div class="mb-8">
				<h1 class="text-3xl font-bold text-neutral-900 mb-2">Super admin</h1>
				<p class="text-neutral-600">Overview of organizations and team usage across the platform</p>
			</div>

			<div v-if="isLoading" class="flex justify-center py-12">
				<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
			</div>

			<div v-else class="space-y-8">
				<!-- Quick Stats -->
				<section>
					<h2 class="text-xl font-semibold text-neutral-900 mb-4">Platform overview</h2>
					<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
						<div class="bg-white p-6 rounded-lg border border-neutral-200 shadow-sm">
							<div class="text-sm text-neutral-500 mb-1">Total Teams</div>
							<div class="text-3xl font-bold text-neutral-900">{{ stats.total_teams }}</div>
						</div>
						<div class="bg-white p-6 rounded-lg border border-neutral-200 shadow-sm">
							<div class="text-sm text-neutral-500 mb-1">Competitor Organizations</div>
							<div class="text-3xl font-bold text-neutral-900">{{ stats.competitor_organizations }}</div>
						</div>
					</div>
				</section>

				<!-- Quick Actions -->
				<section>
					<h2 class="text-xl font-semibold text-neutral-900 mb-4">Quick actions</h2>
					<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
						<!-- Team Usage Management -->
						<div class="bg-white p-6 rounded-lg border border-neutral-200 shadow-sm">
							<div class="flex items-start justify-between mb-4">
								<div>
									<h3 class="text-lg font-medium text-neutral-900 mb-2">Manage team usage</h3>
									<p class="text-sm text-neutral-600">Manage team spending limits and usage across all teams</p>
								</div>
							</div>
							<Button @click="navigateToTeams" variant="default" class="w-full">Manage team usage</Button>
						</div>

						<!-- Export Management -->
						<div class="bg-white p-6 rounded-lg border border-neutral-200 shadow-sm">
							<div class="flex items-start justify-between mb-4">
								<div>
									<h3 class="text-lg font-medium text-neutral-900 mb-2">Export</h3>
									<p class="text-sm text-neutral-600">Export organization data</p>
								</div>
							</div>
							<Button @click="navigateToExport" variant="default" class="w-full">Export data</Button>
						</div>
					</div>
				</section>
			</div>
		</div>
	</FullWidthLayout>
</template>
