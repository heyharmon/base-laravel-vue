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
const searchQuery = ref('');
const searchResults = ref([]);
const searchTimeout = ref(null);
const organization = ref({
  name: '',
  website: '',
  is_competitor: true
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
  organization.value.name = result.name || '';
  organization.value.website = result.domain || '';
  searchQuery.value = '';
  searchResults.value = [];
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
  }
};

const createOrganization = async () => {
  if (!organization.value.name || !organization.value.website) return;

  isSubmitting.value = true;
  try {
    await organizationStore.createOrganization(organization.value);
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
          <!-- Competitor name and website fields removed, but properties still exist in the organization ref -->
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
