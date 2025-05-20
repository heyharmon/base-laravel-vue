<script setup>
import { ref, onMounted } from 'vue';
import { useTeamStore } from '@/stores/teamStore';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';

const teamStore = useTeamStore();
const showCreateModal = ref(false);
const newTeamName = ref('');
const isSubmitting = ref(false);

onMounted(async () => {
  await teamStore.fetchTeams();
});

const createTeam = async () => {
  if (!newTeamName.value) return;
  
  isSubmitting.value = true;
  try {
    await teamStore.createTeam({ name: newTeamName.value });
    newTeamName.value = '';
    showCreateModal.value = false;
  } catch (error) {
    console.error('Error creating team:', error);
  } finally {
    isSubmitting.value = false;
  }
};

const acceptInvitation = async (teamId) => {
  try {
    await teamStore.acceptInvitation(teamId);
  } catch (error) {
    console.error('Error accepting invitation:', error);
  }
};

const declineInvitation = async (teamId) => {
  try {
    await teamStore.declineInvitation(teamId);
  } catch (error) {
    console.error('Error declining invitation:', error);
  }
};
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-bold">Teams</h1>
        <Button @click="showCreateModal = true">Create Team</Button>
      </div>

      <!-- Loading state -->
      <div v-if="teamStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
      </div>

      <!-- Error state -->
      <div v-else-if="teamStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        {{ teamStore.error }}
      </div>

      <div v-else>
        <!-- Teams you own -->
        <div class="mb-8">
          <h2 class="text-xl font-semibold mb-4">Teams You Own</h2>
          <div v-if="teamStore.ownedTeams.length === 0" class="text-neutral-500">
            You don't own any teams yet.
          </div>
          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div 
              v-for="team in teamStore.ownedTeams" 
              :key="team.id" 
              class="bg-neutral-100 p-4 rounded-lg shadow"
            >
              <div class="flex justify-between items-start">
                <h3 class="text-lg font-medium">{{ team.name }}</h3>
                <span class="bg-neutral-200 text-neutral-800 text-xs px-2 py-1 rounded">Owner</span>
              </div>
              <div class="mt-2 text-sm text-neutral-600">
                <div>{{ team.members_count }} members</div>
                <div v-if="team.pending_invitations_count > 0">
                  {{ team.pending_invitations_count }} pending invitations
                </div>
              </div>
              <div class="mt-4">
                <router-link 
                  :to="{ name: 'teams.show', params: { id: team.id } }"
                  class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                >
                  View Team
                </router-link>
              </div>
            </div>
          </div>
        </div>

        <!-- Teams you're a member of -->
        <div class="mb-8">
          <h2 class="text-xl font-semibold mb-4">Teams You've Joined</h2>
          <div v-if="teamStore.joinedTeams.length === 0" class="text-neutral-500">
            You haven't joined any teams yet.
          </div>
          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div 
              v-for="team in teamStore.joinedTeams" 
              :key="team.id" 
              class="bg-neutral-100 p-4 rounded-lg shadow"
            >
              <div class="flex justify-between items-start">
                <h3 class="text-lg font-medium">{{ team.name }}</h3>
                <span class="bg-neutral-200 text-neutral-800 text-xs px-2 py-1 rounded">Member</span>
              </div>
              <div class="mt-2 text-sm text-neutral-600">
                <div>{{ team.members_count }} members</div>
              </div>
              <div class="mt-4">
                <router-link 
                  :to="{ name: 'teams.show', params: { id: team.id } }"
                  class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                >
                  View Team
                </router-link>
              </div>
            </div>
          </div>
        </div>

        <!-- Pending invitations -->
        <div>
          <h2 class="text-xl font-semibold mb-4">Pending Invitations</h2>
          <div v-if="teamStore.pendingInvitations.length === 0" class="text-neutral-500">
            You don't have any pending invitations.
          </div>
          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div 
              v-for="team in teamStore.pendingInvitations" 
              :key="team.id" 
              class="bg-neutral-100 p-4 rounded-lg shadow border-l-4 border-blue-500"
            >
              <h3 class="text-lg font-medium">{{ team.name }}</h3>
              <div class="mt-4 flex space-x-2">
                <Button 
                  @click="acceptInvitation(team.id)"
                  class="bg-green-600 hover:bg-green-700 text-white"
                >
                  Accept
                </Button>
                <Button 
                  @click="declineInvitation(team.id)"
                  class="bg-neutral-600 hover:bg-neutral-700 text-white"
                >
                  Decline
                </Button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Create Team Modal -->
      <div v-if="showCreateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
          <h2 class="text-xl font-bold mb-4">Create New Team</h2>
          <div class="mb-4">
            <label class="block text-sm font-medium text-neutral-700 mb-1">Team Name</label>
            <input 
              v-model="newTeamName"
              type="text"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter team name"
            />
          </div>
          <div class="flex justify-end space-x-2">
            <Button 
              @click="showCreateModal = false"
              class="bg-neutral-200 hover:bg-neutral-300 text-neutral-800"
            >
              Cancel
            </Button>
            <Button 
              @click="createTeam"
              :disabled="isSubmitting || !newTeamName"
              class="bg-blue-600 hover:bg-blue-700 text-white"
            >
              {{ isSubmitting ? 'Creating...' : 'Create Team' }}
            </Button>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
