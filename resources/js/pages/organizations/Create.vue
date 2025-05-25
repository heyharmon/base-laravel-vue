<script setup>
import { ref, watch } from 'vue'
import api from '@/services/api'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'

const search = ref('')
const results = ref([])
const name = ref('')
const website = ref('')

watch(search, async (term) => {
  if (!term || term.length < 2) {
    results.value = []
    return
  }

  try {
    results.value = await api.get('/companies/search', { params: { term } })
  } catch (e) {
    results.value = []
  }
})

function selectCompany(company) {
  name.value = company.name
  website.value = company.website
  search.value = company.name
  results.value = []
}
</script>

<template>
  <DefaultLayout>
    <div class="max-w-xl mx-auto py-8 space-y-6">
      <h1 class="text-2xl font-bold">Create Organization</h1>

      <div class="relative">
        <Input v-model="search" placeholder="Search company" class="w-full" />
        <ul v-if="results.length" class="absolute z-10 mt-1 w-full bg-white border border-neutral-200 rounded-md shadow max-h-60 overflow-y-auto">
          <li
            v-for="company in results"
            :key="company.id"
            @click="selectCompany(company)"
            class="flex items-center gap-2 px-3 py-2 hover:bg-neutral-100 cursor-pointer"
          >
            <img v-if="company.logo" :src="company.logo" alt="" class="w-6 h-6 object-contain" />
            <span class="truncate">{{ company.name }}</span>
          </li>
        </ul>
      </div>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Name</label>
          <Input v-model="name" class="w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Website</label>
          <Input v-model="website" class="w-full" />
        </div>
        <Button>Save</Button>
      </div>
    </div>
  </DefaultLayout>
</template>

