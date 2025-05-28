<script setup>
import { ref } from 'vue'
import Sheet from '@/components/ui/Sheet.vue'
import JobStatusList from '@/components/jobs/JobStatusList.vue'

const props = defineProps({
	isOpen: {
		type: Boolean,
		required: true
	}
})

const emit = defineEmits(['close'])

const closeSheet = () => {
	emit('close')
}
</script>

<template>
	<Sheet :is-open="isOpen" @close="closeSheet" position="right" title="Runs">
		<div class="w-full xl:w-[800px] md:p-4">
			<JobStatusList :autoRefresh="true" :refreshInterval="1000" />
		</div>
	</Sheet>
</template>
