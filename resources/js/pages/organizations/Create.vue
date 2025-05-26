<script setup>
import { ref, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useOrganizationStore } from '@/stores/organizationStore';
import api from '@/services/api';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';
import Spinner from '@/components/ui/Spinner.vue';

const router = useRouter();
const organizationStore = useOrganizationStore();
const isSubmitting = ref(false);
const isSearching = ref(false);
const isLoadingDetails = ref(false);
const searchQuery = ref('');
const searchResults = ref([]);
const searchTimeout = ref(null);
const organization = ref({
  name: '',
  website: '',
  is_competitor: true,
  founded: null,
  employee_count: null,
  location: '',
  description: '',
  logo: '',
  industry: '',
  hasDetails: false
});

// Check if the search query is a valid domain
const isDomain = computed(() => {
  const domainRegex = /^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/;
  return domainRegex.test(searchQuery.value);
});

// Search for organizations when the search query changes
watch(searchQuery, (newQuery) => {
  if (searchTimeout.value) clearTimeout(searchTimeout.value);

  if (!newQuery || newQuery.length < 2) {
    searchResults.value = [];
    return;
  }

  searchTimeout.value = setTimeout(async () => {
    isSearching.value = true;
    try {
      const response = await api.get('/organization-search', {
        params: { query: newQuery }
      });
      searchResults.value = response.results || [];
    } catch (error) {
      console.error('Error searching organizations:', error);
      searchResults.value = [];
    } finally {
      isSearching.value = false;
    }
  }, 300);
});

// Select an organization from search results
const selectOrganization = (result) => {
  console.log('Selected organization...', result);
  organization.value.name = result.name || '';
  organization.value.website = result.domain || '';
  organization.value.logo = result.icon || '';
  searchQuery.value = '';
  searchResults.value = [];

  // Fetch brand details for the selected organization
//   if (result.domain) {
//     fetchBrandDetails(result.domain);
//   }
};

// Fetch brand details from the API
const fetchBrandDetails = async (domain) => {
  if (!domain) return;

  isLoadingDetails.value = true;
  // Reset details fields
  organization.value.description = '';
  organization.value.logo = '';
  organization.value.industry = '';
  organization.value.hasDetails = false;

  try {
    const response = await api.get('/brand-details', {
      params: { identifier: domain }
    });
    const details = response.details;

    // Update organization with additional details if available
    if (details) {
      if (details.name) {
        organization.value.name = details.name;
      }
      if (details.description) {
        organization.value.description = details.description;
      }
      if (details.company?.foundedYear) {
        organization.value.founded = details.company.foundedYear;
      }
      if (details.company?.employees) {
        organization.value.employee_count = details.company.employees;
      }

      // Add location information if available
      if (details.company?.location) {
        const location = details.company.location;
        const locationParts = [location.city, location.state, location.country].filter(Boolean);
        if (locationParts.length > 0) {
          organization.value.location = locationParts.join(', ');
        }
      }

      // Add logo if available
      if (details.logos && details.logos.length > 0 && details.logos[0]?.formats?.length > 0) {
        organization.value.logo = details.logos[0].formats[0].src;
      }

      // Add industry if available
      if (details.company?.industries && details.company.industries.length > 0) {
        organization.value.industry = details.company.industries[0].name;
      }

      organization.value.hasDetails = true;
    }
  } catch (error) {
    console.error('Error fetching brand details:', error);
  } finally {
    isLoadingDetails.value = false;
  }
};

// Create organization from domain
const createFromDomain = () => {
  if (isDomain.value) {
    organization.value.website = searchQuery.value;
    // Extract name from domain (remove TLD and capitalize first letter)
    const domainParts = searchQuery.value.split('.');
    if (domainParts.length > 1) {
      const name = domainParts[0].charAt(0).toUpperCase() + domainParts[0].slice(1);
      organization.value.name = name;
    }
    searchQuery.value = '';
    searchResults.value = [];

    // Fetch brand details for the domain
    fetchBrandDetails(organization.value.website);
  }
};

const createOrganization = async () => {
  if (!organization.value.name || !organization.value.website) return;

  isSubmitting.value = true;
  try {
    let response = await organizationStore.createOrganization(organization.value);
    console.log(response);
    router.push({ name: 'organizations.index' });
  } catch (error) {
    console.error('Error creating organization:', error);
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8">
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold">Add Competitor</h1>
      </div>

      <div class="max-w-2xl mx-auto">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Search for competitor</label>
            <div class="relative">
              <input
                v-model="searchQuery"
                type="text"
                class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Search for a company..."
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

                <!-- Empty state with domain detection -->
                <li
                  v-if="searchResults.length === 0"
                  @click="createFromDomain"
                  @keydown.enter="createFromDomain"
                  class="px-3 py-2 hover:bg-neutral-100 cursor-pointer border-b border-neutral-200 last:border-b-0"
                >
                  <div class="flex items-center justify-between">
                    <div>
                      <div class="font-medium text-neutral-700">No organization found</div>
                      <div v-if="isDomain" class="text-sm text-neutral-500">
                        Create new competitor from "{{ searchQuery }}"
                      </div>
                      <div v-else class="text-sm text-neutral-500">
                        Try searching with a domain name
                      </div>
                    </div>
                    <div v-if="isDomain" class="flex items-center gap-2 border px-2 rounded text-sm text-neutral-500">
					  <span class="pt-1">↵</span> Press enter
                    </div>
                  </div>
                </li>
              </ul>
            </div>
          </div>

          <!-- Organization Preview Card -->
          <div v-if="organization.website">
            <div v-if="isLoadingDetails" class="flex justify-center py-8">
              <Spinner class="h-8 w-8" />
            </div>

            <div v-else-if="organization.hasDetails" class="p-6 mt-4 border rounded-md">
              <div class="flex items-start gap-6">
                <!-- Logo -->
                <div v-if="organization.logo" class="flex-shrink-0">
                  <img
                    :src="organization.logo"
                    :alt="organization.name + ' logo'"
                    class="h-16 w-16 object-contain bg-white rounded-md border border-neutral-200"
                  />
                </div>

                <!-- Organization Details -->
                <div class="flex-grow">
                  <h3 class="text-xl font-semibold text-neutral-900">{{ organization.name }}</h3>
                  <p class="text-sm text-neutral-500 mt-1">{{ organization.website }}</p>

                  <p v-if="organization.description" class="mt-3 text-neutral-700">
                    {{ organization.description }}
                  </p>

                  <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div v-if="organization.founded" class="flex items-center gap-2">
                      <span class="font-medium">Founded:</span> {{ organization.founded }}
                    </div>

                    <div v-if="organization.industry" class="flex items-center gap-2">
                      <span class="font-medium">Industry:</span> {{ organization.industry }}
                    </div>

                    <div v-if="organization.location" class="flex items-center gap-2 col-span-2">
                      <span class="font-medium">Location:</span> {{ organization.location }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="mt-4">
              <div class="flex items-center gap-4 p-6 border border-neutral-200 rounded-md bg-neutral-50">
				<img v-if="organization.logo" :src="organization.logo" :alt="organization.name + ' logo'" class="h-16 w-16 object-contain bg-white rounded-md border border-neutral-200"/>
                <div>
					<h3 class="text-md font-medium">{{ organization.name }}</h3>
                	<p class="text-sm text-neutral-500">{{ organization.website }}</p>
				</div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-6 flex justify-end space-x-2">
          <Button
            @click="router.push({ name: 'organizations.index' })"
            variant="neutral"
          >
            Cancel
          </Button>
          <Button
            @click="createOrganization"
            :disabled="isSubmitting || !organization.name || !organization.website"
            variant="dark"
          >
            {{ isSubmitting ? 'Creating...' : 'Add competitor' }}
          </Button>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
