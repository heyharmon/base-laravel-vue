<script setup>
import { ref, watch, computed } from 'vue'
import api from '@/services/api'
import Spinner from '@/components/ui/Spinner.vue'

const props = defineProps({
	label: {
		type: String,
		default: 'Search for organization'
	},
	placeholder: {
		type: String,
		default: 'Enter the website domain'
	}
})

const emit = defineEmits(['select-organization'])

const isSearching = ref(false)
const searchQuery = ref('')
const searchResults = ref([])
const searchTimeout = ref(null)

// Check if the search query is a valid domain
const isDomain = computed(() => {
	const domainRegex = /^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/
	return domainRegex.test(searchQuery.value)
})

// Search for organizations when the search query changes
watch(searchQuery, (newQuery) => {
	if (searchTimeout.value) clearTimeout(searchTimeout.value)

	if (!newQuery || newQuery.length < 2) {
		searchResults.value = []
		return
	}

	searchTimeout.value = setTimeout(async () => {
		isSearching.value = true
		try {
			const response = await api.get('/organization-search', {
				params: { query: newQuery }
			})
			searchResults.value = response.results || []
		} catch (error) {
			console.error('Error searching organizations:', error)
			searchResults.value = []
		} finally {
			isSearching.value = false
		}
	}, 300)
})

// Select an organization from search results
const selectOrganization = (result) => {
	// Create a standardized organization object
	const organization = {
		name: result.name || '',
		website: result.domain || '',
		logo: '',
		is_competitor: true,
		founded: null,
		employee_count: null,
		location: '',
		description: '',
		industry: '',
		hasDetails: false
	}

	emit('select-organization', organization)
	searchQuery.value = ''
	searchResults.value = []
}

// Create organization from domain
const createFromDomain = () => {
	if (isDomain.value) {
		const domain = searchQuery.value

		// Create a standardized organization object
		const organization = {
			website: domain,
			is_competitor: true,
			founded: null,
			employee_count: null,
			location: '',
			description: '',
			logo: '',
			industry: '',
			hasDetails: false
		}

		// Extract name from domain (remove TLD and capitalize first letter)
		const domainParts = domain.split('.')
		if (domainParts.length > 1) {
			const name = domainParts[0].charAt(0).toUpperCase() + domainParts[0].slice(1)
			organization.name = name
		} else {
			organization.name = domain
		}

		emit('select-organization', organization)
		searchQuery.value = ''
		searchResults.value = []
	}
}
</script>

<template>
	<div>
		<label class="block text-sm font-medium text-neutral-700 mb-1">{{ label }}</label>
		<div class="relative">
			<input
				v-model="searchQuery"
				type="text"
				class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
				:placeholder="placeholder"
				@keydown.enter="isDomain ? createFromDomain() : null"
			/>
			<div v-if="isSearching" class="absolute right-3 top-2">
				<Spinner class="h-5 w-5" />
			</div>
		</div>

		<!-- Search results -->
		<div v-if="searchQuery.length >= 2 && !isSearching" class="mt-1 bg-white border border-neutral-300 rounded-md shadow-sm max-h-60 overflow-y-auto">
			<ul>
				<li
					v-if="isDomain"
					@click="createFromDomain"
					@keydown.enter="createFromDomain"
					class="px-3 py-2 bg-neutral-100 hover:bg-neutral-200/60 cursor-pointer border-b border-neutral-200 last:border-t-0"
				>
					<div class="flex items-center justify-between">
						<div>
							<div class="font-medium text-neutral-700">Create new competitor</div>
							<div class="text-sm text-neutral-500">Create from "{{ searchQuery }}"</div>
						</div>
						<div class="flex items-center gap-2 bg-white hover:bg-neutral-100/50 border border-neutral-900 px-2 rounded text-sm text-neutral-900">
							<span class="pt-1">↵</span> Press enter
						</div>
					</div>
				</li>

				<li
					v-for="result in searchResults"
					:key="result.domain"
					@click="selectOrganization(result)"
					class="px-3 py-2 hover:bg-neutral-100 cursor-pointer border-b border-neutral-200 last:border-b-0"
				>
					<div class="flex items-center">
						<div v-if="result.icon" class="mr-2">
							<img :src="result.icon" alt="Logo" class="h-5 w-5 object-contain" />
						</div>
						<div>
							<div class="font-medium">{{ result.name }}</div>
							<div class="text-sm text-neutral-500">{{ result.domain }}</div>
						</div>
					</div>
				</li>

				<!-- Empty state when no results -->
				<li v-if="searchResults.length === 0 && !isDomain" class="px-3 py-2 border-b border-neutral-200 last:border-b-0">
					<div class="flex items-center justify-between">
						<div>
							<div class="font-medium text-neutral-700">No organization found</div>
							<div class="text-sm text-neutral-500">Try searching with a domain name</div>
						</div>
					</div>
				</li>
			</ul>
		</div>
	</div>
</template>
