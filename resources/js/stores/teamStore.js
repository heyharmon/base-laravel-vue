import { defineStore } from 'pinia'
import api from '@/services/api'

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
			console.log('Fetching teams...')
			this.isLoading = true
			this.error = null

			try {
				const response = await api.get('/teams')
				this.ownedTeams = response.ownedTeams
				this.joinedTeams = response.joinedTeams
				this.pendingInvitations = response.pendingInvitations
			} catch (error) {
				this.error = error.response?.data?.message || 'Failed to fetch teams'
				console.error('Error fetching teams:', error)
			} finally {
				this.isLoading = false
			}
		},

		async fetchTeam(teamId) {
			console.log('Fetching team details for team ID:', teamId)
			this.isLoading = true
			this.error = null

			try {
				const response = await api.get(`/teams/${teamId}`)
				this.currentTeam = response.team
				this.members = response.members
				this.pendingMembers = response.pendingInvitations
				return response
			} catch (error) {
				this.error = error.response?.data?.message || 'Failed to fetch team details'
				console.error('Error fetching team details:', error)
			} finally {
				this.isLoading = false
			}
		},

		async createTeam(teamData) {
			console.log('Creating team...')
			this.isLoading = true
			this.error = null

			try {
				const response = await api.post('/teams', teamData)
				await this.fetchTeams()
				return response
			} catch (error) {
				this.error = error.response?.data?.message || 'Failed to create team'
				console.error('Error creating team:', error)
				throw error
			} finally {
				this.isLoading = false
			}
		},

		async updateTeam(teamId, teamData) {
			console.log('Updating team ID:', teamId)
			this.isLoading = true
			this.error = null

			try {
				const response = await api.put(`/teams/${teamId}`, teamData)
				await this.fetchTeam(teamId)
				return response
			} catch (error) {
				this.error = error.response?.data?.message || 'Failed to update team'
				console.error('Error updating team:', error)
				throw error
			} finally {
				this.isLoading = false
			}
		},

		async inviteUser(teamId, userData) {
			console.log('Inviting user to team ID:', teamId)
			this.isLoading = true
			this.error = null

			try {
				const response = await api.post(`/teams/${teamId}/invite`, userData)
				await this.fetchTeam(teamId)
				return response
			} catch (error) {
				this.error = error.response?.data?.message || 'Failed to invite user'
				console.error('Error inviting user:', error)
				throw error
			} finally {
				this.isLoading = false
			}
		},

		async acceptInvitation(teamId) {
			console.log('Accepting invitation for team ID:', teamId)
			this.isLoading = true
			this.error = null

			try {
				const response = await api.post(`/teams/${teamId}/accept-invitation`)
				await this.switchTeam(teamId)
				return response
			} catch (error) {
				this.error = error.response?.data?.message || 'Failed to accept invitation'
				console.error('Error accepting invitation:', error)
				throw error
			} finally {
				this.isLoading = false
			}
		},

		async declineInvitation(teamId) {
			console.log('Declining invitation for team ID:', teamId)
			this.isLoading = true
			this.error = null

			try {
				const response = await api.post(`/teams/${teamId}/decline-invitation`)
				await this.fetchTeams()
				return response
			} catch (error) {
				this.error = error.response?.data?.message || 'Failed to decline invitation'
				console.error('Error declining invitation:', error)
				throw error
			} finally {
				this.isLoading = false
			}
		},

		async removeMember(teamId, userId) {
			console.log('Removing member from team ID:', teamId)
			this.isLoading = true
			this.error = null

			try {
				const response = await api.delete(`/teams/${teamId}/members/${userId}`)
				await this.fetchTeam(teamId)
				return response
			} catch (error) {
				this.error = error.response?.data?.message || 'Failed to remove member'
				console.error('Error removing member:', error)
				throw error
			} finally {
				this.isLoading = false
			}
		},

		async deleteTeam(teamId) {
			console.log('Deleting team ID:', teamId)
			this.isLoading = true
			this.error = null

			try {
				const response = await api.delete(`/teams/${teamId}`)
				await this.fetchTeams()
				if (this.currentTeam && this.currentTeam.id === teamId) {
					this.currentTeam = null
					this.members = []
					this.pendingMembers = []
				}
				return response
			} catch (error) {
				this.error = error.response?.data?.message || 'Failed to delete team'
				console.error('Error deleting team:', error)
				throw error
			} finally {
				this.isLoading = false
			}
		},

		async updateMemberRole(teamId, userId, roleData) {
			console.log('Updating member role for team ID:', teamId)
			this.isLoading = true
			this.error = null

			try {
				const response = await api.put(`/teams/${teamId}/members/${userId}/role`, roleData)
				await this.fetchTeam(teamId)
				return response
			} catch (error) {
				this.error = error.response?.data?.message || 'Failed to update member role'
				console.error('Error updating member role:', error)
				throw error
			} finally {
				this.isLoading = false
			}
		},

		async switchTeam(teamId) {
			console.log('Switching to team ID:', teamId)
			this.isLoading = true
			this.error = null

			try {
				const response = await api.post(`/teams/${teamId}/switch`)
				if (response.team && response.message) {
					// Update the user in localStorage with new current team
					const user = JSON.parse(localStorage.getItem('user'))
					if (user) {
						user.current_team_id = response.team.id
						localStorage.setItem('user', JSON.stringify(user))
					}

					// Fetch the team data to ensure we have the latest information
					await this.fetchTeam(teamId)

					return response
				}
				return null
			} catch (error) {
				this.error = error.response?.data?.message || 'Failed to switch team'
				console.error('Error switching team:', error)
				throw error
			} finally {
				this.isLoading = false
			}
		},

		getCurrentTeam(teams, user) {
			if (!teams || !user || !user.current_team_id) return null

			// Find the current team from the list of teams
			return (
				teams.ownedTeams.find((team) => team.id === user.current_team_id) || teams.joinedTeams.find((team) => team.id === user.current_team_id) || null
			)
		}
	}
})
