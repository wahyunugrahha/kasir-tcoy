<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../services/api'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

const openShift = ref(null)
const openShiftDetail = ref(null)
const shifts = ref([])
const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const successMsg = ref('')

// Open shift form
const openForm = ref({ opening_cash: '' })

// Close shift form
const closeForm = ref({ closing_cash_physical: '', notes: '' })
const movementForm = ref({ type: 'cash_drop', amount: '', reason: '', notes: '' })

async function loadOpenShiftDetail() {
  if (!openShift.value?.id) {
    openShiftDetail.value = null
    return
  }

  try {
    const res = await api.get(`/v1/shifts/${openShift.value.id}`)
    openShiftDetail.value = res.data
  } catch {
    openShiftDetail.value = null
  }
}

async function loadShifts() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/v1/shifts')
    shifts.value = res.data.data ?? []
    openShift.value = shifts.value.find((s) => s.status === 'open' && Number(s.user_id) === Number(auth.user?.id)) ?? null
    await loadOpenShiftDetail()
  } catch {
    error.value = t('settlement.loadError')
  } finally {
    loading.value = false
  }
}

async function addCashMovement() {
  if (!openShift.value) return

  error.value = ''
  successMsg.value = ''
  submitting.value = true
  try {
    await api.post(`/v1/shifts/${openShift.value.id}/cash-movements`, {
      type: movementForm.value.type,
      amount: Number(movementForm.value.amount),
      reason: String(movementForm.value.reason || '').trim(),
      notes: String(movementForm.value.notes || '').trim() || null,
    })

    movementForm.value = { type: 'cash_drop', amount: '', reason: '', notes: '' }
    successMsg.value = t('settlement.cashMovementSuccess')
    await loadShifts()
  } catch (e) {
    error.value = e.response?.data?.message ?? t('settlement.cashMovementError')
  } finally {
    submitting.value = false
  }
}

function totalMovement(type) {
  return (openShiftDetail.value?.cash_movements ?? [])
    .filter((m) => m.type === type)
    .reduce((sum, m) => sum + Number(m.amount || 0), 0)
}

async function startShift() {
  error.value = ''
  successMsg.value = ''
  submitting.value = true
  try {
    await api.post('/v1/shifts', {
      opening_cash: Number(openForm.value.opening_cash),
    })
    successMsg.value = t('settlement.shiftOpenSuccess')
    openForm.value.opening_cash = ''
    await loadShifts()
  } catch (e) {
    error.value = e.response?.data?.message ?? t('settlement.shiftOpenError')
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
    successMsg.value = t('settlement.shiftCloseSuccess', { amount: formatCurrency(closed.cash_difference) })
    closeForm.value = { closing_cash_physical: '', notes: '' }
    await loadShifts()
  } catch (e) {
    error.value = e.response?.data?.message ?? t('settlement.shiftCloseError')
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
      <h1 class="text-xl font-bold text-ink">{{ t('settlement.title') }}</h1>
      <p class="text-sm text-ink-faint">{{ t('settlement.subtitle') }}</p>
    </div>

    <div v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
    <div v-if="successMsg" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ successMsg }}</div>

    <!-- No open shift → Show open shift form -->
    <div v-if="!openShift" class="rounded-2xl border border-line-soft bg-surface p-6 shadow-sm">
      <h2 class="mb-4 text-base font-semibold text-ink">{{ t('settlement.openNewShift') }}</h2>
      <div class="space-y-4">
        <div class="rounded-lg border border-line-soft bg-surface-2 px-3 py-2 text-sm text-ink">
          {{ t('settlement.activeAccount') }} <span class="font-semibold">{{ auth.user?.name ?? '-' }}</span>
        </div>
        <label class="block">
          <span class="mb-1 block text-sm text-ink-soft">{{ t('settlement.openingCash') }}</span>
          <input v-model.number="openForm.opening_cash" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2 text-sm" placeholder="0" />
        </label>
        <button
          :disabled="submitting"
          class="w-full rounded-full bg-brand-600 active:scale-95 transition-transform py-2.5 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50"
          @click="startShift"
        >
          {{ submitting ? t('settlement.saving') : `▶ ${t('settlement.startShift')}` }}
        </button>
      </div>
    </div>

    <!-- Active shift → Show close shift form -->
    <div v-else class="rounded-2xl border border-emerald-200 bg-surface p-6 shadow-sm">
      <div class="mb-4 flex items-center gap-3">
        <span class="inline-block rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">● {{ t('settlement.shiftActiveBadge') }}</span>
        <span class="text-sm text-ink-faint">{{ t('settlement.openedAt') }} {{ formatDate(openShift.started_at) }}</span>
      </div>

      <div class="mb-5 grid grid-cols-2 gap-4 rounded-xl bg-surface-2 p-4 text-sm">
        <div>
          <p class="text-xs text-ink-faint">{{ t('settlement.openingCashLabel') }}</p>
          <p class="text-base font-bold text-ink">{{ formatCurrency(openShift.opening_cash) }}</p>
        </div>
        <div>
          <p class="text-xs text-ink-faint">{{ t('settlement.cashierLabel') }}</p>
          <p class="text-base font-semibold text-ink">{{ openShift.user?.name ?? t('settlement.userFallback', { id: openShift.user_id }) }}</p>
        </div>
      </div>

      <h2 class="mb-4 text-base font-semibold text-ink">{{ t('settlement.closeShift') }}</h2>
      <div class="space-y-4">
        <label class="block">
          <span class="mb-1 block text-sm text-ink-soft">{{ t('settlement.physicalCash') }}</span>
          <input v-model.number="closeForm.closing_cash_physical" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2 text-sm" :placeholder="t('settlement.physicalCashPlaceholder')" />
        </label>
        <label class="block">
          <span class="mb-1 block text-sm text-ink-soft">{{ t('settlement.notes') }}</span>
          <textarea v-model="closeForm.notes" rows="3" class="w-full rounded-lg border border-line px-3 py-2 text-sm" :placeholder="t('settlement.notesPlaceholder')"></textarea>
        </label>
        <button
          :disabled="submitting || !closeForm.closing_cash_physical"
          class="w-full rounded-lg bg-rose-600 py-2.5 text-sm font-semibold text-white hover:bg-rose-500 disabled:opacity-50"
          @click="closeShift"
        >
          {{ submitting ? t('settlement.saving') : `⏹ ${t('settlement.closeShift')}` }}
        </button>
      </div>

      <div class="mt-6 rounded-xl border border-line-soft bg-surface-2 p-4">
        <h3 class="mb-3 text-sm font-semibold text-ink">{{ t('settlement.cashMovementTitle') }}</h3>
        <div class="grid gap-3 md:grid-cols-2">
          <label class="block">
            <span class="mb-1 block text-xs text-ink-soft">{{ t('settlement.type') }}</span>
            <select v-model="movementForm.type" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
              <option value="cash_drop">{{ t('settlement.cashDrop') }}</option>
              <option value="pay_out">{{ t('settlement.payOut') }}</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1 block text-xs text-ink-soft">{{ t('settlement.amount') }}</span>
            <input v-model.number="movementForm.amount" type="number" min="1" class="w-full rounded-lg border border-line px-3 py-2 text-sm" />
          </label>
          <label class="block md:col-span-2">
            <span class="mb-1 block text-xs text-ink-soft">{{ t('settlement.reason') }}</span>
            <input v-model="movementForm.reason" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm" :placeholder="t('settlement.reasonPlaceholder')" />
          </label>
          <label class="block md:col-span-2">
            <span class="mb-1 block text-xs text-ink-soft">{{ t('settlement.notesLabel') }}</span>
            <input v-model="movementForm.notes" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm" :placeholder="t('settlement.optionalPlaceholder')" />
          </label>
        </div>

        <button
          :disabled="submitting || !movementForm.amount || !movementForm.reason"
          class="mt-3 w-full rounded-lg border border-brand-300 bg-brand-50 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-100 disabled:opacity-50"
          @click="addCashMovement"
        >
          {{ submitting ? t('settlement.saving') : `+ ${t('settlement.addCashMovement')}` }}
        </button>

        <div class="mt-3 grid grid-cols-2 gap-3 text-xs text-ink">
          <p class="rounded bg-surface px-3 py-2">{{ t('settlement.totalCashDrop') }} <span class="font-semibold">{{ formatCurrency(totalMovement('cash_drop')) }}</span></p>
          <p class="rounded bg-surface px-3 py-2">{{ t('settlement.totalPayOut') }} <span class="font-semibold">{{ formatCurrency(totalMovement('pay_out')) }}</span></p>
        </div>

        <div v-if="(openShiftDetail?.cash_movements ?? []).length > 0" class="mt-3 rounded border border-line-soft bg-surface">
          <table class="w-full text-xs">
            <thead class="bg-surface-2 text-left uppercase tracking-wide text-ink-faint">
              <tr>
                <th class="px-3 py-2">{{ t('settlement.timeCol') }}</th>
                <th class="px-3 py-2">{{ t('settlement.type') }}</th>
                <th class="px-3 py-2">{{ t('settlement.reason') }}</th>
                <th class="px-3 py-2 text-right">{{ t('settlement.amount') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <tr v-for="movement in openShiftDetail.cash_movements" :key="movement.id">
                <td class="px-3 py-2">{{ formatDate(movement.created_at) }}</td>
                <td class="px-3 py-2">{{ movement.type }}</td>
                <td class="px-3 py-2">{{ movement.reason }}</td>
                <td class="px-3 py-2 text-right">{{ formatCurrency(movement.amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Recent shifts history -->
    <div class="rounded-2xl border border-line-soft bg-surface shadow-sm">
      <div class="border-b border-line-soft px-5 py-4">
        <h2 class="font-semibold text-ink">{{ t('settlement.shiftHistory') }}</h2>
      </div>
      <div v-if="loading" class="py-8 text-center text-ink-faint text-sm">{{ t('settlement.loadingShort') }}</div>
      <div v-else-if="shifts.length === 0" class="py-8 text-center text-ink-faint text-sm">{{ t('settlement.noShiftsYet') }}</div>
      <table v-else class="w-full text-sm">
        <thead class="border-b border-line-soft bg-surface-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">
          <tr>
            <th class="px-4 py-3 text-left">{{ t('settlement.openCol') }}</th>
            <th class="px-4 py-3 text-left">{{ t('settlement.closeCol') }}</th>
            <th class="px-4 py-3 text-right">{{ t('settlement.capitalCol') }}</th>
            <th class="px-4 py-3 text-right">{{ t('settlement.physicalCol') }}</th>
            <th class="px-4 py-3 text-right">{{ t('settlement.differenceCol') }}</th>
            <th class="px-4 py-3 text-left">{{ t('settlement.statusCol') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-line-soft">
          <tr v-for="shift in shifts" :key="shift.id" class="hover:bg-surface-2">
            <td class="px-4 py-3 text-ink-soft">{{ formatDate(shift.started_at) }}</td>
            <td class="px-4 py-3 text-ink-soft">{{ formatDate(shift.ended_at) }}</td>
            <td class="px-4 py-3 text-right">{{ formatCurrency(shift.opening_cash) }}</td>
            <td class="px-4 py-3 text-right">{{ formatCurrency(shift.closing_cash_physical) }}</td>
            <td class="px-4 py-3 text-right" :class="Number(shift.cash_difference) < 0 ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-emerald-600 font-semibold'">
              {{ shift.cash_difference != null ? formatCurrency(shift.cash_difference) : '-' }}
            </td>
            <td class="px-4 py-3">
              <span :class="shift.status === 'open' ? 'bg-emerald-100 text-emerald-700' : 'bg-surface-2 text-ink-soft'" class="rounded-full px-2 py-0.5 text-xs font-semibold">
                {{ shift.status === 'open' ? t('settlement.active') : t('settlement.finished') }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
