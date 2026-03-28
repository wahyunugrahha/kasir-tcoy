<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../services/api'

const products = ref([])
const categories = ref([])
const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const successMsg = ref('')
const showForm = ref(false)
const editingId = ref(null)
const BACKEND_URL = import.meta.env.VITE_API_BASE_URL?.replace('/api', '') ?? 'http://127.0.0.1:8000'
const LOW_STOCK_THRESHOLD = 5

const lowStockProducts = computed(() =>
  products.value.filter((p) => p.stock > 0 && p.stock <= LOW_STOCK_THRESHOLD)
)

const form = ref({
  category_id: '',
  sku: '',
  name: '',
  cost_price: '',
  selling_price: '',
  stock: 0,
  description: '',
  image: null,
})

const imagePreview = ref(null)

async function loadData() {
  loading.value = true
  error.value = ''
  try {
    const [prodRes, catRes] = await Promise.all([
      api.get('/v1/products', { params: { per_page: 100 } }),
      api.get('/v1/categories'),
    ])
    products.value = prodRes.data.data ?? []
    categories.value = catRes.data.data ?? catRes.data ?? []
  } catch {
    error.value = 'Gagal memuat data.'
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = { category_id: '', sku: '', name: '', cost_price: '', selling_price: '', stock: 0, description: '', image: null }
  imagePreview.value = null
  showForm.value = true
}

function openEdit(product) {
  editingId.value = product.id
  form.value = {
    category_id: product.category_id,
    sku: product.sku,
    name: product.name,
    cost_price: product.cost_price,
    selling_price: product.selling_price,
    stock: product.stock,
    description: product.description ?? '',
    image: null,
  }
  imagePreview.value = product.image_url ? resolveImageUrl(product.image_url) : null
  showForm.value = true
}

function resolveImageUrl(url) {
  if (!url) return null
  if (url.startsWith('http')) return url
  return BACKEND_URL + url
}

function handleImageChange(event) {
  const file = event.target.files?.[0]
  if (!file) return
  form.value.image = file
  imagePreview.value = URL.createObjectURL(file)
}

async function saveProduct() {
  error.value = ''
  successMsg.value = ''
  submitting.value = true

  try {
    const fd = new FormData()
    fd.append('category_id', form.value.category_id)
    fd.append('sku', form.value.sku)
    fd.append('name', form.value.name)
    fd.append('cost_price', form.value.cost_price)
    fd.append('selling_price', form.value.selling_price)
    fd.append('stock', form.value.stock)
    fd.append('description', form.value.description)
    if (form.value.image) {
      fd.append('image', form.value.image)
    }

    if (editingId.value) {
      fd.append('_method', 'PUT')
      await api.post(`/v1/products/${editingId.value}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      successMsg.value = 'Produk berhasil diperbarui!'
    } else {
      await api.post('/v1/products', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      successMsg.value = 'Produk berhasil ditambahkan!'
    }

    showForm.value = false
    await loadData()
  } catch (e) {
    const errData = e.response?.data
    if (errData?.errors) {
      error.value = Object.values(errData.errors).flat().join(', ')
    } else {
      error.value = errData?.message ?? 'Gagal menyimpan produk.'
    }
  } finally {
    submitting.value = false
  }
}

async function deleteProduct(id) {
  if (!confirm('Hapus produk ini?')) return
  try {
    await api.delete(`/v1/products/${id}`)
    await loadData()
  } catch {
    error.value = 'Gagal menghapus produk.'
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0))
}

onMounted(loadData)
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-5 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Manajemen Produk</h1>
        <p class="text-sm text-slate-500">{{ products.length }} produk terdaftar</p>
      </div>
      <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" @click="openCreate">
        + Tambah Produk
      </button>
    </div>

    <div v-if="error" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
    <div v-if="successMsg" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ successMsg }}</div>

    <!-- Low stock alert banner -->
    <div v-if="lowStockProducts.length > 0" class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      <p class="font-semibold">&#9888; {{ lowStockProducts.length }} produk hampir habis (stok &le; {{ LOW_STOCK_THRESHOLD }})</p>
      <p class="mt-1 text-xs">{{ lowStockProducts.map((p) => `${p.name} (${p.stock})`).join(', ') }}</p>
    </div>

    <!-- Product grid -->
    <div v-if="loading" class="py-12 text-center text-slate-500">Memuat produk...</div>

    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
      <div
        v-for="product in products"
        :key="product.id"
        class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md"
      >
        <!-- Product image -->
        <div class="relative h-40 bg-slate-100">
          <img
            v-if="product.image_url"
            :src="resolveImageUrl(product.image_url)"
            :alt="product.name"
            class="h-full w-full object-cover"
          />
          <div v-else class="flex h-full items-center justify-center text-4xl text-slate-300">📦</div>
          <span
            class="absolute right-2 top-2 rounded-full px-2 py-0.5 text-xs font-semibold shadow-sm"
            :class="product.stock <= 0
              ? 'bg-rose-500 text-white'
              : product.stock <= 5
                ? 'bg-amber-400 text-white'
                : 'bg-white/80 text-slate-600'"
          >
            Stok: {{ product.stock }}
          </span>
        </div>

        <!-- Product info -->
        <div class="p-4">
          <p class="font-semibold text-slate-800">{{ product.name }}</p>
          <p class="text-xs text-slate-400">{{ product.sku }} · {{ product.category?.name }}</p>
          <p class="mt-1.5 text-base font-bold text-indigo-600">{{ formatCurrency(product.selling_price) }}</p>

          <!-- Action buttons -->
          <div class="mt-3 flex gap-2">
            <button
              class="flex-1 rounded-lg border border-slate-300 py-1.5 text-xs font-medium hover:bg-slate-50"
              @click="openEdit(product)"
            >
              Edit
            </button>
            <button
              class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-500 hover:bg-rose-50"
              @click="deleteProduct(product.id)"
            >
              Hapus
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal form -->
    <Transition name="fade">
      <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl max-h-[90vh]">
          <div class="mb-5 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-800">{{ editingId ? 'Edit Produk' : 'Tambah Produk' }}</h2>
            <button class="text-slate-400 hover:text-slate-600" @click="showForm = false">✕</button>
          </div>

          <div class="space-y-4 text-sm">
            <!-- Image upload -->
            <div>
              <label class="mb-1 block text-slate-600">Gambar Produk</label>
              <div class="flex gap-4">
                <div class="h-20 w-20 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                  <img v-if="imagePreview" :src="imagePreview" class="h-full w-full object-cover" />
                  <div v-else class="flex h-full items-center justify-center text-2xl text-slate-300">📷</div>
                </div>
                <div>
                  <input type="file" accept="image/*" class="text-xs" @change="handleImageChange" />
                  <p class="mt-1 text-xs text-slate-400">Max 2MB, format JPG/PNG/WebP</p>
                </div>
              </div>
            </div>

            <label class="block">
              <span class="mb-1 block text-slate-600">Kategori *</span>
              <select v-model="form.category_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="">-- Pilih Kategori --</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </label>

            <div class="grid grid-cols-2 gap-3">
              <label class="block">
                <span class="mb-1 block text-slate-600">SKU *</span>
                <input v-model="form.sku" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
              </label>
              <label class="block">
                <span class="mb-1 block text-slate-600">Stok</span>
                <input v-model.number="form.stock" type="number" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
              </label>
            </div>

            <label class="block">
              <span class="mb-1 block text-slate-600">Nama Produk *</span>
              <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
            </label>

            <div class="grid grid-cols-2 gap-3">
              <label class="block">
                <span class="mb-1 block text-slate-600">Harga Modal *</span>
                <input v-model.number="form.cost_price" type="number" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
              </label>
              <label class="block">
                <span class="mb-1 block text-slate-600">Harga Jual *</span>
                <input v-model.number="form.selling_price" type="number" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
              </label>
            </div>

            <label class="block">
              <span class="mb-1 block text-slate-600">Deskripsi</span>
              <textarea v-model="form.description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
            </label>
          </div>

          <div class="mt-5 flex gap-3">
            <button class="flex-1 rounded-lg border border-slate-300 py-2.5 text-sm hover:bg-slate-50" @click="showForm = false">
              Batal
            </button>
            <button
              :disabled="submitting"
              class="flex-1 rounded-lg bg-indigo-600 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
              @click="saveProduct"
            >
              {{ submitting ? 'Menyimpan...' : (editingId ? 'Simpan Perubahan' : 'Tambah Produk') }}
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
