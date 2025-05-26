<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useOrganizationStore } from '@/stores/organizationStore';
import api from '@/services/api';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';
import Spinner from '@/components/ui/Spinner.vue';
import OrganizationSearch from '@/components/OrganizationSearch.vue';

const router = useRouter();
const organizationStore = useOrganizationStore();
const isSubmitting = ref(false);
const isLoadingDetails = ref(false);
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



// Handle organization selection from search component
const handleOrganizationSelect = (result) => {
  console.log('Selected organization...', result);
  organization.value.name = result.name || '';
  organization.value.website = result.domain || '';
  organization.value.logo = result.icon || '';

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

// Handle create from domain from search component
const handleCreateFromDomain = (domain) => {
  organization.value.website = domain;
  // Extract name from domain (remove TLD and capitalize first letter)
  const domainParts = domain.split('.');
  if (domainParts.length > 1) {
    const name = domainParts[0].charAt(0).toUpperCase() + domainParts[0].slice(1);
    organization.value.name = name;
  }

  // Fetch brand details for the domain
  fetchBrandDetails(organization.value.website);
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
          <OrganizationSearch 
            label="Search for competitor"
            placeholder="Search for a company..."
            @select="handleOrganizationSelect"
            @create-from-domain="handleCreateFromDomain"
          />

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
