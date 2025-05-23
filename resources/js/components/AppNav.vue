<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import auth from '@/services/auth';
import { useTeamStore } from '@/stores/teamStore';
import { useJobStatusStore } from '@/stores/jobStatusStore';
import { PopoverRoot, PopoverTrigger, PopoverContent, PopoverPortal, PopoverClose } from 'reka-ui';
import GeneratePromptsModal from '@/components/GeneratePromptsModal.vue';
import JobStatusSheet from '@/components/jobs/JobStatusSheet.vue';

const router = useRouter();
const teamStore = useTeamStore();
const jobStatusStore = useJobStatusStore();
const isAuthenticated = computed(() => auth.isAuthenticated());
const user = computed(() => auth.getUser());
const teams = ref(null);
const currentTeam = ref(null);
// Explicitly set popover to closed by default
const isTeamDropdownOpen = ref(false);
const isGenerateModalOpen = ref(false);
const isJobStatusSheetOpen = ref(false);

// Computed property to count pending or processing jobs
const activeJobsCount = computed(() => {
  return jobStatusStore.jobs?.filter(job =>
    job.status === 'pending' || job.status === 'processing'
  )?.length || 0;
});

const logout = async () => {
  await auth.logout();
  router.push('/login');
};

const loadTeams = async () => {
  if (isAuthenticated.value) {
    try {
      await teamStore.fetchTeams();
      teams.value = {
        ownedTeams: teamStore.ownedTeams,
        joinedTeams: teamStore.joinedTeams,
        pendingInvitations: teamStore.pendingInvitations
      };
      updateCurrentTeam();
    } catch (error) {
      console.error('Error loading teams:', error);
    }
  }
};

const updateCurrentTeam = () => {
  if (teams.value && user.value) {
    currentTeam.value = teamStore.getCurrentTeam(teams.value, user.value);
  }
};

const switchTeam = async (teamId) => {
  try {
    await teamStore.switchTeam(teamId);
    await loadTeams();
    isTeamDropdownOpen.value = false;
    // Refresh the page after switching teams
    window.location.reload();
  } catch (error) {
    console.error('Error switching team:', error);
  }
};

const loadActiveJobs = async () => {
  if (isAuthenticated.value) {
    try {
      await jobStatusStore.fetchActiveJobs();
    } catch (error) {
      console.error('Error loading active jobs:', error);
    }
  }
};

onMounted(() => {
  loadTeams();
  loadActiveJobs();
  isTeamDropdownOpen.value = false;
});

// Clean up interval when component is unmounted
onUnmounted(() => {
  jobStatusStore.stopAutoRefresh();
});
</script>

<template>
  <nav class="bg-neutral-900 text-white">
    <div class="container mx-auto px-4 py-3 flex items-center justify-between">
      <div class="flex items-center space-x-4">
        <router-link to="/" class="text-xl font-bold">Paraloom</router-link>
        <div v-if="isAuthenticated" class="flex items-center space-x-4 ml-6">
          <button
            @click="isGenerateModalOpen = true"
            class="flex items-center space-x-1 px-2 py-1 rounded bg-white text-neutral-800 hover:bg-neutral-100 text-sm cursor-pointer"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
            </svg>
            <span>Generate</span>
          </button>
          <router-link to="/" class="text-sm hover:text-neutral-300">Prompts</router-link>
          <router-link to="/organizations" class="text-sm hover:text-neutral-300">Keywords</router-link>
        </div>
      </div>

      <div class="flex items-center space-x-3">
        <template v-if="isAuthenticated">
          <button v-if="activeJobsCount > 0" @click="isJobStatusSheetOpen = true" class="flex items-center space-x-2 cursor-pointer px-3 py-1 rounded hover:bg-neutral-800">
            <div class="relative size-5">
              <svg class="animate-spin absolute inset-0" viewBox="0 0 24 24">
                <circle class="text-neutral-800" stroke="currentColor" fill="transparent" stroke-width="2" cx="12" cy="12" r="11"></circle>
                <circle class="text-neutral-400" stroke="currentColor" fill="transparent" stroke-width="2" stroke-dasharray="17.27875959474386 51.83627878423158" stroke-dashoffset="0" stroke-linecap="butt" cx="12" cy="12" r="11"></circle>
              </svg>
              <div class="absolute inset-0 flex items-center justify-center">
                <span class="text-xs font-bold">{{ activeJobsCount }}</span>
              </div>
            </div>
            <span class="text-sm font-medium">Runs</span>
          </button>

          <PopoverRoot>
            <PopoverTrigger as-child>
              <div class="flex items-center space-x-2 cursor-pointer px-3 py-1 rounded bg-neutral-800 hover:bg-neutral-700">
                <span class="text-sm font-medium">{{ currentTeam?.name || 'Select Team' }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                  <path d="m6 9 6 6 6-6"/>
                </svg>
              </div>
            </PopoverTrigger>
            <PopoverPortal>
              <PopoverContent
                class="w-56 p-0 bg-neutral-800 rounded shadow-lg overflow-hidden border border-neutral-700 z-50"
                side="bottom"
                align="end"
                :side-offset="5"
              >
                <div class="p-2">
                  <p class="text-xs font-medium text-neutral-300 mb-2">Your Teams</p>
                  <div v-if="teams" class="space-y-1">
                    <div v-if="teams.joinedTeams && teams.joinedTeams.length > 0" class="space-y-1.5">
                      <PopoverClose as-child v-for="team in teams.joinedTeams" :key="team.id">
                        <div
                          @click="switchTeam(team.id)"
                          class="flex items-center px-2 py-1 rounded cursor-pointer hover:bg-neutral-700"
                          :class="{ 'bg-neutral-700': currentTeam?.id === team.id }">
                          <span class="text-sm text-white">{{ team.name }}</span>
                          <span v-if="currentTeam?.id === team.id" class="ml-auto text-xs text-neutral-400">Current</span>
                        </div>
                      </PopoverClose>
                    </div>
                  </div>
                  <div v-else class="text-sm text-neutral-400 py-1">Loading teams...</div>
                </div>
                <div class="border-t border-neutral-700 mt-1">
                  <PopoverClose as-child>
                    <router-link to="/teams" class="block px-3 py-2 text-sm text-white hover:bg-neutral-700">
                      Manage Teams
                    </router-link>
                  </PopoverClose>
                  <PopoverClose as-child>
                    <a @click="logout" class="cursor-pointer w-full text-left block px-3 py-2 text-sm text-white hover:bg-neutral-700">
                      Logout
                    </a>
                  </PopoverClose>
                </div>
              </PopoverContent>
            </PopoverPortal>
          </PopoverRoot>

          <!-- <button
            @click="logout"
            class="px-3 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-sm cursor-pointer"
          >
            Logout
          </button> -->
        </template>
        <template v-else>
          <router-link
            to="/login"
            class="px-3 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-sm"
          >
            Login
          </router-link>
          <router-link
            to="/register"
            class="px-3 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-sm"
          >
            Register
          </router-link>
        </template>
      </div>
    </div>
  </nav>
  <GeneratePromptsModal :is-open="isGenerateModalOpen" @close="isGenerateModalOpen = false" />
  <JobStatusSheet :is-open="isJobStatusSheetOpen" @close="isJobStatusSheetOpen = false" />
</template>
