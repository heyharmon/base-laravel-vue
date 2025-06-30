<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import api from '@/services/api'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import OrganizationLogo from '@/components/organizations/OrganizationLogo.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

// State
const organizations = ref([])
const industries = ref([])
const isLoading = ref(false)
const selectedOrganizations = ref(new Set())
const stats = ref({
  total_organizations: 0,
  owned_organizations: 0,
  competitor_organizations: 0,
  total_teams: 0
})

// Filters
const filters = ref({
  industry_id: '',
  is_competitor: 'all',
  search: '',
  sort_by: 'name',
  sort_order: 'asc'
})

// Pagination
const pagination = ref({
  current_page: 1,
  per_page: 50,
  total: 0,
  last_page: 1
})

// Computed
const isAllSelected = computed(() => {
  return (
    organizations.value.length > 0 &&
    organizations.value.every(org => selectedOrganizations.value.has(org.id))
  )
})

const selectedCount = computed(() => selectedOrganizations.value.size)

// Fetch organizations with current filters
const fetchOrganizations = async () => {
  isLoading.value = true
  try {
    const params = {
      ...filters.value,
      page: pagination.value.current_page,
      per_page: pagination.value.per_page
    }

    // Remove empty values
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === 'all') {
        delete params[key]
      }
    })

    const response = await api.get('/super-admin/organizations', { params })
    organizations.value = response.data
    pagination.value = {
      current_page: response.meta.current_page,
      per_page: response.meta.per_page,
      total: response.meta.total,
      last_page: response.meta.last_page
    }
  } catch (error) {
    console.error('Error fetching organizations:', error)
  } finally {
    isLoading.value = false
  }
}

// Fetch industries for filter
const fetchIndustries = async () => {
  try {
    const response = await api.get('/organization-industries')
    industries.value = response
  } catch (error) {
    console.error('Error fetching industries:', error)
  }
}

// Fetch statistics
const fetchStats = async () => {
  try {
    const response = await api.get('/super-admin/organizations/stats')
    stats.value = response
  } catch (error) {
    console.error('Error fetching stats:', error)
  }
}

// Debounced search
const debouncedSearch = useDebounceFn(() => {
  pagination.value.current_page = 1
  fetchOrganizations()
}, 300)

// Watchers
watch(
  () => filters.value.search,
  () => {
    debouncedSearch()
  }
)

watch([
  () => filters.value.industry_id,
  () => filters.value.is_competitor
], () => {
  pagination.value.current_page = 1
  fetchOrganizations()
})

// Methods
const sort = field => {
  if (filters.value.sort_by === field) {
    filters.value.sort_order =
      filters.value.sort_order === 'asc' ? 'desc' : 'asc'
  } else {
    filters.value.sort_by = field
    filters.value.sort_order = 'asc'
  }
  fetchOrganizations()
}

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedOrganizations.value.clear()
  } else {
    organizations.value.forEach(org => {
      selectedOrganizations.value.add(org.id)
    })
  }
  // Force reactivity
  selectedOrganizations.value = new Set(selectedOrganizations.value)
}

const toggleSelect = orgId => {
  if (selectedOrganizations.value.has(orgId)) {
    selectedOrganizations.value.delete(orgId)
  } else {
    selectedOrganizations.value.add(orgId)
  }
  // Force reactivity
  selectedOrganizations.value = new Set(selectedOrganizations.value)
}

const changePage = page => {
  pagination.value.current_page = page
  fetchOrganizations()
}

const getSortIcon = field => {
  if (filters.value.sort_by !== field) {
    return '↕️'
  }
  return filters.value.sort_order === 'asc' ? '↑' : '↓'
}

// Lifecycle
onMounted(async () => {
  await Promise.all([
    fetchOrganizations(),
    fetchIndustries(),
    fetchStats()
  ])
})
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold mb-4">Super Admin - Organizations</h1>

        <!-- Stats Cards -->
        <div class="grid grid-cols-4 gap-4 mb-6">
          <div class="bg-white p-4 rounded-lg border border-neutral-200">
            <div class="text-sm text-neutral-500">Total Organizations</div>
            <div class="text-2xl font-bold">{{ stats.total_organizations }}</div>
          </div>
          <div class="bg-white p-4 rounded-lg border border-neutral-200">
            <div class="text-sm text-neutral-500">Owned</div>
            <div class="text-2xl font-bold text-green-600">{{ stats.owned_organizations }}</div>
          </div>
          <div class="bg-white p-4 rounded-lg border border-neutral-200">
            <div class="text-sm text-neutral-500">Competitors</div>
            <div class="text-2xl font-bold text-red-600">{{ stats.competitor_organizations }}</div>
          </div>
          <div class="bg-white p-4 rounded-lg border border-neutral-200">
            <div class="text-sm text-neutral-500">Total Teams</div>
            <div class="text-2xl font-bold text-blue-600">{{ stats.total_teams }}</div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white p-4 rounded-lg border border-neutral-200 mb-6">
        <div class="grid grid-cols-4 gap-4">
          <!-- Search -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Search by name</label>
            <Input v-model="filters.search" type="text" placeholder="Search organizations..." class="w-full" />
          </div>

          <!-- Industry Filter -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Industry</label>
            <select
              v-model="filters.industry_id"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Industries</option>
              <option v-for="industry in industries" :key="industry.id" :value="industry.id">
                {{ industry.name }}
              </option>
            </select>
          </div>

          <!-- Type Filter -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Type</label>
            <select
              v-model="filters.is_competitor"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="all">All</option>
              <option value="owned">Owned</option>
              <option value="competitor">Competitor</option>
            </select>
          </div>

          <!-- Selected Count -->
          <div class="flex items-end">
            <div v-if="selectedCount > 0" class="bg-blue-50 text-blue-700 px-3 py-2 rounded-md text-sm">
              {{ selectedCount }} selected
            </div>
          </div>
        </div>
      </div>

      <!-- Data Table -->
      <div class="bg-white rounded-lg border border-neutral-200 overflow-hidden">
        <!-- Loading State -->
        <div v-if="isLoading" class="flex justify-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
        </div>

        <!-- Table -->
        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
              <tr>
                <th class="px-4 py-3 text-left">
                  <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" class="rounded border-neutral-300" />
                </th>
                <th class="px-4 py-3 text-left">
                  <span class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Logo</span>
                </th>
                <th class="px-4 py-3 text-left">
                  <button @click="sort('name')" class="text-xs font-medium text-neutral-500 uppercase tracking-wider hover:text-neutral-700 flex items-center gap-1">
                    Name {{ getSortIcon('name') }}
                  </button>
                </th>
                <th class="px-4 py-3 text-left">
                  <button @click="sort('team_name')" class="text-xs font-medium text-neutral-500 uppercase tracking-wider hover:text-neutral-700 flex items-center gap-1">
                    Team {{ getSortIcon('team_name') }}
                  </button>
                </th>
                <th class="px-4 py-3 text-left">
                  <button @click="sort('visibility')" class="text-xs font-medium text-neutral-500 uppercase tracking-wider hover:text-neutral-700 flex items-center gap-1">
                    Visibility {{ getSortIcon('visibility') }}
                  </button>
                </th>
                <th class="px-4 py-3 text-left">
                  <button @click="sort('industry_name')" class="text-xs font-medium text-neutral-500 uppercase tracking-wider hover:text-neutral-700 flex items-center gap-1">
                    Industry {{ getSortIcon('industry_name') }}
                  </button>
                </th>
                <th class="px-4 py-3 text-left">
                  <button @click="sort('website')" class="text-xs font-medium text-neutral-500 uppercase tracking-wider hover:text-neutral-700 flex items-center gap-1">
                    Website {{ getSortIcon('website') }}
                  </button>
                </th>
                <th class="px-4 py-3 text-left">
                  <span class="text-xs font-medium text-neutral-500 uppercase tracking-wider">Type</span>
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
              <tr
                v-for="org in organizations"
                :key="org.id"
                class="hover:bg-neutral-50 transition-colors"
                :class="{ 'bg-blue-50': selectedOrganizations.has(org.id) }"
              >
                <td class="px-4 py-3">
                  <input type="checkbox" :checked="selectedOrganizations.has(org.id)" @change="toggleSelect(org.id)" class="rounded border-neutral-300" />
                </td>
                <td class="px-4 py-3">
                  <OrganizationLogo :organization="org" size="sm" />
                </td>
                <td class="px-4 py-3">
                  <div class="font-medium text-neutral-900">{{ org.name || 'Unnamed' }}</div>
                </td>
                <td class="px-4 py-3 text-sm text-neutral-600">
                  {{ org.team_name || '-' }}
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <div class="w-20 bg-neutral-200 rounded-full h-2">
                      <div
                        class="h-2 rounded-full"
                        :class="org.is_competitor ? 'bg-red-500' : 'bg-green-500'"
                        :style="{ width: `${org.visibility}%` }"
                      ></div>
                    </div>
                    <span class="text-sm font-medium">{{ org.visibility }}%</span>
                  </div>
                </td>
                <td class="px-4 py-3 text-sm text-neutral-600">
                  {{ org.industry_name || '-' }}
                </td>
                <td class="px-4 py-3 text-sm text-neutral-600">
                  <a
                    v-if="org.website"
                    :href="org.website.startsWith('http') ? org.website : `https://${org.website}`"
                    target="_blank"
                    class="text-blue-600 hover:underline"
                  >
                    {{ org.website }}
                  </a>
                  <span v-else>-</span>
                </td>
                <td class="px-4 py-3">
                  <span
                    class="px-2 py-1 text-xs font-medium rounded-full"
                    :class="org.is_competitor ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'"
                  >
                    {{ org.is_competitor ? 'Competitor' : 'Owned' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="px-4 py-3 bg-neutral-50 border-t border-neutral-200">
          <div class="flex items-center justify-between">
            <div class="text-sm text-neutral-700">
              Showing {{ (pagination.current_page - 1) * pagination.per_page + 1 }} to
              {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of
              {{ pagination.total }} results
            </div>
            <div class="flex gap-2">
              <Button @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1" variant="outline" size="sm">
                Previous
              </Button>

              <template v-for="page in Math.min(5, pagination.last_page)" :key="page">
                <Button
                  v-if="page === 1 || page === pagination.last_page || Math.abs(page - pagination.current_page) <= 1"
                  @click="changePage(page)"
                  :variant="page === pagination.current_page ? 'dark' : 'outline'"
                  size="sm"
                >
                  {{ page }}
                </Button>
              </template>

              <Button @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" variant="outline" size="sm">
                Next
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>

