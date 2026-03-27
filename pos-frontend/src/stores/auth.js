import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import api from '../services/api'

const TOKEN_KEY = 'pos_auth_token'
const USER_KEY = 'pos_auth_user'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem(TOKEN_KEY) ?? '')
  const user = ref(localStorage.getItem(USER_KEY) ? JSON.parse(localStorage.getItem(USER_KEY)) : null)
  const loading = ref(false)

  const isAuthenticated = computed(() => Boolean(token.value && user.value))
  const isAdmin = computed(() => user.value?.role === 'admin')

  function persistSession(newToken, newUser) {
    token.value = newToken
    user.value = newUser
    localStorage.setItem(TOKEN_KEY, newToken)
    localStorage.setItem(USER_KEY, JSON.stringify(newUser))
  }

  function clearSession() {
    token.value = ''
    user.value = null
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(USER_KEY)
  }

  async function login(payload) {
    loading.value = true
    try {
      const response = await api.post('/auth/login', payload)
      persistSession(response.data.token, response.data.user)
      return { ok: true }
    } catch (error) {
      return {
        ok: false,
        message: error.response?.data?.message ?? 'Login gagal. Cek email dan password.',
      }
    } finally {
      loading.value = false
    }
  }

  async function fetchMe() {
    if (!token.value) return
    try {
      const response = await api.get('/auth/me')
      user.value = response.data
      localStorage.setItem(USER_KEY, JSON.stringify(response.data))
    } catch {
      clearSession()
    }
  }

  async function logout() {
    try {
      if (token.value) {
        await api.post('/auth/logout')
      }
    } catch {
      // Ignore network/server errors during logout; always clear local session.
    } finally {
      clearSession()
    }
  }

  return {
    token,
    user,
    loading,
    isAuthenticated,
    isAdmin,
    login,
    fetchMe,
    logout,
    clearSession,
  }
})
