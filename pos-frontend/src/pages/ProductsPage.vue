<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../services/api'
import { useEscToClose } from '../composables/useEscToClose'

const { t } = useI18n()

const products = ref([])
const categories = ref([])
const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const successMsg = ref('')
const showForm = ref(false)
const editingId = ref(null)
const viewMode = ref('grid')

useEscToClose(showForm, () => { showForm.value = false })
const BACKEND_URL = import.meta.env.VITE_API_BASE_URL?.replace('/api', '') ?? 'http://127.0.0.1:8000'

const lowStockProducts = computed(() =>
  products.value.filter((p) => p.stock > 0 && p.stock <= Number(p.min_stock ?? 5))
)

const form = ref({
  category_id: '',
  sku: '',
  name: '',
  cost_price: '',
  selling_price: '',
  stock: 0,
  min_stock: 5,
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
    error.value = t('products.loadError')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = { category_id: '', sku: '', name: '', cost_price: '', selling_price: '', stock: 0, min_stock: 5, description: '', image: null }
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
    min_stock: product.min_stock ?? 5,
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
    fd.append('min_stock', form.value.min_stock)
    fd.append('description', form.value.description)
    if (form.value.image) {
      fd.append('image', form.value.image)
    }

    if (editingId.value) {
      fd.append('_method', 'PUT')
      await api.post(`/v1/products/${editingId.value}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      successMsg.value = t('products.updateSuccess')
    } else {
      await api.post('/v1/products', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      successMsg.value = t('products.createSuccess')
    }

    showForm.value = false
    await loadData()
  } catch (e) {
    const errData = e.response?.data
    if (errData?.errors) {
      error.value = Object.values(errData.errors).flat().join(', ')
    } else {
      error.value = errData?.message ?? t('products.saveError')
    }
  } finally {
    submitting.value = false
  }
}

async function deleteProduct(id) {
  if (!confirm(t('products.confirmDelete'))) return
  try {
    await api.delete(`/v1/products/${id}`)
    await loadData()
  } catch (e) {
    error.value = e.response?.data?.message ?? t('products.deleteError')
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
        <h1 class="text-xl font-bold text-ink">{{ t('products.title') }}</h1>
        <p class="text-sm text-ink-faint">{{ t('products.countRegistered', { count: products.length }) }}</p>
      </div>
      <div class="flex items-center gap-2">
        <div class="flex items-center gap-0.5 rounded-lg border border-line bg-surface p-0.5">
          <button
            type="button"
            :aria-label="t('products.gridView')"
            class="grid h-8 w-8 place-items-center rounded-md transition"
            :class="viewMode === 'grid' ? 'bg-brand-600 text-white' : 'text-ink-soft hover:bg-surface-2'"
            @click="viewMode = 'grid'"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zm0 9h7v7h-7v-7zM4 13h7v7H4v-7z" />
            </svg>
          </button>
          <button
            type="button"
            :aria-label="t('products.listView')"
            class="grid h-8 w-8 place-items-center rounded-md transition"
            :class="viewMode === 'list' ? 'bg-brand-600 text-white' : 'text-ink-soft hover:bg-surface-2'"
            @click="viewMode = 'list'"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        </div>
        <button class="rounded-full bg-brand-600 active:scale-95 transition-transform px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500" @click="openCreate">
          + {{ t('products.addProduct') }}
        </button>
      </div>
    </div>

    <div v-if="error" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</div>
    <div v-if="successMsg" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ successMsg }}</div>

    <!-- Low stock alert banner -->
    <div v-if="lowStockProducts.length > 0" class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      <p class="font-semibold">&#9888; {{ t('products.lowStockBanner', { count: lowStockProducts.length }) }}</p>
      <p class="mt-1 text-xs">{{ lowStockProducts.map((p) => `${p.name} (${p.stock}/${p.min_stock})`).join(', ') }}</p>
    </div>

    <!-- Product grid -->
    <div v-if="loading" class="py-12 text-center text-ink-faint">{{ t('products.loading') }}</div>

    <div v-else-if="viewMode === 'grid'" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
      <div
        v-for="product in products"
        :key="product.id"
        class="group overflow-hidden rounded-2xl border border-line-soft bg-surface shadow-sm transition-shadow hover:shadow-md"
      >
        <!-- Product image -->
        <div class="relative h-40 bg-surface-2">
          <img
            v-if="product.image_url"
            :src="resolveImageUrl(product.image_url)"
            :alt="product.name"
            class="h-full w-full object-cover"
          />
          <div v-else class="flex h-full items-center justify-center text-slate-300">
            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 7l8-4 8 4-8 4-8-4zm0 0v10l8 4 8-4V7" />
            </svg>
          </div>
          <span
            class="absolute right-2 top-2 rounded-full px-2 py-0.5 text-xs font-semibold shadow-sm"
            :class="product.stock <= 0
              ? 'bg-rose-500 text-white'
              : product.stock <= Number(product.min_stock ?? 5)
                ? 'bg-amber-400 text-white'
                : 'bg-surface/80 text-ink-soft'"
          >
            {{ t('products.stockLabel', { stock: product.stock }) }}
          </span>
        </div>

        <!-- Product info -->
        <div class="p-4">
          <p class="font-semibold text-ink">{{ product.name }}</p>
          <p class="text-xs text-ink-faint">{{ product.sku }} · {{ product.category?.name }}</p>
          <p class="mt-1.5 text-base font-bold text-brand-600">{{ formatCurrency(product.selling_price) }}</p>

          <!-- Action buttons -->
          <div class="mt-3 flex gap-2">
            <button
              class="flex-1 rounded-lg border border-line py-1.5 text-xs font-medium hover:bg-surface-2"
              @click="openEdit(product)"
            >
              {{ t('products.edit') }}
            </button>
            <button
              class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950"
              @click="deleteProduct(product.id)"
            >
              {{ t('products.delete') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="space-y-2">
      <div
        v-for="product in products"
        :key="product.id"
        class="flex items-center gap-3 rounded-xl border border-line-soft bg-surface p-3 shadow-sm"
      >
        <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-surface-2">
          <img
            v-if="product.image_url"
            :src="resolveImageUrl(product.image_url)"
            :alt="product.name"
            class="h-full w-full object-cover"
          />
          <div v-else class="flex h-full items-center justify-center text-slate-300">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 7l8-4 8 4-8 4-8-4zm0 0v10l8 4 8-4V7" />
            </svg>
          </div>
        </div>

        <div class="min-w-0 flex-1">
          <p class="truncate font-semibold text-ink">{{ product.name }}</p>
          <p class="text-xs text-ink-faint">{{ product.sku }} · {{ product.category?.name }}</p>
          <p class="text-sm font-bold text-brand-600">{{ formatCurrency(product.selling_price) }}</p>
        </div>

        <span
          class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold shadow-sm"
          :class="product.stock <= 0
            ? 'bg-rose-500 text-white'
            : product.stock <= Number(product.min_stock ?? 5)
              ? 'bg-amber-400 text-white'
              : 'bg-surface-2 text-ink-soft'"
        >
          {{ t('products.stockLabel', { stock: product.stock }) }}
        </span>

        <div class="flex shrink-0 gap-2">
          <button
            class="rounded-lg border border-line px-3 py-1.5 text-xs font-medium hover:bg-surface-2"
            @click="openEdit(product)"
          >
            {{ t('products.edit') }}
          </button>
          <button
            class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950"
            @click="deleteProduct(product.id)"
          >
            {{ t('products.delete') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal form -->
    <Transition name="fade">
      <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div role="dialog" aria-modal="true" aria-labelledby="product-form-title" class="w-full max-w-lg overflow-y-auto rounded-2xl bg-surface p-6 shadow-2xl max-h-[90vh]">
          <div class="mb-5 flex items-center justify-between">
            <h2 id="product-form-title" class="text-lg font-bold text-ink">{{ editingId ? t('products.editTitle') : t('products.addTitle') }}</h2>
            <button :aria-label="t('products.close')" class="text-ink-faint hover:text-ink-soft" @click="showForm = false">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="space-y-4 text-sm">
            <!-- Image upload -->
            <div>
              <label class="mb-1 block text-ink-soft">{{ t('products.productImage') }}</label>
              <div class="flex gap-4">
                <div class="h-20 w-20 overflow-hidden rounded-lg border border-line-soft bg-surface-2">
                  <img v-if="imagePreview" :src="imagePreview" class="h-full w-full object-cover" />
                  <div v-else class="flex h-full items-center justify-center text-slate-300">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h1.5l1-1.5h9l1 1.5H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                      <circle cx="12" cy="13.5" r="3.2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                  </div>
                </div>
                <div>
                  <input type="file" accept="image/*" class="text-xs" @change="handleImageChange" />
                  <p class="mt-1 text-xs text-ink-faint">{{ t('products.imageHint') }}</p>
                </div>
              </div>
            </div>

            <label class="block">
              <span class="mb-1 block text-ink-soft">{{ t('products.category') }}</span>
              <select v-model="form.category_id" class="w-full rounded-lg border border-line px-3 py-2">
                <option value="">{{ t('products.selectCategory') }}</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </label>

            <div class="grid grid-cols-2 gap-3">
              <label class="block">
                <span class="mb-1 block text-ink-soft">{{ t('products.sku') }}</span>
                <input v-model="form.sku" type="text" class="w-full rounded-lg border border-line px-3 py-2" />
              </label>
              <label class="block">
                <span class="mb-1 block text-ink-soft">{{ t('products.stock') }}</span>
                <input v-model.number="form.stock" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2" />
              </label>
            </div>

            <label class="block">
              <span class="mb-1 block text-ink-soft">{{ t('products.reorderPoint') }}</span>
              <input v-model.number="form.min_stock" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2" />
            </label>

            <label class="block">
              <span class="mb-1 block text-ink-soft">{{ t('products.productName') }}</span>
              <input v-model="form.name" type="text" class="w-full rounded-lg border border-line px-3 py-2" />
            </label>

            <div class="grid grid-cols-2 gap-3">
              <label class="block">
                <span class="mb-1 block text-ink-soft">{{ t('products.costPrice') }}</span>
                <input v-model.number="form.cost_price" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2" />
              </label>
              <label class="block">
                <span class="mb-1 block text-ink-soft">{{ t('products.sellingPrice') }}</span>
                <input v-model.number="form.selling_price" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2" />
              </label>
            </div>

            <label class="block">
              <span class="mb-1 block text-ink-soft">{{ t('products.description') }}</span>
              <textarea v-model="form.description" rows="2" class="w-full rounded-lg border border-line px-3 py-2"></textarea>
            </label>
          </div>

          <div class="mt-5 flex gap-3">
            <button class="flex-1 rounded-lg border border-line py-2.5 text-sm hover:bg-surface-2" @click="showForm = false">
              {{ t('products.cancel') }}
            </button>
            <button
              :disabled="submitting"
              class="flex-1 rounded-full bg-brand-600 active:scale-95 transition-transform py-2.5 text-sm font-semibold text-white hover:bg-brand-500 disabled:opacity-50"
              @click="saveProduct"
            >
              {{ submitting ? t('products.saving') : (editingId ? t('products.saveChanges') : t('products.addProduct')) }}
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
