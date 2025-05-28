<script setup>
import { onMounted } from 'vue'
import { useJobStatusStore } from '@/stores/jobStatusStore'

const jobStatusStore = useJobStatusStore()

onMounted(async () => {
	await jobStatusStore.pollTeamJobs()
})
</script>

<template>
	<div class="min-h-screen bg-neutral-100">
		<router-view />
	</div>
</template>
