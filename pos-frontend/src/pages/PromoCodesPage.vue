<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../services/api'
import { useEscToClose } from '../composables/useEscToClose'

const { t } = useI18n()

const promoCodes = ref([])
const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const successMsg = ref('')
const showForm = ref(false)
const editingId = ref(null)

useEscToClose(showForm, () => { showForm.value = false })

const form = ref({
  code: '',
  discount_type: 'percent',
  discount_value: '',
  min_purchase: '',
  max_discount: '',
  starts_at: '',
  ends_at: '',
  usage_limit: '',
  active: true,
})

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/v1/promo-codes', { params: { per_page: 100 } })
    promoCodes.value = res.data.data ?? []
  } catch {
    error.value = t('promoCodes.loadError')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = { code: '', discount_type: 'percent', discount_value: '', min_purchase: '', max_discount: '', starts_at: '', ends_at: '', usage_limit: '', active: true }
  showForm.value = true
}

function openEdit(promo) {
  editingId.value = promo.id
  form.value = {
    code: promo.code,
    discount_type: promo.discount_type,
    discount_value: promo.discount_value,
    min_purchase: promo.min_purchase ?? '',
    max_discount: promo.max_discount ?? '',
    starts_at: promo.starts_at ? promo.starts_at.slice(0, 16) : '',
    ends_at: promo.ends_at ? promo.ends_at.slice(0, 16) : '',
    usage_limit: promo.usage_limit ?? '',
    active: Boolean(promo.active),
  }
  showForm.value = true
}

async function savePromoCode() {
  error.value = ''
  successMsg.value = ''
  submitting.value = true

  const payload = {
    code: form.value.code,
    discount_type: form.value.discount_type,
    discount_value: Number(form.value.discount_value),
    min_purchase: form.value.min_purchase === '' ? null : Number(form.value.min_purchase),
    max_discount: form.value.max_discount === '' ? null : Number(form.value.max_discount),
    starts_at: form.value.starts_at || null,
    ends_at: form.value.ends_at || null,
    usage_limit: form.value.usage_limit === '' ? null : Number(form.value.usage_limit),
    active: form.value.active,
  }

  try {
    if (editingId.value) {
      await api.put(`/v1/promo-codes/${editingId.value}`, payload)
      successMsg.value = t('promoCodes.updateSuccess')
    } else {
      await api.post('/v1/promo-codes', payload)
      successMsg.value = t('promoCodes.createSuccess')
    }

    showForm.value = false
    await loadData()
  } catch (e) {
    const errData = e.response?.data
    error.value = errData?.errors ? Object.values(errData.errors).flat().join(', ') : (errData?.message ?? t('promoCodes.saveError'))
  } finally {
    submitting.value = false
  }
}

async function deletePromoCode(id) {
  if (!confirm(t('promoCodes.confirmDelete'))) return
  try {
    await api.delete(`/v1/promo-codes/${id}`)
    await loadData()
  } catch {
    error.value = t('promoCodes.deleteError')
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))
}

function describeDiscount(promo) {
  return promo.discount_type === 'percent' ? `${Number(promo.discount_value)}%` : formatCurrency(promo.discount_value)
}

onMounted(loadData)
</script>

<template>
  <div>
    <div class="mb-5 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-ink">{{ t('promoCodes.title') }}</h1>
        <p class="text-sm text-ink-faint">{{ t('promoCodes.countRegistered', { count: promoCodes.length }) }}</p>
      </div>
      <button class="rounded-full bg-brand-600 active:scale-95 transition-transform px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500" @click="openCreate">
        + {{ t('promoCodes.addPromo') }}
      </button>
    </div>

    <div v-if="error" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
    <div v-if="successMsg" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ successMsg }}</div>

    <div v-if="loading" class="py-12 text-center text-ink-faint">{{ t('promoCodes.loading') }}</div>

    <div v-else-if="promoCodes.length === 0" class="rounded-2xl border-2 border-dashed border-line-soft bg-surface py-16 text-center">
      <svg class="mx-auto h-10 w-10 text-line" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20.59 13.41L11 3.83A2 2 0 009.59 3.24L4 3a1 1 0 00-1 1l.24 5.59a2 2 0 00.59 1.41l9.58 9.58a2 2 0 002.83 0l4.35-4.35a2 2 0 000-2.82zM7 8a1 1 0 110-2 1 1 0 010 2z" />
      </svg>
      <p class="mt-3 text-ink-faint">{{ t('promoCodes.empty') }}</p>
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-line-soft bg-surface shadow-sm">
      <table class="w-full text-sm">
        <thead class="border-b border-line-soft bg-surface-2 text-left text-xs font-semibold uppercase tracking-wide text-ink-faint">
          <tr>
            <th class="px-4 py-3">{{ t('promoCodes.codeCol') }}</th>
            <th class="px-4 py-3">{{ t('promoCodes.discountCol') }}</th>
            <th class="px-4 py-3">{{ t('promoCodes.minPurchaseCol') }}</th>
            <th class="px-4 py-3">{{ t('promoCodes.usageCol') }}</th>
            <th class="px-4 py-3">{{ t('promoCodes.validCol') }}</th>
            <th class="px-4 py-3">{{ t('promoCodes.statusCol') }}</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-line-soft">
          <tr v-for="promo in promoCodes" :key="promo.id" class="hover:bg-surface-2">
            <td class="px-4 py-3 font-mono font-semibold text-brand-600">{{ promo.code }}</td>
            <td class="px-4 py-3 text-ink">{{ describeDiscount(promo) }}<span v-if="promo.max_discount"> {{ t('promoCodes.maxDiscountSuffix', { amount: formatCurrency(promo.max_discount) }) }}</span></td>
            <td class="px-4 py-3 text-ink-soft">{{ promo.min_purchase ? formatCurrency(promo.min_purchase) : '-' }}</td>
            <td class="px-4 py-3 text-ink-soft">{{ promo.times_used }}{{ promo.usage_limit ? ` / ${promo.usage_limit}` : '' }}</td>
            <td class="px-4 py-3 text-xs text-ink-faint">
              <span v-if="!promo.starts_at && !promo.ends_at">{{ t('promoCodes.always') }}</span>
              <span v-else>{{ promo.starts_at ? new Date(promo.starts_at).toLocaleDateString('id-ID') : '...' }} - {{ promo.ends_at ? new Date(promo.ends_at).toLocaleDateString('id-ID') : '...' }}</span>
            </td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="promo.active ? 'bg-emerald-100 text-emerald-700' : 'bg-surface-2 text-ink-faint'">
                {{ promo.active ? t('promoCodes.active') : t('promoCodes.inactive') }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <button class="mr-2 text-xs font-medium text-brand-600 hover:underline" @click="openEdit(promo)">{{ t('promoCodes.edit') }}</button>
              <button class="text-xs font-medium text-rose-600 dark:text-rose-400 hover:underline" @click="deletePromoCode(promo.id)">{{ t('promoCodes.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Transition name="fade">
      <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div role="dialog" aria-modal="true" aria-labelledby="promo-form-title" class="w-full max-w-lg overflow-y-auto rounded-2xl bg-surface p-6 shadow-2xl max-h-[90vh]">
          <div class="mb-5 flex items-center justify-between">
            <h2 id="promo-form-title" class="text-lg font-bold text-ink">{{ editingId ? t('promoCodes.editTitle') : t('promoCodes.addTitle') }}</h2>
            <button :aria-label="t('promoCodes.close')" class="text-ink-faint hover:text-ink-soft" @click="showForm = false">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="space-y-4 text-sm">
            <label class="block">
              <span class="mb-1 block text-ink-soft">{{ t('promoCodes.codeLabel') }}</span>
              <input v-model="form.code" type="text" placeholder="DISKON10" class="w-full rounded-lg border border-line px-3 py-2 uppercase" />
            </label>

            <div class="grid grid-cols-2 gap-3">
              <label class="block">
                <span class="mb-1 block text-ink-soft">{{ t('promoCodes.discountType') }}</span>
                <select v-model="form.discount_type" class="w-full rounded-lg border border-line px-3 py-2">
                  <option value="percent">{{ t('promoCodes.percent') }}</option>
                  <option value="fixed">{{ t('promoCodes.fixedAmount') }}</option>
                </select>
              </label>
              <label class="block">
                <span class="mb-1 block text-ink-soft">{{ t('promoCodes.discountValue') }}</span>
                <input v-model.number="form.discount_value" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2" />
              </label>
            </div>

            <div v-if="form.discount_type === 'percent'" class="grid grid-cols-2 gap-3">
              <label class="block">
                <span class="mb-1 block text-ink-soft">{{ t('promoCodes.minPurchase') }}</span>
                <input v-model.number="form.min_purchase" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2" />
              </label>
              <label class="block">
                <span class="mb-1 block text-ink-soft">{{ t('promoCodes.maxDiscount') }}</span>
                <input v-model.number="form.max_discount" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2" />
              </label>
            </div>
            <label v-else class="block">
              <span class="mb-1 block text-ink-soft">{{ t('promoCodes.minPurchase') }}</span>
              <input v-model.number="form.min_purchase" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2" />
            </label>

            <div class="grid grid-cols-2 gap-3">
              <label class="block">
                <span class="mb-1 block text-ink-soft">{{ t('promoCodes.startsAt') }}</span>
                <input v-model="form.starts_at" type="datetime-local" class="w-full rounded-lg border border-line px-3 py-2" />
              </label>
              <label class="block">
                <span class="mb-1 block text-ink-soft">{{ t('promoCodes.endsAt') }}</span>
                <input v-model="form.ends_at" type="datetime-local" class="w-full rounded-lg border border-line px-3 py-2" />
              </label>
            </div>

            <label class="block">
              <span class="mb-1 block text-ink-soft">{{ t('promoCodes.usageLimit') }}</span>
              <input v-model.number="form.usage_limit" type="number" min="1" :placeholder="t('promoCodes.noLimit')" class="w-full rounded-lg border border-line px-3 py-2" />
            </label>

            <label class="flex items-center gap-2">
              <input v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-line" />
              <span class="text-ink-soft">{{ t('promoCodes.active') }}</span>
            </label>
          </div>

          <div class="mt-5 flex gap-3">
            <button class="flex-1 rounded-lg border border-line py-2.5 text-sm hover:bg-surface-2" @click="showForm = false">
              {{ t('promoCodes.cancel') }}
            </button>
            <button
              :disabled="submitting"
              class="flex-1 rounded-full bg-brand-600 active:scale-95 transition-transform py-2.5 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50"
              @click="savePromoCode"
            >
              {{ submitting ? t('promoCodes.saving') : (editingId ? t('promoCodes.saveChanges') : t('promoCodes.addPromo')) }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
