import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import auth from '@/services/auth'

export const useUserStore = defineStore('user', () => {
  const currentUserRef = ref(auth.getUser())

  function setCurrentUser(user) {
    currentUserRef.value = user
    if (user) {
      localStorage.setItem('user', JSON.stringify(user))
    } else {
      localStorage.removeItem('user')
    }
  }

  async function fetchMe() {
    const user = await api.get('/user')
    setCurrentUser(user)
    return user
  }

  async function acknowledgeIndividualRunWarning() {
    const user = await api.post('/user/acknowledge-individual-run-warning')
    setCurrentUser(user)
    return user
  }

  return {
    currentUser: currentUserRef,
    setCurrentUser,
    fetchMe,
    acknowledgeIndividualRunWarning
  }
})
