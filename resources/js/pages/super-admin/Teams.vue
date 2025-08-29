<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import FullWidthLayout from '@/layouts/FullWidthLayout.vue'

const teams = ref([])
const isLoading = ref(false)
const router = useRouter()

const fetchTeams = async () => {
	isLoading.value = true
	try {
		const response = await api.get('/super-admin/teams')
		teams.value = response
	} catch (error) {
		console.error('Error fetching teams:', error)
	} finally {
		isLoading.value = false
	}
}

const viewTeam = (team) => {
	router.push(`/super-admin/teams/${team.id}`)
}

onMounted(fetchTeams)
</script>

<template>
	<FullWidthLayout>
		<div class="p-6">
			<h1 class="text-2xl font-bold mb-6">Team Usage</h1>
			<div v-if="isLoading" class="py-8 text-center">Loading...</div>
			<div v-else>
				<table class="min-w-full bg-white border border-neutral-200 rounded-lg overflow-hidden">
					<thead>
						<tr class="bg-neutral-50 border-b border-neutral-200">
							<th class="text-left px-4 py-3 text-sm font-medium text-neutral-700">Team</th>
                        <th class="text-left px-4 py-3 text-sm font-medium text-neutral-700">Price Limit</th>
                        <th class="text-left px-4 py-3 text-sm font-medium text-neutral-700">Price Usage</th>
                        <th class="text-left px-4 py-3 text-sm font-medium text-neutral-700">Price Remaining</th>
							<th class="px-4 py-3"></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="team in teams" :key="team.id" class="border-b border-neutral-200 last:border-b-0">
							<td class="px-4 py-3">{{ team.name }}</td>
                        <td class="px-4 py-3">{{ team.limit_price !== null ? '$' + team.limit_price.toFixed(2) : '—' }}</td>
                        <td class="px-4 py-3">${{ (team.usage_price || 0).toFixed(2) }}</td>
                        <td class="px-4 py-3">{{ team.remaining_price !== null ? '$' + team.remaining_price.toFixed(2) : '—' }}</td>
							<td class="px-4 py-3">
								<button class="text-blue-600 underline" @click="viewTeam(team)">Manage limitations</button>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</FullWidthLayout>
</template>
