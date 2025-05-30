<script setup>
import Button from '@/components/ui/Button.vue'

const props = defineProps({
	organization: { type: Object, required: true },
	hasChanges: { type: Boolean, default: false },
	isSubmitting: { type: Boolean, default: false }
})
const emit = defineEmits(['update'])

// US States list
const states = [
	{ code: 'AL', name: 'Alabama' },
	{ code: 'AK', name: 'Alaska' },
	{ code: 'AZ', name: 'Arizona' },
	{ code: 'AR', name: 'Arkansas' },
	{ code: 'CA', name: 'California' },
	{ code: 'CO', name: 'Colorado' },
	{ code: 'CT', name: 'Connecticut' },
	{ code: 'DE', name: 'Delaware' },
	{ code: 'FL', name: 'Florida' },
	{ code: 'GA', name: 'Georgia' },
	{ code: 'HI', name: 'Hawaii' },
	{ code: 'ID', name: 'Idaho' },
	{ code: 'IL', name: 'Illinois' },
	{ code: 'IN', name: 'Indiana' },
	{ code: 'IA', name: 'Iowa' },
	{ code: 'KS', name: 'Kansas' },
	{ code: 'KY', name: 'Kentucky' },
	{ code: 'LA', name: 'Louisiana' },
	{ code: 'ME', name: 'Maine' },
	{ code: 'MD', name: 'Maryland' },
	{ code: 'MA', name: 'Massachusetts' },
	{ code: 'MI', name: 'Michigan' },
	{ code: 'MN', name: 'Minnesota' },
	{ code: 'MS', name: 'Mississippi' },
	{ code: 'MO', name: 'Missouri' },
	{ code: 'MT', name: 'Montana' },
	{ code: 'NE', name: 'Nebraska' },
	{ code: 'NV', name: 'Nevada' },
	{ code: 'NH', name: 'New Hampshire' },
	{ code: 'NJ', name: 'New Jersey' },
	{ code: 'NM', name: 'New Mexico' },
	{ code: 'NY', name: 'New York' },
	{ code: 'NC', name: 'North Carolina' },
	{ code: 'ND', name: 'North Dakota' },
	{ code: 'OH', name: 'Ohio' },
	{ code: 'OK', name: 'Oklahoma' },
	{ code: 'OR', name: 'Oregon' },
	{ code: 'PA', name: 'Pennsylvania' },
	{ code: 'RI', name: 'Rhode Island' },
	{ code: 'SC', name: 'South Carolina' },
	{ code: 'SD', name: 'South Dakota' },
	{ code: 'TN', name: 'Tennessee' },
	{ code: 'TX', name: 'Texas' },
	{ code: 'UT', name: 'Utah' },
	{ code: 'VT', name: 'Vermont' },
	{ code: 'VA', name: 'Virginia' },
	{ code: 'WA', name: 'Washington' },
	{ code: 'WV', name: 'West Virginia' },
	{ code: 'WI', name: 'Wisconsin' },
	{ code: 'WY', name: 'Wyoming' },
	{ code: 'DC', name: 'District of Columbia' }
]
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
			<div>
				<label class="block text-sm font-medium text-neutral-700 mb-1">State</label>
				<select
					v-model="organization.state"
					class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
				>
					<option value="">Select a state (optional)</option>
					<option v-for="state in states" :key="state.code" :value="state.name">{{ state.name }}</option>
				</select>
				<p class="text-xs text-neutral-500 mt-1">State where this organization primarily does business</p>
			</div>
			<div class="pt-4">
				<Button v-if="hasChanges" type="submit" :disabled="isSubmitting" variant="dark">
					{{ isSubmitting ? 'Saving...' : 'Save Changes' }}
				</Button>
			</div>
		</form>
	</div>
</template>
