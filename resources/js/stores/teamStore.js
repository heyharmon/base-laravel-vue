import { defineStore } from 'pinia';
import api from '@/services/api';

export const useTeamStore = defineStore('team', {
  state: () => ({
    ownedTeams: [],
    joinedTeams: [],
    pendingInvitations: [],
    currentTeam: null,
    members: [],
    pendingMembers: [],
    isLoading: false,
    error: null
  }),
  
  actions: {
    async fetchTeams() {
      this.isLoading = true;
      this.error = null;
      
      try {
        const response = await api.get('/teams');
        this.ownedTeams = response.ownedTeams;
        this.joinedTeams = response.joinedTeams;
        this.pendingInvitations = response.pendingInvitations;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch teams';
        console.error('Error fetching teams:', error);
      } finally {
        this.isLoading = false;
      }
    },
    
    async fetchTeam(teamId) {
      this.isLoading = true;
      this.error = null;
      
      try {
        const response = await api.get(`/teams/${teamId}`);
        this.currentTeam = response.team;
        this.members = response.members;
        this.pendingMembers = response.pendingInvitations;
        return response;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch team details';
        console.error('Error fetching team details:', error);
      } finally {
        this.isLoading = false;
      }
    },
    
    async createTeam(teamData) {
      this.isLoading = true;
      this.error = null;
      
      try {
        const response = await api.post('/teams', teamData);
        await this.fetchTeams();
        return response;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to create team';
        console.error('Error creating team:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },
    
    async updateTeam(teamId, teamData) {
      this.isLoading = true;
      this.error = null;
      
      try {
        const response = await api.put(`/teams/${teamId}`, teamData);
        if (this.currentTeam && this.currentTeam.id === teamId) {
          this.currentTeam = { ...this.currentTeam, ...teamData };
        }
        await this.fetchTeams();
        return response;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update team';
        console.error('Error updating team:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },
    
    async inviteUser(teamId, userData) {
      this.isLoading = true;
      this.error = null;
      
      try {
        const response = await api.post(`/teams/${teamId}/invite`, userData);
        await this.fetchTeam(teamId);
        return response;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to invite user';
        console.error('Error inviting user:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },
    
    async acceptInvitation(teamId) {
      this.isLoading = true;
      this.error = null;
      
      try {
        const response = await api.post(`/teams/${teamId}/accept-invitation`);
        await this.fetchTeams();
        return response;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to accept invitation';
        console.error('Error accepting invitation:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },
    
    async declineInvitation(teamId) {
      this.isLoading = true;
      this.error = null;
      
      try {
        const response = await api.post(`/teams/${teamId}/decline-invitation`);
        await this.fetchTeams();
        return response;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to decline invitation';
        console.error('Error declining invitation:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },
    
    async removeMember(teamId, userId) {
      this.isLoading = true;
      this.error = null;
      
      try {
        const response = await api.delete(`/teams/${teamId}/members/${userId}`);
        await this.fetchTeam(teamId);
        return response;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to remove member';
        console.error('Error removing member:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    },
    
    async updateMemberRole(teamId, userId, roleData) {
      this.isLoading = true;
      this.error = null;
      
      try {
        const response = await api.put(`/teams/${teamId}/members/${userId}/role`, roleData);
        await this.fetchTeam(teamId);
        return response;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update member role';
        console.error('Error updating member role:', error);
        throw error;
      } finally {
        this.isLoading = false;
      }
    }
  }
});
