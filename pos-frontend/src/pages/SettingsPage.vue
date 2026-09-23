<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../services/api'
import { useAuthStore } from '../stores/auth'
import { useEscToClose } from '../composables/useEscToClose'

const { t } = useI18n()
const auth = useAuthStore()
const isAdmin = auth.isAdmin

const loading = ref(false)
const error = ref('')
const successMsg = ref('')

// Store settings (stored in localStorage for now)
const storeSettings = ref({
  store_name: '',
  store_address: '',
  store_phone: '',
  tax_percentage: 0,
})

// Categories management
const categories = ref([])
const catForm = ref({ name: '' })
const catSubmitting = ref(false)
const editingCatId = ref(null)

// Users management
const users = ref([])
const userForm = ref({ name: '', email: '', role: 'cashier', password: '', manager_pin: '' })
const userSubmitting = ref(false)
const editingUserId = ref(null)
const showUserForm = ref(false)

useEscToClose(showUserForm, () => { showUserForm.value = false })

const SETTINGS_KEY = 'pos_store_settings'

function normalizePercent(value) {
  const raw = Number.parseFloat(value)
  if (!Number.isFinite(raw)) return 0
  const clamped = Math.max(0, Math.min(100, raw))
  return Math.round(clamped * 100) / 100
}

function loadStoreSettings() {
  const saved = localStorage.getItem(SETTINGS_KEY)
  if (saved) {
      try {
        Object.assign(storeSettings.value, JSON.parse(saved))
        storeSettings.value.tax_percentage = normalizePercent(storeSettings.value.tax_percentage)
      } catch {
        // Malformed JSON in localStorage — silently ignore
      }
  }
}

function saveStoreSettings() {
  storeSettings.value.tax_percentage = normalizePercent(storeSettings.value.tax_percentage)
  localStorage.setItem(SETTINGS_KEY, JSON.stringify(storeSettings.value))
  successMsg.value = t('settings.storeSaveSuccess')
  setTimeout(() => { successMsg.value = '' }, 3000)
}

function formatTaxInputOnBlur() {
  storeSettings.value.tax_percentage = normalizePercent(storeSettings.value.tax_percentage)
}

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [catRes, usrRes] = await Promise.all([
      api.get('/v1/categories'),
      api.get('/v1/users'),
    ])
    categories.value = catRes.data.data ?? catRes.data ?? []
    users.value = usrRes.data.data ?? usrRes.data ?? []
  } catch {
    error.value = t('settings.loadError')
  } finally {
    loading.value = false
  }
}

// Category CRUD
async function saveCategory() {
  catSubmitting.value = true
  error.value = ''
  try {
    if (editingCatId.value) {
      await api.put(`/v1/categories/${editingCatId.value}`, { name: catForm.value.name })
    } else {
      await api.post('/v1/categories', { name: catForm.value.name })
    }
    catForm.value.name = ''
    editingCatId.value = null
    await loadData()
  } catch (e) {
    error.value = e.response?.data?.message ?? t('settings.categorySaveError')
  } finally {
    catSubmitting.value = false
  }
}

function editCategory(cat) {
  catForm.value.name = cat.name
  editingCatId.value = cat.id
}

async function deleteCategory(id) {
  if (!confirm(t('settings.confirmDeleteCategory'))) return
  try {
    await api.delete(`/v1/categories/${id}`)
    await loadData()
  } catch (e) {
    error.value = e.response?.data?.message ?? t('settings.categoryDeleteError')
  }
}

// User CRUD
function openCreateUser() {
  editingUserId.value = null
  userForm.value = { name: '', email: '', role: 'cashier', password: '', manager_pin: '' }
  showUserForm.value = true
}

function openEditUser(user) {
  editingUserId.value = user.id
  userForm.value = { name: user.name, email: user.email, role: user.role, password: '', manager_pin: '' }
  showUserForm.value = true
}

async function saveUser() {
  userSubmitting.value = true
  error.value = ''
  try {
    const payload = { ...userForm.value }
    if (!payload.password) delete payload.password
    if (!payload.manager_pin) delete payload.manager_pin

    if (editingUserId.value) {
      await api.put(`/v1/users/${editingUserId.value}`, payload)
    } else {
      await api.post('/v1/users', payload)
    }
    showUserForm.value = false
    await loadData()
  } catch (e) {
    const errData = e.response?.data
    error.value = errData?.errors ? Object.values(errData.errors).flat().join(', ') : (errData?.message ?? t('settings.userSaveError'))
  } finally {
    userSubmitting.value = false
  }
}

async function deleteUser(id) {
  if (!confirm(t('settings.confirmDeleteUser'))) return
  try {
    await api.delete(`/v1/users/${id}`)
    await loadData()
  } catch {
    error.value = t('settings.userDeleteError')
  }
}

onMounted(() => {
  loadStoreSettings()
  loadData()
})

// ── Ganti PIN Saya ───────────────────────────────────────────────────────────
const pinForm = ref({ current_pin: '', new_pin: '', confirm_pin: '' })
const pinSubmitting = ref(false)
const pinError = ref('')
const pinSuccess = ref('')

async function saveMyPin() {
  pinError.value = ''
  pinSuccess.value = ''

  if (pinForm.value.new_pin.length < 4 || !/^[0-9]+$/.test(pinForm.value.new_pin)) {
    pinError.value = t('settings.pinTooShort')
    return
  }
  if (pinForm.value.new_pin !== pinForm.value.confirm_pin) {
    pinError.value = t('settings.pinMismatch')
    return
  }

  pinSubmitting.value = true
  try {
    await api.put(`/v1/users/${auth.user.id}`, {
      name: auth.user.name,
      email: auth.user.email,
      role: auth.user.role,
      current_pin: pinForm.value.current_pin || undefined,
      manager_pin: pinForm.value.new_pin,
    })
    pinSuccess.value = t('settings.pinUpdateSuccess')
    pinForm.value = { current_pin: '', new_pin: '', confirm_pin: '' }
  } catch (e) {
    const errData = e.response?.data
    pinError.value = errData?.errors
      ? Object.values(errData.errors).flat().join(', ')
      : (errData?.message ?? t('settings.pinSaveError'))
  } finally {
    pinSubmitting.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-bold text-ink">{{ t('settings.title') }}</h1>
      <p class="text-sm text-ink-faint">{{ t('settings.subtitle') }}</p>
    </div>

    <div v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
    <div v-if="successMsg" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ successMsg }}</div>

    <!-- Store settings -->
    <div class="rounded-2xl border border-line-soft bg-surface p-6 shadow-sm">
      <h2 class="mb-4 font-semibold text-ink">{{ t('settings.storeInfo') }}</h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block">
          <span class="mb-1 block text-sm text-ink-soft">{{ t('settings.storeName') }}</span>
          <input v-model="storeSettings.store_name" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm" :placeholder="t('settings.storeNamePlaceholder')" />
        </label>
        <label class="block">
          <span class="mb-1 block text-sm text-ink-soft">{{ t('settings.phone') }}</span>
          <input v-model="storeSettings.store_phone" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm" :placeholder="t('settings.phonePlaceholder')" />
        </label>
        <label class="block sm:col-span-2">
          <span class="mb-1 block text-sm text-ink-soft">{{ t('settings.address') }}</span>
          <textarea v-model="storeSettings.store_address" rows="2" class="w-full rounded-lg border border-line px-3 py-2 text-sm" :placeholder="t('settings.addressPlaceholder')"></textarea>
        </label>
        <label class="block">
          <span class="mb-1 block text-sm text-ink-soft">{{ t('settings.defaultTax') }}</span>
          <input v-model.number="storeSettings.tax_percentage" type="number" min="0" max="100" step="0.01" class="w-full rounded-lg border border-line px-3 py-2 text-sm" @blur="formatTaxInputOnBlur" />
          <p class="mt-1 text-xs text-ink-faint">{{ t('settings.maxPercent') }}</p>
        </label>
      </div>
      <button class="mt-4 rounded-full bg-brand-600 active:scale-95 transition-transform px-5 py-2 text-sm font-semibold text-white hover:bg-brand-500" @click="saveStoreSettings">
        {{ t('settings.saveStoreSettings') }}
      </button>
    </div>

    <!-- Category management -->
    <div class="rounded-2xl border border-line-soft bg-surface p-6 shadow-sm">
      <h2 class="mb-4 font-semibold text-ink">{{ t('settings.categoryManagement') }}</h2>

      <div class="mb-4 flex gap-2">
        <input v-model="catForm.name" type="text" :placeholder="t('settings.categoryNamePlaceholder')" class="flex-1 rounded-lg border border-line px-3 py-2 text-sm" @keyup.enter="saveCategory" />
        <button
          :disabled="catSubmitting || !catForm.name"
          class="rounded-full bg-brand-600 active:scale-95 transition-transform px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50"
          @click="saveCategory"
        >
          {{ editingCatId ? t('settings.update') : t('settings.add') }}
        </button>
        <button v-if="editingCatId" class="rounded-lg border border-line px-3 py-2 text-sm hover:bg-surface-2" @click="editingCatId = null; catForm.name = ''">
          {{ t('settings.cancel') }}
        </button>
      </div>

      <div v-if="loading" class="text-sm text-ink-faint">{{ t('settings.loadingShort') }}</div>
      <ul v-else class="divide-y divide-line-soft">
        <li v-for="cat in categories" :key="cat.id" class="flex items-center justify-between py-2.5 text-sm">
          <span class="text-ink">{{ cat.name }}</span>
          <div class="flex gap-2">
            <button class="text-xs text-brand-500 hover:underline" @click="editCategory(cat)">{{ t('settings.edit') }}</button>
            <button class="text-xs text-rose-600 dark:text-rose-400 hover:underline" @click="deleteCategory(cat.id)">{{ t('settings.delete') }}</button>
          </div>
        </li>
        <li v-if="categories.length === 0" class="py-4 text-center text-ink-faint text-sm">{{ t('settings.noCategoriesYet') }}</li>
      </ul>
    </div>

    <!-- User management -->
    <div class="rounded-2xl border border-line-soft bg-surface p-6 shadow-sm">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="font-semibold text-ink">{{ t('settings.userManagement') }}</h2>
        <button class="rounded-full bg-brand-600 active:scale-95 transition-transform px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500" @click="openCreateUser">
          + {{ t('settings.addUser') }}
        </button>
      </div>

      <div v-if="loading" class="text-sm text-ink-faint">{{ t('settings.loadingShort') }}</div>
      <div v-else class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b border-line-soft text-xs font-semibold uppercase tracking-wide text-ink-faint">
          <tr>
            <th class="pb-2 text-left">{{ t('settings.nameCol') }}</th>
            <th class="pb-2 text-left">{{ t('settings.emailCol') }}</th>
            <th class="pb-2 text-left">{{ t('settings.roleCol') }}</th>
            <th class="pb-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-line-soft">
          <tr v-for="user in users" :key="user.id">
            <td class="py-2.5 font-medium text-ink">{{ user.name }}</td>
            <td class="py-2.5 text-ink-faint">{{ user.email }}</td>
            <td class="py-2.5">
              <span :class="user.role === 'admin' ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700'" class="rounded-full px-2 py-0.5 text-xs font-semibold">
                {{ user.role }}
              </span>
            </td>
            <td class="py-2.5 text-right">
              <button class="mr-3 text-xs text-brand-500 hover:underline" @click="openEditUser(user)">{{ t('settings.edit') }}</button>
              <button class="text-xs text-rose-600 dark:text-rose-400 hover:underline" @click="deleteUser(user.id)">{{ t('settings.delete') }}</button>
            </td>
          </tr>
          <tr v-if="users.length === 0">
            <td colspan="4" class="py-6 text-center text-ink-faint">{{ t('settings.noUsersYet') }}</td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <!-- Ganti PIN Saya (hanya admin) -->
    <div v-if="isAdmin" class="rounded-2xl border border-line-soft bg-surface p-6 shadow-sm">
      <h2 class="mb-1 font-semibold text-ink">{{ t('settings.changeMyPin') }}</h2>
      <p class="mb-4 text-xs text-ink-faint">{{ t('settings.pinUsageHint') }}</p>

      <div v-if="pinError" class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ pinError }}</div>
      <div v-if="pinSuccess" class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">{{ pinSuccess }}</div>

      <div class="grid gap-4 sm:grid-cols-3 text-sm">
        <label class="block">
          <span class="mb-1 block text-ink-soft">{{ t('settings.newPin') }}</span>
          <input
            v-model="pinForm.new_pin"
            type="password"
            inputmode="numeric"
            maxlength="20"
            :placeholder="t('settings.pinPlaceholder')"
            class="w-full rounded-lg border border-line px-3 py-2"
            autocomplete="new-password"
          />
        </label>
        <label class="block">
          <span class="mb-1 block text-ink-soft">{{ t('settings.confirmPin') }}</span>
          <input
            v-model="pinForm.confirm_pin"
            type="password"
            inputmode="numeric"
            maxlength="20"
            :placeholder="t('settings.confirmPinPlaceholder')"
            class="w-full rounded-lg border border-line px-3 py-2"
            autocomplete="new-password"
          />
        </label>
        <div class="flex items-end">
          <button
            :disabled="pinSubmitting || !pinForm.new_pin || !pinForm.confirm_pin"
            class="w-full rounded-full bg-brand-600 active:scale-95 transition-transform px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50"
            @click="saveMyPin"
          >
            {{ pinSubmitting ? t('settings.saving') : t('settings.savePin') }}
          </button>
        </div>
      </div>
    </div>

    <!-- User form modal -->
    <Transition name="fade">
      <div v-if="showUserForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div role="dialog" aria-modal="true" aria-labelledby="user-form-title" class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-2xl">
          <div class="mb-5 flex items-center justify-between">
            <h2 id="user-form-title" class="text-lg font-bold text-ink">{{ editingUserId ? t('settings.editUserTitle') : t('settings.addUserTitle') }}</h2>
            <button :aria-label="t('settings.close')" class="text-ink-faint hover:text-ink-soft" @click="showUserForm = false">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="space-y-4 text-sm">
            <label class="block">
              <span class="mb-1 block text-ink-soft">{{ t('settings.nameRequired') }}</span>
              <input v-model="userForm.name" type="text" class="w-full rounded-lg border border-line px-3 py-2" />
            </label>
            <label class="block">
              <span class="mb-1 block text-ink-soft">{{ t('settings.emailRequired') }}</span>
              <input v-model="userForm.email" type="email" class="w-full rounded-lg border border-line px-3 py-2" />
            </label>
            <label class="block">
              <span class="mb-1 block text-ink-soft">{{ t('settings.roleLabel') }}</span>
              <select v-model="userForm.role" class="w-full rounded-lg border border-line px-3 py-2">
                <option value="admin">{{ t('settings.roleAdmin') }}</option>
                <option value="cashier">{{ t('settings.roleCashier') }}</option>
              </select>
            </label>
            <label class="block">
              <span class="mb-1 block text-ink-soft">{{ t('settings.password') }} {{ editingUserId ? t('settings.keepUnchangedHint') : '*' }}</span>
              <input v-model="userForm.password" type="password" class="w-full rounded-lg border border-line px-3 py-2" autocomplete="new-password" />
            </label>
            <label v-if="userForm.role === 'admin'" class="block">
              <span class="mb-1 block text-ink-soft">{{ t('settings.managerPin') }} {{ editingUserId ? t('settings.keepUnchangedHint') : '*' }}</span>
              <input
                v-model="userForm.manager_pin"
                type="password"
                inputmode="numeric"
                maxlength="20"
                :placeholder="t('settings.pinPlaceholder')"
                class="w-full rounded-lg border border-line px-3 py-2"
                autocomplete="new-password"
              />
              <p class="mt-1 text-xs text-ink-faint">{{ t('settings.managerPinHint') }}</p>
            </label>
          </div>

          <div class="mt-5 flex gap-3">
            <button class="flex-1 rounded-lg border border-line py-2.5 text-sm hover:bg-surface-2" @click="showUserForm = false">{{ t('settings.cancel') }}</button>
            <button
              :disabled="userSubmitting"
              class="flex-1 rounded-full bg-brand-600 active:scale-95 transition-transform py-2.5 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50"
              @click="saveUser"
            >
              {{ userSubmitting ? t('settings.saving') : t('settings.save') }}
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
