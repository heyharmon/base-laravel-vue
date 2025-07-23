<script setup>
import Button from '@/components/ui/Button.vue'

const props = defineProps({
	organization: { type: Object, required: true },
	hasChanges: { type: Boolean, default: false },
	isSubmitting: { type: Boolean, default: false }
})
const emit = defineEmits(['update'])
</script>

<template>
	<div class="w-full md:w-1/3">
		<h2 class="text-xl font-semibold mb-2">Edit {{ organization.is_competitor ? 'competitor' : 'your organization' }}</h2>
		<form @submit.prevent="$emit('update')" class="space-y-3">
			<div>
				<label class="block text-sm font-medium text-neutral-700 mb-1">Name</label>
				<input
					v-model="organization.name"
					type="text"
					class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
					placeholder="Enter organization name"
				/>
			</div>
			<div>
				<label class="block text-sm font-medium text-neutral-700 mb-1">Website</label>
				<input
					v-model="organization.website"
					type="text"
					class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
					placeholder="Enter website URL"
				/>
			</div>

			<div class="pt-4">
				<Button v-if="hasChanges" type="submit" :disabled="isSubmitting" variant="dark">
					{{ isSubmitting ? 'Saving...' : 'Save Changes' }}
				</Button>
			</div>
		</form>
	</div>
</template>
