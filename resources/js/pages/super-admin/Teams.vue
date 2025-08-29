<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import FullWidthLayout from '@/layouts/FullWidthLayout.vue'
import UsageProgress from '@/components/UsageProgress.vue'

const teams = ref([])
const router = useRouter()

onMounted(async () => {
  teams.value = await api.get('/super-admin/teams')
})

const viewTeam = (id) => {
  router.push({ name: 'super-admin.teams.show', params: { teamId: id } })
}
</script>

<template>
  <FullWidthLayout>
    <h1 class="text-2xl font-bold mb-6">Teams</h1>
    <div v-for="team in teams" :key="team.id" class="mb-6 p-4 border rounded cursor-pointer" @click="viewTeam(team.id)">
      <div class="font-medium mb-2">{{ team.name }}</div>
      <UsageProgress :used="team.responses_used" :limit="team.responses_limit" label="Responses" />
      <UsageProgress :used="team.articles_used" :limit="team.articles_limit" label="Articles" />
    </div>
  </FullWidthLayout>
</template>
