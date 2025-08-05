<script setup>
import { ref, onMounted, watch } from 'vue';
import { useOrganizationStore } from '@/stores/organizationStore';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

const organizationStore = useOrganizationStore();
const showCreateModal = ref(false);
const showFilters = ref(false);

onMounted(async () => {
  await organizationStore.fetchOrganizations();
});

watch(
  () => organizationStore.filters,
  () => {
    organizationStore.fetchOrganizations(1);
  },
  { deep: true }
);

const handleSearch = () => {
  organizationStore.fetchOrganizations(1);
};

const handleSort = (column) => {
  if (organizationStore.filters.sort_by === column) {
    organizationStore.setFilters({
      sort_direction: organizationStore.filters.sort_direction === 'asc' ? 'desc' : 'asc'
    });
  } else {
    organizationStore.setFilters({
      sort_by: column,
      sort_direction: 'asc'
    });
  }
};

const getSortIcon = (column) => {
  if (organizationStore.filters.sort_by !== column) return '↕️';
  return organizationStore.filters.sort_direction === 'asc' ? '↑' : '↓';
};

const handlePageChange = (page) => {
  organizationStore.fetchOrganizations(page);
};

const deleteOrganization = async (id) => {
  if (!confirm('Are you sure you want to delete this organization?')) return;
  try {
    await organizationStore.deleteOrganization(id);
  } catch (error) {
    console.error('Error deleting organization:', error);
  }
};
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold">Organizations</h1>
        <div class="flex space-x-2">
          <router-link to="/organizations/import">
            <Button class="bg-green-600 hover:bg-green-700 text-white">
              Import from Google Maps
            </Button>
          </router-link>
          <router-link to="/organizations/create">
            <Button>Create Organization</Button>
          </router-link>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-4 mb-6">
        <div class="flex flex-wrap gap-4 items-center">
          <div class="flex-1 min-w-64">
            <Input
              v-model="organizationStore.filters.search"
              placeholder="Search organizations..."
              @keyup.enter="handleSearch"
            />
          </div>
          
          <Button @click="showFilters = !showFilters" variant="outline">
            {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
          </Button>
          
          <Button @click="organizationStore.resetFilters()" variant="outline">
            Clear Filters
          </Button>
        </div>
        
        <div v-if="showFilters" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">City</label>
            <Input v-model="organizationStore.filters.city" placeholder="Filter by city" />
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1">State</label>
            <Input v-model="organizationStore.filters.state" placeholder="Filter by state" />
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-1">Category</label>
            <Input v-model="organizationStore.filters.category" placeholder="Filter by category" />
          </div>
        </div>
      </div>

      <div v-if="organizationStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
      </div>

      <div v-else-if="organizationStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        {{ organizationStore.error }}
      </div>

      <div v-else class="bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-neutral-50 border-b border-neutral-200">
              <tr>
                <th @click="handleSort('name')" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider cursor-pointer hover:bg-neutral-100">
                  Name {{ getSortIcon('name') }}
                </th>
                <th @click="handleSort('category')" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider cursor-pointer hover:bg-neutral-100">
                  Category {{ getSortIcon('category') }}
                </th>
                <th @click="handleSort('city')" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider cursor-pointer hover:bg-neutral-100">
                  Location {{ getSortIcon('city') }}
                </th>
                <th @click="handleSort('score')" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider cursor-pointer hover:bg-neutral-100">
                  Rating {{ getSortIcon('score') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
              <tr v-for="organization in organizationStore.organizations" :key="organization.id" class="hover:bg-neutral-50">
                <td class="px-6 py-4">
                  <div class="flex items-center">
                    <img v-if="organization.banner" :src="organization.banner" :alt="organization.name" class="h-10 w-10 rounded-full mr-3 object-cover">
                    <div class="h-10 w-10 rounded-full mr-3 bg-neutral-200 flex items-center justify-center" v-else>
                      <span class="text-neutral-500 font-medium">{{ organization.name.charAt(0).toUpperCase() }}</span>
                    </div>
                    <div>
                      <div class="text-sm font-medium text-neutral-900">{{ organization.name }}</div>
                      <div v-if="organization.phone" class="text-sm text-neutral-500">{{ organization.phone }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900">
                  {{ organization.category || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900">
                  <div>{{ organization.city || '-' }}</div>
                  <div class="text-neutral-500">{{ organization.state || '-' }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900">
                  <div v-if="organization.score" class="flex items-center">
                    <span class="text-yellow-400">★</span>
                    <span class="ml-1">{{ organization.score }}</span>
                    <span v-if="organization.reviews" class="text-neutral-500 ml-1">({{ organization.reviews }})</span>
                  </div>
                  <span v-else>-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex space-x-2">
                    <router-link :to="{ name: 'organizations.show', params: { id: organization.id } }" class="text-blue-600 hover:text-blue-900">
                      View
                    </router-link>
                    <router-link :to="{ name: 'organizations.edit', params: { id: organization.id } }" class="text-green-600 hover:text-green-900">
                      Edit
                    </router-link>
                    <button @click="deleteOrganization(organization.id)" class="text-red-600 hover:text-red-900">
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <div v-if="organizationStore.pagination.last_page > 1" class="bg-white px-4 py-3 flex items-center justify-between border-t border-neutral-200">
          <div class="flex-1 flex justify-between sm:hidden">
            <Button 
              @click="handlePageChange(organizationStore.pagination.current_page - 1)"
              :disabled="organizationStore.pagination.current_page === 1"
              variant="outline"
            >
              Previous
            </Button>
            <Button 
              @click="handlePageChange(organizationStore.pagination.current_page + 1)"
              :disabled="organizationStore.pagination.current_page === organizationStore.pagination.last_page"
              variant="outline"
            >
              Next
            </Button>
          </div>
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-neutral-700">
                Showing
                <span class="font-medium">{{ ((organizationStore.pagination.current_page - 1) * organizationStore.pagination.per_page) + 1 }}</span>
                to
                <span class="font-medium">{{ Math.min(organizationStore.pagination.current_page * organizationStore.pagination.per_page, organizationStore.pagination.total) }}</span>
                of
                <span class="font-medium">{{ organizationStore.pagination.total }}</span>
                results
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                <Button 
                  @click="handlePageChange(organizationStore.pagination.current_page - 1)"
                  :disabled="organizationStore.pagination.current_page === 1"
                  variant="outline"
                  size="sm"
                >
                  Previous
                </Button>
                <Button 
                  v-for="page in Math.min(5, organizationStore.pagination.last_page)" 
                  :key="page"
                  @click="handlePageChange(page)"
                  :variant="page === organizationStore.pagination.current_page ? 'default' : 'outline'"
                  size="sm"
                >
                  {{ page }}
                </Button>
                <Button 
                  @click="handlePageChange(organizationStore.pagination.current_page + 1)"
                  :disabled="organizationStore.pagination.current_page === organizationStore.pagination.last_page"
                  variant="outline"
                  size="sm"
                >
                  Next
                </Button>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
