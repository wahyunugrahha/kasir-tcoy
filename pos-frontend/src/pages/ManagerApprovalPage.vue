<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../services/api'
import { useManagerApprovalStore } from '../stores/managerApproval'

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
    return 'Belum ada approval aktif.'
  }

  const remaining = Math.floor(approval.secondsLeft / 60)
  return `Approval aktif: ${approval.managerName} (${approval.managerEmail}) - sisa sekitar ${remaining} menit`
})

async function loadManagers() {
  loadingManagers.value = true
  error.value = ''

  try {
    const res = await api.get('/v1/managers')
    managers.value = res.data ?? []
  } catch (err) {
    error.value = err.response?.data?.message ?? 'Gagal memuat daftar manager.'
  } finally {
    loadingManagers.value = false
  }
}

async function verifyAndActivate() {
  error.value = ''
  successMessage.value = ''

  if (!selectedManagerId.value || !managerPin.value) {
    error.value = 'Pilih manager dan masukkan PIN terlebih dahulu.'
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
      throw new Error('PIN manager tidak valid.')
    }

    approval.setApproval({
      manager: res.data.manager,
      pin: managerPin.value,
    })

    successMessage.value = 'Approval manager aktif selama 10 menit.'
    managerPin.value = ''
  } catch (err) {
    error.value = err.response?.data?.message ?? err.message ?? 'Verifikasi manager gagal.'
  } finally {
    submitting.value = false
  }
}

function clearApproval() {
  approval.clearApproval()
  successMessage.value = 'Approval manager dibersihkan.'
}

onMounted(loadManagers)
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-5">
    <div>
      <h1 class="text-xl font-bold text-slate-800">Approval Manager</h1>
      <p class="text-sm text-slate-500">Aktifkan approval manager agar proses refund/void cashier tidak perlu input PIN berulang.</p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4">
      <p class="text-sm text-slate-700">{{ approvalStatus }}</p>
      <button
        v-if="approval.isValid"
        class="mt-3 rounded-lg border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50"
        @click="clearApproval"
      >
        Hapus Approval Aktif
      </button>
    </div>

    <div v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
    <div v-if="successMessage" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ successMessage }}</div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 class="mb-4 text-base font-semibold text-slate-700">Verifikasi Manager PIN</h2>

      <label class="block">
        <span class="mb-1 block text-sm text-slate-600">Manager</span>
        <select
          v-model="selectedManagerId"
          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
          :disabled="loadingManagers"
        >
          <option value="">Pilih manager...</option>
          <option v-for="manager in managers" :key="manager.id" :value="manager.id">
            {{ manager.name }} ({{ manager.email }})
          </option>
        </select>
      </label>

      <label class="mt-3 block">
        <span class="mb-1 block text-sm text-slate-600">PIN Manager</span>
        <input
          v-model="managerPin"
          type="password"
          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
          placeholder="Masukkan PIN manager"
        />
      </label>

      <button
        :disabled="submitting"
        class="mt-4 w-full rounded-lg bg-indigo-600 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
        @click="verifyAndActivate"
      >
        {{ submitting ? 'Memverifikasi...' : 'Aktifkan Approval 10 Menit' }}
      </button>
    </div>
  </div>
</template>
