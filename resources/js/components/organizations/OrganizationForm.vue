<script setup>
import { ref, computed, onMounted } from 'vue'
import { useOrganizationStore } from '@/stores/organizationStore'
import Button from '@/components/ui/Button.vue'

const props = defineProps({
	organization: { type: Object, required: true },
	hasChanges: { type: Boolean, default: false },
	isSubmitting: { type: Boolean, default: false }
})
const emit = defineEmits(['update'])

const organizationStore = useOrganizationStore()
const showAddIndustry = ref(false)
const newIndustryName = ref('')
const isCreatingIndustry = ref(false)

onMounted(async () => {
	await organizationStore.fetchIndustries()
})

const selectedIndustry = computed({
	get() {
		return props.organization.industry_id || ''
	},
	set(value) {
		props.organization.industry_id = value
	}
})

const addNewIndustry = async () => {
	if (!newIndustryName.value.trim()) return
	
	isCreatingIndustry.value = true
	try {
		const industry = await organizationStore.createIndustry({ name: newIndustryName.value.trim() })
		selectedIndustry.value = industry.id
		newIndustryName.value = ''
		showAddIndustry.value = false
	} catch (error) {
		console.error('Error creating industry:', error)
	} finally {
		isCreatingIndustry.value = false
	}
}

const cancelAddIndustry = () => {
	newIndustryName.value = ''
	showAddIndustry.value = false
}

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
				<label class="block text-sm font-medium text-neutral-700 mb-1">Location</label>
				<input
					v-model="organization.location"
					type="text"
					placeholder="Enter location (optional)"
					class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
				/>
				<p class="text-xs text-neutral-500 mt-1">Location where this organization primarily does business</p>
			</div>
			<div>
				<label class="block text-sm font-medium text-neutral-700 mb-1">Industry</label>
				<div v-if="!showAddIndustry" class="space-y-2">
					<select
						v-model="selectedIndustry"
						class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
					>
						<option value="">Select an industry (optional)</option>
						<option v-for="industry in organizationStore.industries" :key="industry.id" :value="industry.id">
							{{ industry.name }}
						</option>
					</select>
					<button
						@click="showAddIndustry = true"
						type="button"
						class="text-sm text-blue-600 hover:text-blue-800 font-medium"
					>
						+ Add new industry
					</button>
				</div>
				<div v-else class="space-y-2">
					<input
						v-model="newIndustryName"
						type="text"
						placeholder="Enter new industry name"
						class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
						@keyup.enter="addNewIndustry"
						@keyup.escape="cancelAddIndustry"
					/>
					<div class="flex gap-2">
						<button
							@click="addNewIndustry"
							type="button"
							:disabled="isCreatingIndustry || !newIndustryName.trim()"
							class="text-sm bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
						>
							{{ isCreatingIndustry ? 'Adding...' : 'Add' }}
						</button>
						<button
							@click="cancelAddIndustry"
							type="button"
							class="text-sm text-neutral-600 hover:text-neutral-800 px-3 py-1"
						>
							Cancel
						</button>
					</div>
				</div>
				<p class="text-xs text-neutral-500 mt-1">Specify the industry this organization operates in</p>
			</div>
			<div>
				<label class="block text-sm font-medium text-neutral-700 mb-1">Description</label>
				<textarea
					v-model="organization.description"
					rows="3"
					placeholder="Enter organization description (optional)"
					class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
				></textarea>
				<p class="text-xs text-neutral-500 mt-1">Provide a brief description of this organization</p>
			</div>
			<div class="pt-4">
				<Button v-if="hasChanges" type="submit" :disabled="isSubmitting" variant="dark">
					{{ isSubmitting ? 'Saving...' : 'Save Changes' }}
				</Button>
			</div>
		</form>
	</div>
</template>
