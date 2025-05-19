<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import auth from '@/services/auth';
import teamService from '@/services/team';
import { PopoverRoot, PopoverTrigger, PopoverContent, PopoverPortal, PopoverClose } from 'reka-ui';

const router = useRouter();
const isAuthenticated = computed(() => auth.isAuthenticated());
const user = computed(() => auth.getUser());
const teams = ref(null);
const currentTeam = ref(null);
// Explicitly set popover to closed by default
const isTeamDropdownOpen = ref(false);

const logout = async () => {
  await auth.logout();
  router.push('/login');
};

const loadTeams = async () => {
  if (isAuthenticated.value) {
    try {
      teams.value = await teamService.getTeams();
      updateCurrentTeam();
    } catch (error) {
      console.error('Error loading teams:', error);
    }
  }
};

const updateCurrentTeam = () => {
  if (teams.value && user.value) {
    currentTeam.value = teamService.getCurrentTeam(teams.value, user.value);
  }
};

const switchTeam = async (teamId) => {
  try {
    await teamService.switchTeam(teamId);
    await loadTeams();
    isTeamDropdownOpen.value = false;
    // Refresh the page after switching teams
    window.location.reload();
  } catch (error) {
    console.error('Error switching team:', error);
  }
};

onMounted(() => {
  loadTeams();
  // Ensure popover is closed on mount
  isTeamDropdownOpen.value = false;
});
</script>

<template>
  <nav class="bg-neutral-900 text-white">
    <div class="container mx-auto px-4 py-3 flex items-center justify-between">
      <div class="flex items-center space-x-4">
        <router-link to="/" class="text-xl font-bold">Paraloom</router-link>
        <div v-if="isAuthenticated" class="flex items-center space-x-4 ml-6">
          <router-link to="/" class="text-sm hover:text-neutral-300">Dashboard</router-link>
          <router-link to="/teams" class="text-sm hover:text-neutral-300">Teams</router-link>
        </div>
      </div>
      
      <div class="flex items-center space-x-4">
        <template v-if="isAuthenticated">
          <span class="text-sm">{{ user?.name }}</span>

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
                    <div v-if="teams.joinedTeams && teams.joinedTeams.length > 0">
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
                </div>
              </PopoverContent>
            </PopoverPortal>
          </PopoverRoot>
          
          <button 
            @click="logout" 
            class="px-3 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-sm cursor-pointer"
          >
            Logout
          </button>
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
</template>
