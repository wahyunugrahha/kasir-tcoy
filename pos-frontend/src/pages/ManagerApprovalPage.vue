<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../services/api'
import { useManagerApprovalStore } from '../stores/managerApproval'

const { t } = useI18n()
const approval = useManagerApprovalStore()

const managers = ref([])
const loadingManagers = ref(false)
const submitting = ref(false)
const error = ref('')
const successMessage = ref('')

const selectedManagerId = ref('')
const managerPin = ref('')

const approvalStatus = computed(() => {
  if (!approval.isValid) {
    return t('managerApproval.noActiveApproval')
  }

  const remaining = Math.floor(approval.secondsLeft / 60)
  return t('managerApproval.activeApproval', { name: approval.managerName, email: approval.managerEmail, minutes: remaining })
})

async function loadManagers() {
  loadingManagers.value = true
  error.value = ''

  try {
    const res = await api.get('/v1/managers')
    managers.value = res.data ?? []
  } catch (err) {
    error.value = err.response?.data?.message ?? t('managerApproval.loadError')
  } finally {
    loadingManagers.value = false
  }
}

async function verifyAndActivate() {
  error.value = ''
  successMessage.value = ''

  if (!selectedManagerId.value || !managerPin.value) {
    error.value = t('managerApproval.selectManagerFirst')
    return
  }

  submitting.value = true

  try {
    const payload = {
      manager_user_id: Number(selectedManagerId.value),
      manager_pin: String(managerPin.value),
    }

    const res = await api.post('/v1/managers/verify-pin', payload)

    if (!res.data?.valid) {
      throw new Error(t('managerApproval.invalidPin'))
    }

    approval.setApproval({
      manager: res.data.manager,
      pin: managerPin.value,
    })

    successMessage.value = t('managerApproval.activateSuccess')
    managerPin.value = ''
  } catch (err) {
    error.value = err.response?.data?.message ?? err.message ?? t('managerApproval.verifyError')
  } finally {
    submitting.value = false
  }
}

function clearApproval() {
  approval.clearApproval()
  successMessage.value = t('managerApproval.cleared')
}

onMounted(loadManagers)
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-5">
    <div>
      <h1 class="text-xl font-bold text-ink">{{ t('managerApproval.title') }}</h1>
      <p class="text-sm text-ink-faint">{{ t('managerApproval.subtitle') }}</p>
    </div>

    <div class="rounded-xl border border-line-soft bg-surface p-4">
      <p class="text-sm text-ink">{{ approvalStatus }}</p>
      <button
        v-if="approval.isValid"
        class="mt-3 rounded-lg border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50"
        @click="clearApproval"
      >
        {{ t('managerApproval.clearActive') }}
      </button>
    </div>

    <div v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
    <div v-if="successMessage" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ successMessage }}</div>

    <div class="rounded-2xl border border-line-soft bg-surface p-5 shadow-sm">
      <h2 class="mb-4 text-base font-semibold text-ink">{{ t('managerApproval.verifyTitle') }}</h2>

      <label class="block">
        <span class="mb-1 block text-sm text-ink-soft">{{ t('managerApproval.managerLabel') }}</span>
        <select
          v-model="selectedManagerId"
          class="w-full rounded-lg border border-line px-3 py-2 text-sm"
          :disabled="loadingManagers"
        >
          <option value="">{{ t('managerApproval.selectManagerPlaceholder') }}</option>
          <option v-for="manager in managers" :key="manager.id" :value="manager.id">
            {{ manager.name }} ({{ manager.email }})
          </option>
        </select>
      </label>

      <label class="mt-3 block">
        <span class="mb-1 block text-sm text-ink-soft">{{ t('managerApproval.pinLabel') }}</span>
        <input
          v-model="managerPin"
          type="password"
          class="w-full rounded-lg border border-line px-3 py-2 text-sm"
          :placeholder="t('managerApproval.pinPlaceholder')"
        />
      </label>

      <button
        :disabled="submitting"
        class="mt-4 w-full rounded-full bg-brand-600 active:scale-95 transition-transform py-2.5 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50"
        @click="verifyAndActivate"
      >
        {{ submitting ? t('managerApproval.verifying') : t('managerApproval.activateBtn') }}
      </button>
    </div>
  </div>
</template>
