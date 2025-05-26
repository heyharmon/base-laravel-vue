<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useTeamStore } from '@/stores/teamStore';
import api from '@/services/api';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';
import OrganizationSearch from '@/components/OrganizationSearch.vue';

const router = useRouter();
const teamStore = useTeamStore();
const isSubmitting = ref(false);

// Organization data
const organization = ref({
  name: '',
  website: '',
  is_competitor: false,
  founded: null,
  employee_count: null,
  location: '',
  description: '',
  logo: '',
  industry: '',
  hasDetails: false
});

// Handle organization selection from search
const handleOrganizationSelect = (result) => {
  organization.value.name = result.name || '';
  organization.value.website = result.domain || '';
  organization.value.logo = result.icon || '';
};

// Handle create from domain
const handleCreateFromDomain = (domain) => {
  organization.value.website = domain;
  // Extract name from domain (remove TLD and capitalize first letter)
  const domainParts = domain.split('.');
  if (domainParts.length > 1) {
    const name = domainParts[0].charAt(0).toUpperCase() + domainParts[0].slice(1);
    organization.value.name = name;
  }
};

// Create team and organization
const createTeam = async () => {
  if (!organization.value.name) return;

  isSubmitting.value = true;
  try {
    // Create the team first
    const teamResponse = await teamStore.createTeam({ name: organization.value.name });
    
    // If we have organization data, create the organization
    if (organization.value.name && organization.value.website) {
      try {
        // Create the organization for the team
        await api.post('/organizations', {
          name: organization.value.name,
          website: organization.value.website,
          is_competitor: false, // Default organization is not a competitor
          logo: organization.value.logo || null,
          founded: organization.value.founded || null,
          employee_count: organization.value.employee_count || null,
          location: organization.value.location || null,
          description: organization.value.description || null,
          industry: organization.value.industry || null
        });
      } catch (orgError) {
        console.error('Error creating organization:', orgError);
        // Continue even if organization creation fails
      }
    }
    
    // Switch to the newly created team
    await teamStore.switchTeam(teamResponse.id);
    
    // Redirect to the dashboard or team page
    router.push({ name: 'dashboard' });
  } catch (error) {
    console.error('Error creating team:', error);
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8">
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold">Create New Team</h1>
      </div>

      <div class="max-w-2xl mx-auto">
        <div class="space-y-6">


          <!-- Organization Search -->
          <div>
            <h2 class="text-lg font-medium mb-4">Team Organization</h2>
            <p class="text-sm text-neutral-600 mb-4">
              Each team needs an organization. Search for your organization or enter your domain below.
            </p>
            
            <OrganizationSearch 
              label="Search for your organization"
              placeholder="Search for your company or enter your domain..."
              @select="handleOrganizationSelect"
              @create-from-domain="handleCreateFromDomain"
            />
          </div>

          <!-- Organization Preview -->
          <div v-if="organization.name || organization.website" class="mt-4">
            <h3 class="text-sm font-medium text-neutral-700 mb-2">Organization Preview</h3>
            <div class="flex items-center gap-4 p-6 border border-neutral-200 rounded-md bg-neutral-50">
              <img 
                v-if="organization.logo" 
                :src="organization.logo" 
                :alt="organization.name + ' logo'" 
                class="h-16 w-16 object-contain bg-white rounded-md border border-neutral-200"
              />
              <div>
                <h3 class="text-md font-medium">{{ organization.name }}</h3>
                <p class="text-sm text-neutral-500">{{ organization.website }}</p>
              </div>
            </div>
          </div>

          <div class="mt-6 flex justify-end space-x-2">
            <Button
              @click="router.push({ name: 'teams.index' })"
              variant="neutral"
            >
              Cancel
            </Button>
            <Button
              @click="createTeam"
              :disabled="isSubmitting || !organization.name"
              variant="dark"
            >
              {{ isSubmitting ? 'Creating...' : 'Create Team' }}
            </Button>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
