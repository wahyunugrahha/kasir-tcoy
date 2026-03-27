<script setup>
import { onMounted, ref } from 'vue'
import api from '../services/api'

const openShift = ref(null)
const shifts = ref([])
const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const successMsg = ref('')

// Open shift form
const openForm = ref({ user_id: 2, opening_cash: '' })

// Close shift form
const closeForm = ref({ closing_cash_physical: '', notes: '' })

async function loadShifts() {
  loading.value = true
  try {
    const res = await api.get('/v1/shifts')
    shifts.value = res.data.data ?? []
    openShift.value = shifts.value.find((s) => s.status === 'open') ?? null
  } catch {
    error.value = 'Gagal memuat data shift.'
  } finally {
    loading.value = false
  }
}

async function startShift() {
  error.value = ''
  successMsg.value = ''
  submitting.value = true
  try {
    await api.post('/v1/shifts', {
      user_id: Number(openForm.value.user_id),
      opening_cash: Number(openForm.value.opening_cash),
    })
    successMsg.value = 'Shift berhasil dibuka!'
    openForm.value.opening_cash = ''
    await loadShifts()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal membuka shift.'
  } finally {
    submitting.value = false
  }
}

async function closeShift() {
  if (!openShift.value) return
  error.value = ''
  successMsg.value = ''
  submitting.value = true
  try {
    const res = await api.put(`/v1/shifts/${openShift.value.id}/close`, {
      closing_cash_physical: Number(closeForm.value.closing_cash_physical),
      notes: closeForm.value.notes,
    })
    const closed = res.data
    successMsg.value = `Shift ditutup. Selisih kas: ${formatCurrency(closed.cash_difference)}`
    closeForm.value = { closing_cash_physical: '', notes: '' }
    await loadShifts()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal menutup shift.'
  } finally {
    submitting.value = false
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(loadShifts)
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <div>
      <h1 class="text-xl font-bold text-slate-800">Settlement (Tutup Kasir)</h1>
      <p class="text-sm text-slate-500">Kelola shift dan rekap kas harian</p>
    </div>

    <div v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
    <div v-if="successMsg" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ successMsg }}</div>

    <!-- No open shift → Show open shift form -->
    <div v-if="!openShift" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="mb-4 text-base font-semibold text-slate-700">Buka Shift Baru</h2>
      <div class="space-y-4">
        <label class="block">
          <span class="mb-1 block text-sm text-slate-600">Kasir (User ID)</span>
          <input v-model.number="openForm.user_id" type="number" min="1" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
        </label>
        <label class="block">
          <span class="mb-1 block text-sm text-slate-600">Modal Awal Kas (Rp)</span>
          <input v-model.number="openForm.opening_cash" type="number" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="0" />
        </label>
        <button
          :disabled="submitting"
          class="w-full rounded-lg bg-indigo-600 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
          @click="startShift"
        >
          {{ submitting ? 'Menyimpan...' : '▶ Mulai Shift' }}
        </button>
      </div>
    </div>

    <!-- Active shift → Show close shift form -->
    <div v-else class="rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm">
      <div class="mb-4 flex items-center gap-3">
        <span class="inline-block rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">● SHIFT AKTIF</span>
        <span class="text-sm text-slate-500">Dibuka: {{ formatDate(openShift.started_at) }}</span>
      </div>

      <div class="mb-5 grid grid-cols-2 gap-4 rounded-xl bg-slate-50 p-4 text-sm">
        <div>
          <p class="text-xs text-slate-500">Modal Awal</p>
          <p class="text-base font-bold text-slate-800">{{ formatCurrency(openShift.opening_cash) }}</p>
        </div>
        <div>
          <p class="text-xs text-slate-500">Kasir</p>
          <p class="text-base font-semibold text-slate-800">{{ openShift.user?.name ?? `User #${openShift.user_id}` }}</p>
        </div>
      </div>

      <h2 class="mb-4 text-base font-semibold text-slate-700">Tutup Shift</h2>
      <div class="space-y-4">
        <label class="block">
          <span class="mb-1 block text-sm text-slate-600">Uang Fisik di Laci (Rp)</span>
          <input v-model.number="closeForm.closing_cash_physical" type="number" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Hitung uang di laci..." />
        </label>
        <label class="block">
          <span class="mb-1 block text-sm text-slate-600">Catatan (jika ada selisih)</span>
          <textarea v-model="closeForm.notes" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Tulis keterangan bila ada selisih..."></textarea>
        </label>
        <button
          :disabled="submitting || !closeForm.closing_cash_physical"
          class="w-full rounded-lg bg-rose-600 py-2.5 text-sm font-semibold text-white hover:bg-rose-500 disabled:opacity-50"
          @click="closeShift"
        >
          {{ submitting ? 'Menyimpan...' : '⏹ Tutup Shift' }}
        </button>
      </div>
    </div>

    <!-- Recent shifts history -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="font-semibold text-slate-700">Riwayat Shift</h2>
      </div>
      <div v-if="loading" class="py-8 text-center text-slate-500 text-sm">Memuat...</div>
      <div v-else-if="shifts.length === 0" class="py-8 text-center text-slate-400 text-sm">Belum ada shift.</div>
      <table v-else class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3 text-left">Buka</th>
            <th class="px-4 py-3 text-left">Tutup</th>
            <th class="px-4 py-3 text-right">Modal</th>
            <th class="px-4 py-3 text-right">Fisik</th>
            <th class="px-4 py-3 text-right">Selisih</th>
            <th class="px-4 py-3 text-left">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="shift in shifts" :key="shift.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 text-slate-600">{{ formatDate(shift.started_at) }}</td>
            <td class="px-4 py-3 text-slate-600">{{ formatDate(shift.ended_at) }}</td>
            <td class="px-4 py-3 text-right">{{ formatCurrency(shift.opening_cash) }}</td>
            <td class="px-4 py-3 text-right">{{ formatCurrency(shift.closing_cash_physical) }}</td>
            <td class="px-4 py-3 text-right" :class="Number(shift.cash_difference) < 0 ? 'text-rose-600 font-semibold' : 'text-emerald-600 font-semibold'">
              {{ shift.cash_difference != null ? formatCurrency(shift.cash_difference) : '-' }}
            </td>
            <td class="px-4 py-3">
              <span :class="shift.status === 'open' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'" class="rounded-full px-2 py-0.5 text-xs font-semibold">
                {{ shift.status === 'open' ? 'Aktif' : 'Selesai' }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
