<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import { useTeamStore } from '@/stores/teamStore';
import auth from '@/services/auth';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';

const route = useRoute();
const teamStore = useTeamStore();
const teamId = computed(() => route.params.id);
const currentUser = computed(() => auth.getUser());
const isOwner = computed(() => teamStore.currentTeam?.owner_id === currentUser.value?.id);
const isAdmin = computed(() => {
  if (!teamStore.members || !currentUser.value) return false;
  return teamStore.members.some(member => 
    member.id === currentUser.value.id && 
    member.pivot.role === 'admin'
  );
});
const showEditModal = ref(false);
const showInviteModal = ref(false);
const editTeamName = ref('');
const inviteEmail = ref('');
const inviteRole = ref('member');
const isSubmitting = ref(false);

onMounted(async () => {
  await loadTeam();
});

const loadTeam = async () => {
  await teamStore.fetchTeam(teamId.value);
  if (teamStore.currentTeam) {
    editTeamName.value = teamStore.currentTeam.name;
  }
};

const updateTeam = async () => {
  if (!editTeamName.value) return;
  
  isSubmitting.value = true;
  try {
    await teamStore.updateTeam(teamId.value, { name: editTeamName.value });
    showEditModal.value = false;
  } catch (error) {
    console.error('Error updating team:', error);
  } finally {
    isSubmitting.value = false;
  }
};

const inviteUser = async () => {
  if (!inviteEmail.value) return;
  
  isSubmitting.value = true;
  try {
    await teamStore.inviteUser(teamId.value, { 
      email: inviteEmail.value,
      role: inviteRole.value
    });
    inviteEmail.value = '';
    inviteRole.value = 'member';
    showInviteModal.value = false;
  } catch (error) {
    console.error('Error inviting user:', error);
  } finally {
    isSubmitting.value = false;
  }
};

const removeMember = async (userId) => {
  if (!confirm('Are you sure you want to remove this member?')) return;
  
  try {
    await teamStore.removeMember(teamId.value, userId);
  } catch (error) {
    console.error('Error removing member:', error);
  }
};

const updateRole = async (userId, role) => {
  try {
    await teamStore.updateMemberRole(teamId.value, userId, { role });
  } catch (error) {
    console.error('Error updating role:', error);
  }
};
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8 px-4">
      <!-- Loading state -->
      <div v-if="teamStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
      </div>

      <!-- Error state -->
      <div v-else-if="teamStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        {{ teamStore.error }}
      </div>

      <div v-else-if="teamStore.currentTeam">
        <div class="flex justify-between items-center mb-8">
          <div>
            <h1 class="text-2xl font-bold">{{ teamStore.currentTeam.name }}</h1>
            <p class="text-neutral-600 mt-1">
              Owner: {{ teamStore.currentTeam.owner?.name || 'Unknown' }}
            </p>
          </div>
          <div class="flex space-x-2">
            <Button 
              v-if="isOwner || isAdmin"
              @click="showEditModal = true"
              class="bg-neutral-200 hover:bg-neutral-300 text-neutral-800"
            >
              Edit Team
            </Button>
            <Button 
              v-if="isOwner || isAdmin"
              @click="showInviteModal = true"
              class="bg-blue-600 hover:bg-blue-700 text-white"
            >
              Invite Member
            </Button>
          </div>
        </div>

        <!-- Team Members -->
        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden">
          <div class="px-6 py-4 bg-neutral-100 border-b border-neutral-200">
            <h2 class="text-lg font-semibold">Team Members ({{ teamStore.members.length }})</h2>
          </div>
          <div class="divide-y divide-neutral-200">
            <div 
              v-for="member in teamStore.members" 
              :key="member.id"
              class="px-6 py-4 flex items-center justify-between"
            >
              <div>
                <div class="font-medium">{{ member.name }}</div>
                <div class="text-sm text-neutral-500">{{ member.email }}</div>
              </div>
              <div class="flex items-center space-x-4">
                <div class="text-sm">
                  <span 
                    v-if="member.id === teamStore.currentTeam.owner_id" 
                    class="bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs"
                  >
                    Owner
                  </span>
                  <span 
                    v-else
                    class="bg-neutral-100 text-neutral-800 px-2 py-1 rounded-full text-xs"
                  >
                    {{ member.pivot.role === 'admin' ? 'Admin' : 'Member' }}
                  </span>
                </div>
                <div v-if="isOwner || isAdmin">
                  <div v-if="member.id !== teamStore.currentTeam.owner_id" class="flex space-x-2">
                    <select 
                      v-if="member.id !== $route.meta?.user?.id"
                      :value="member.pivot.role"
                      @change="updateRole(member.id, $event.target.value)"
                      class="text-sm border border-neutral-300 rounded px-2 py-1"
                    >
                      <option value="member">Member</option>
                      <option value="admin">Admin</option>
                    </select>
                    <button 
                      v-if="member.id !== $route.meta?.user?.id"
                      @click="removeMember(member.id)"
                      class="text-red-600 hover:text-red-800 text-sm"
                    >
                      Remove
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pending Invitations -->
        <div v-if="teamStore.pendingMembers.length > 0" class="mt-8 bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden">
          <div class="px-6 py-4 bg-neutral-100 border-b border-neutral-200">
            <h2 class="text-lg font-semibold">Pending Invitations ({{ teamStore.pendingMembers.length }})</h2>
          </div>
          <div class="divide-y divide-neutral-200">
            <div 
              v-for="member in teamStore.pendingMembers" 
              :key="member.id"
              class="px-6 py-4 flex items-center justify-between"
            >
              <div>
                <div class="font-medium">{{ member.name }}</div>
                <div class="text-sm text-neutral-500">{{ member.email }}</div>
                <div class="text-xs text-neutral-400 mt-1">
                  Invited: {{ new Date(member.pivot.invitation_sent_at).toLocaleDateString() }}
                </div>
              </div>
              <div class="flex items-center space-x-4">
                <div class="text-sm">
                  <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs">
                    Pending
                  </span>
                </div>
                <button 
                  v-if="isOwner || isAdmin"
                  @click="removeMember(member.id)"
                  class="text-red-600 hover:text-red-800 text-sm"
                >
                  Cancel Invitation
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Team Modal -->
      <div v-if="showEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
          <h2 class="text-xl font-bold mb-4">Edit Team</h2>
          <div class="mb-4">
            <label class="block text-sm font-medium text-neutral-700 mb-1">Team Name</label>
            <input 
              v-model="editTeamName"
              type="text"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter team name"
            />
          </div>
          <div class="flex justify-end space-x-2">
            <Button 
              @click="showEditModal = false"
              class="bg-neutral-200 hover:bg-neutral-300 text-neutral-800"
            >
              Cancel
            </Button>
            <Button 
              @click="updateTeam"
              :disabled="isSubmitting || !editTeamName"
              class="bg-blue-600 hover:bg-blue-700 text-white"
            >
              {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
            </Button>
          </div>
        </div>
      </div>

      <!-- Invite Member Modal -->
      <div v-if="showInviteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
          <h2 class="text-xl font-bold mb-4">Invite Team Member</h2>
          <div class="mb-4">
            <label class="block text-sm font-medium text-neutral-700 mb-1">Email Address</label>
            <input 
              v-model="inviteEmail"
              type="email"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter email address"
            />
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-neutral-700 mb-1">Role</label>
            <select 
              v-model="inviteRole"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="member">Member</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="flex justify-end space-x-2">
            <Button 
              @click="showInviteModal = false"
              class="bg-neutral-200 hover:bg-neutral-300 text-neutral-800"
            >
              Cancel
            </Button>
            <Button 
              @click="inviteUser"
              :disabled="isSubmitting || !inviteEmail"
              class="bg-blue-600 hover:bg-blue-700 text-white"
            >
              {{ isSubmitting ? 'Sending Invitation...' : 'Send Invitation' }}
            </Button>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
