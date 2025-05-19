import api from './api';

const team = {
  async getTeams() {
    const response = await api.get('/teams');
    return response;
  },

  async switchTeam(teamId) {
    const response = await api.post(`/teams/${teamId}/switch`);
    if (response.team && response.message) {
      // Update the user in localStorage with new current team
      const user = JSON.parse(localStorage.getItem('user'));
      if (user) {
        user.current_team_id = response.team.id;
        localStorage.setItem('user', JSON.stringify(user));
      }
      return response;
    }
    return null;
  },

  getCurrentTeam(teams, user) {
    if (!teams || !user || !user.current_team_id) return null;
    
    // Find the current team from the list of teams
    return teams.ownedTeams.find(team => team.id === user.current_team_id) || 
           teams.joinedTeams.find(team => team.id === user.current_team_id) ||
           null;
  }
};

export default team;
