<script setup>
import { onMounted, ref } from 'vue'
import api from '../services/api'
import { useAuthStore } from '../stores/auth'

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
  successMsg.value = 'Pengaturan toko berhasil disimpan!'
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
    error.value = 'Gagal memuat data pengaturan.'
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
    error.value = e.response?.data?.message ?? 'Gagal menyimpan kategori.'
  } finally {
    catSubmitting.value = false
  }
}

function editCategory(cat) {
  catForm.value.name = cat.name
  editingCatId.value = cat.id
}

async function deleteCategory(id) {
  if (!confirm('Hapus kategori ini? Produk yang terkait tidak akan dihapus.')) return
  try {
    await api.delete(`/v1/categories/${id}`)
    await loadData()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Gagal menghapus kategori.'
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
    error.value = errData?.errors ? Object.values(errData.errors).flat().join(', ') : (errData?.message ?? 'Gagal menyimpan user.')
  } finally {
    userSubmitting.value = false
  }
}

async function deleteUser(id) {
  if (!confirm('Hapus user ini?')) return
  try {
    await api.delete(`/v1/users/${id}`)
    await loadData()
  } catch {
    error.value = 'Gagal menghapus user.'
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
    pinError.value = 'PIN baru harus minimal 4 digit angka.'
    return
  }
  if (pinForm.value.new_pin !== pinForm.value.confirm_pin) {
    pinError.value = 'Konfirmasi PIN tidak cocok.'
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
    pinSuccess.value = 'PIN Manager berhasil diperbarui.'
    pinForm.value = { current_pin: '', new_pin: '', confirm_pin: '' }
  } catch (e) {
    const errData = e.response?.data
    pinError.value = errData?.errors
      ? Object.values(errData.errors).flat().join(', ')
      : (errData?.message ?? 'Gagal menyimpan PIN.')
  } finally {
    pinSubmitting.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-bold text-slate-800">Pengaturan</h1>
      <p class="text-sm text-slate-500">Konfigurasi toko, kategori, dan akun pengguna</p>
    </div>

    <div v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
    <div v-if="successMsg" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ successMsg }}</div>

    <!-- Store settings -->
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="mb-4 font-semibold text-slate-700">Informasi Toko</h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block">
          <span class="mb-1 block text-sm text-slate-600">Nama Toko</span>
          <input v-model="storeSettings.store_name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Nama Toko Anda" />
        </label>
        <label class="block">
          <span class="mb-1 block text-sm text-slate-600">No. Telepon</span>
          <input v-model="storeSettings.store_phone" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="+62..." />
        </label>
        <label class="block sm:col-span-2">
          <span class="mb-1 block text-sm text-slate-600">Alamat</span>
          <textarea v-model="storeSettings.store_address" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Alamat lengkap..."></textarea>
        </label>
        <label class="block">
          <span class="mb-1 block text-sm text-slate-600">Pajak Default (%)</span>
          <input v-model.number="storeSettings.tax_percentage" type="number" min="0" max="100" step="0.01" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @blur="formatTaxInputOnBlur" />
          <p class="mt-1 text-xs text-slate-500">Maks 100%</p>
        </label>
      </div>
      <button class="mt-4 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500" @click="saveStoreSettings">
        Simpan Pengaturan Toko
      </button>
    </div>

    <!-- Category management -->
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="mb-4 font-semibold text-slate-700">Manajemen Kategori</h2>

      <div class="mb-4 flex gap-2">
        <input v-model="catForm.name" type="text" placeholder="Nama kategori..." class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm" @keyup.enter="saveCategory" />
        <button
          :disabled="catSubmitting || !catForm.name"
          class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
          @click="saveCategory"
        >
          {{ editingCatId ? 'Perbarui' : 'Tambah' }}
        </button>
        <button v-if="editingCatId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50" @click="editingCatId = null; catForm.name = ''">
          Batal
        </button>
      </div>

      <div v-if="loading" class="text-sm text-slate-500">Memuat...</div>
      <ul v-else class="divide-y divide-slate-100">
        <li v-for="cat in categories" :key="cat.id" class="flex items-center justify-between py-2.5 text-sm">
          <span class="text-slate-700">{{ cat.name }}</span>
          <div class="flex gap-2">
            <button class="text-xs text-indigo-500 hover:underline" @click="editCategory(cat)">Edit</button>
            <button class="text-xs text-rose-400 hover:underline" @click="deleteCategory(cat.id)">Hapus</button>
          </div>
        </li>
        <li v-if="categories.length === 0" class="py-4 text-center text-slate-400 text-sm">Belum ada kategori.</li>
      </ul>
    </div>

    <!-- User management -->
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="font-semibold text-slate-700">Manajemen Pengguna</h2>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" @click="openCreateUser">
          + Tambah User
        </button>
      </div>

      <div v-if="loading" class="text-sm text-slate-500">Memuat...</div>
      <table v-else class="w-full text-sm">
        <thead class="border-b border-slate-200 text-xs font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th class="pb-2 text-left">Nama</th>
            <th class="pb-2 text-left">Email</th>
            <th class="pb-2 text-left">Role</th>
            <th class="pb-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="user in users" :key="user.id">
            <td class="py-2.5 font-medium text-slate-800">{{ user.name }}</td>
            <td class="py-2.5 text-slate-500">{{ user.email }}</td>
            <td class="py-2.5">
              <span :class="user.role === 'admin' ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700'" class="rounded-full px-2 py-0.5 text-xs font-semibold">
                {{ user.role }}
              </span>
            </td>
            <td class="py-2.5 text-right">
              <button class="mr-3 text-xs text-indigo-500 hover:underline" @click="openEditUser(user)">Edit</button>
              <button class="text-xs text-rose-400 hover:underline" @click="deleteUser(user.id)">Hapus</button>
            </td>
          </tr>
          <tr v-if="users.length === 0">
            <td colspan="4" class="py-6 text-center text-slate-400">Belum ada pengguna.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Ganti PIN Saya (hanya admin) -->
    <div v-if="isAdmin" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="mb-1 font-semibold text-slate-700">Ganti Manager PIN Saya</h2>
      <p class="mb-4 text-xs text-slate-500">PIN ini digunakan untuk approval void, refund, dan diskon manual di kasir.</p>

      <div v-if="pinError" class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ pinError }}</div>
      <div v-if="pinSuccess" class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">{{ pinSuccess }}</div>

      <div class="grid gap-4 sm:grid-cols-3 text-sm">
        <label class="block">
          <span class="mb-1 block text-slate-600">PIN Baru *</span>
          <input
            v-model="pinForm.new_pin"
            type="password"
            inputmode="numeric"
            maxlength="20"
            placeholder="Min. 4 digit angka"
            class="w-full rounded-lg border border-slate-300 px-3 py-2"
            autocomplete="new-password"
          />
        </label>
        <label class="block">
          <span class="mb-1 block text-slate-600">Konfirmasi PIN *</span>
          <input
            v-model="pinForm.confirm_pin"
            type="password"
            inputmode="numeric"
            maxlength="20"
            placeholder="Ulangi PIN baru"
            class="w-full rounded-lg border border-slate-300 px-3 py-2"
            autocomplete="new-password"
          />
        </label>
        <div class="flex items-end">
          <button
            :disabled="pinSubmitting || !pinForm.new_pin || !pinForm.confirm_pin"
            class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
            @click="saveMyPin"
          >
            {{ pinSubmitting ? 'Menyimpan...' : 'Simpan PIN' }}
          </button>
        </div>
      </div>
    </div>

    <!-- User form modal -->
    <Transition name="fade">
      <div v-if="showUserForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
          <div class="mb-5 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-800">{{ editingUserId ? 'Edit User' : 'Tambah User' }}</h2>
            <button class="text-slate-400 hover:text-slate-600" @click="showUserForm = false">✕</button>
          </div>

          <div class="space-y-4 text-sm">
            <label class="block">
              <span class="mb-1 block text-slate-600">Nama *</span>
              <input v-model="userForm.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
            </label>
            <label class="block">
              <span class="mb-1 block text-slate-600">Email *</span>
              <input v-model="userForm.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
            </label>
            <label class="block">
              <span class="mb-1 block text-slate-600">Role</span>
              <select v-model="userForm.role" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="admin">Admin</option>
                <option value="cashier">Kasir</option>
              </select>
            </label>
            <label class="block">
              <span class="mb-1 block text-slate-600">Password {{ editingUserId ? '(kosongkan jika tidak diubah)' : '*' }}</span>
              <input v-model="userForm.password" type="password" class="w-full rounded-lg border border-slate-300 px-3 py-2" autocomplete="new-password" />
            </label>
            <label v-if="userForm.role === 'admin'" class="block">
              <span class="mb-1 block text-slate-600">Manager PIN {{ editingUserId ? '(kosongkan jika tidak diubah)' : '*' }}</span>
              <input
                v-model="userForm.manager_pin"
                type="password"
                inputmode="numeric"
                maxlength="20"
                placeholder="Min. 4 digit angka"
                class="w-full rounded-lg border border-slate-300 px-3 py-2"
                autocomplete="new-password"
              />
              <p class="mt-1 text-xs text-slate-500">Digunakan untuk approval void, refund, dan diskon manual.</p>
            </label>
          </div>

          <div class="mt-5 flex gap-3">
            <button class="flex-1 rounded-lg border border-slate-300 py-2.5 text-sm hover:bg-slate-50" @click="showUserForm = false">Batal</button>
            <button
              :disabled="userSubmitting"
              class="flex-1 rounded-lg bg-indigo-600 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
              @click="saveUser"
            >
              {{ userSubmitting ? 'Menyimpan...' : 'Simpan' }}
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
