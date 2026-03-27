<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

const form = ref({
  email: 'admin@pos.local',
  password: 'password',
  device_name: 'pos-web',
})

const errorMessage = ref('')

async function submitLogin() {
  errorMessage.value = ''
  const result = await auth.login(form.value)

  if (!result.ok) {
    errorMessage.value = result.message
    return
  }

  router.push('/pos')
}
</script>

<template>
  <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-4">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(99,102,241,0.35),transparent_35%),radial-gradient(circle_at_80%_0%,rgba(16,185,129,0.25),transparent_35%)]"></div>

    <section class="relative w-full max-w-md rounded-3xl border border-white/10 bg-white/10 p-8 shadow-2xl backdrop-blur-xl">
      <p class="text-xs uppercase tracking-[0.2em] text-indigo-200">POS Professional</p>
      <h1 class="mt-2 text-3xl font-black text-white">Masuk Kasir</h1>
      <p class="mt-2 text-sm text-slate-200">Gunakan akun kasir/admin untuk mengakses dashboard POS.</p>

      <form class="mt-6 space-y-4" @submit.prevent="submitLogin">
        <label class="block">
          <span class="mb-1.5 block text-sm font-medium text-slate-200">Email</span>
          <input
            v-model="form.email"
            type="email"
            required
            class="w-full rounded-xl border border-white/20 bg-slate-900/60 px-4 py-3 text-slate-100 outline-none transition focus:border-indigo-400"
          />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-sm font-medium text-slate-200">Password</span>
          <input
            v-model="form.password"
            type="password"
            required
            class="w-full rounded-xl border border-white/20 bg-slate-900/60 px-4 py-3 text-slate-100 outline-none transition focus:border-indigo-400"
          />
        </label>

        <p v-if="errorMessage" class="rounded-xl border border-rose-300/40 bg-rose-500/20 px-3 py-2 text-sm text-rose-100">
          {{ errorMessage }}
        </p>

        <button
          type="submit"
          :disabled="auth.loading"
          class="w-full rounded-xl bg-indigo-500 px-4 py-3 text-sm font-bold text-white transition hover:bg-indigo-400 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {{ auth.loading ? 'Memproses...' : 'Login' }}
        </button>
      </form>

      <p class="mt-4 text-xs text-slate-300">
        Demo: admin@pos.local / password
      </p>
    </section>
  </div>
</template>
