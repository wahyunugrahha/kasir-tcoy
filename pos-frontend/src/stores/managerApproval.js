import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

const APPROVAL_TTL_MS = 10 * 60 * 1000

export const useManagerApprovalStore = defineStore('managerApproval', () => {
  const nowTs = ref(Date.now())
  const tickerId = ref(null)

  const managerUserId = ref(null)
  const managerName = ref('')
  const managerEmail = ref('')
  const managerPin = ref('')
  const expiresAt = ref(0)

  const isValid = computed(() => {
    return Boolean(managerUserId.value && managerPin.value && Number(nowTs.value) < Number(expiresAt.value || 0))
  })

  const secondsLeft = computed(() => {
    if (!isValid.value) {
      return 0
    }

    return Math.max(0, Math.floor((Number(expiresAt.value) - Number(nowTs.value)) / 1000))
  })

  function startTicker() {
    if (tickerId.value) {
      return
    }

    tickerId.value = window.setInterval(() => {
      nowTs.value = Date.now()
    }, 1000)
  }

  function stopTicker() {
    if (!tickerId.value) {
      return
    }

    window.clearInterval(tickerId.value)
    tickerId.value = null
  }

  function setApproval({ manager, pin }) {
    managerUserId.value = Number(manager.id)
    managerName.value = manager.name ?? ''
    managerEmail.value = manager.email ?? ''
    managerPin.value = String(pin)
    expiresAt.value = Date.now() + APPROVAL_TTL_MS
  }

  function clearApproval() {
    managerUserId.value = null
    managerName.value = ''
    managerEmail.value = ''
    managerPin.value = ''
    expiresAt.value = 0
  }

  return {
    managerUserId,
    managerName,
    managerEmail,
    managerPin,
    expiresAt,
    isValid,
    secondsLeft,
    startTicker,
    stopTicker,
    setApproval,
    clearApproval,
  }
})
