<script setup>
import { RouterLink } from 'vue-router'

const highlights = [
  { title: 'Checkout Cepat', desc: 'Alur kasir responsif dengan hold order dan recall order.' },
  { title: 'Settlement Shift', desc: 'Kontrol kas fisik vs sistem secara akurat di akhir shift.' },
  { title: 'Laporan Visual', desc: 'Grafik penjualan harian dan produk terlaris berbasis data real-time.' },
]
</script>

<template>
  <div class="relative min-h-screen overflow-hidden bg-slate-950 text-white">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_10%,rgba(59,130,246,0.35),transparent_35%),radial-gradient(circle_at_75%_0%,rgba(16,185,129,0.28),transparent_40%),radial-gradient(circle_at_50%_100%,rgba(139,92,246,0.25),transparent_35%)]"></div>

    <header class="relative z-10 mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
      <div>
        <p class="text-xs uppercase tracking-[0.3em] text-indigo-200">POS Professional</p>
        <h1 class="text-lg font-black tracking-tight">POS PRO SUITE</h1>
      </div>
      <RouterLink
        to="/login"
        class="rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold backdrop-blur hover:bg-white/20"
      >
        Login
      </RouterLink>
    </header>

    <main class="relative z-10 mx-auto grid w-full max-w-7xl gap-10 px-6 pb-16 pt-6 lg:grid-cols-[1.3fr_1fr] lg:px-8 lg:pt-12">
      <section>
        <p class="inline-block rounded-full border border-emerald-300/30 bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">
          Siap Pakai untuk Operasional Harian
        </p>
        <h2 class="mt-5 text-4xl font-black leading-tight md:text-5xl">
          Satu Dashboard POS
          <span class="bg-gradient-to-r from-cyan-300 to-indigo-300 bg-clip-text text-transparent"> untuk Kasir, Admin, dan Report</span>
        </h2>
        <p class="mt-5 max-w-2xl text-base text-slate-200/90 md:text-lg">
          Kelola transaksi, stok, settlement, dan analitik penjualan dengan pengalaman kasir yang cepat dan UI modern.
        </p>

        <div class="mt-8 flex flex-wrap gap-3">
          <RouterLink
            to="/login"
            class="rounded-xl bg-indigo-500 px-5 py-3 text-sm font-bold text-white transition hover:bg-indigo-400"
          >
            Mulai Sekarang
          </RouterLink>
          <RouterLink
            to="/history"
            class="rounded-xl border border-white/25 bg-white/10 px-5 py-3 text-sm font-semibold text-white/90 backdrop-blur hover:bg-white/20"
          >
            Lihat Riwayat (Demo)
          </RouterLink>
        </div>

        <div class="mt-10 grid gap-4 sm:grid-cols-3">
          <div class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
            <p class="text-xs uppercase tracking-wide text-slate-300">Latency UI</p>
            <p class="mt-2 text-2xl font-black text-cyan-300">Fast</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
            <p class="text-xs uppercase tracking-wide text-slate-300">Reliability</p>
            <p class="mt-2 text-2xl font-black text-emerald-300">Stable</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
            <p class="text-xs uppercase tracking-wide text-slate-300">Security</p>
            <p class="mt-2 text-2xl font-black text-violet-300">Token Auth</p>
          </div>
        </div>
      </section>

      <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-2xl backdrop-blur-xl">
        <h3 class="text-lg font-bold">Fitur Utama</h3>
        <div class="mt-4 space-y-3">
          <article
            v-for="item in highlights"
            :key="item.title"
            class="rounded-xl border border-white/10 bg-slate-900/40 p-4"
          >
            <p class="font-semibold text-indigo-200">{{ item.title }}</p>
            <p class="mt-1 text-sm text-slate-200">{{ item.desc }}</p>
          </article>
        </div>

        <div class="mt-6 rounded-xl border border-cyan-300/30 bg-cyan-400/10 p-4">
          <p class="text-xs uppercase tracking-wide text-cyan-200">Status Sistem</p>
          <p class="mt-1 text-sm text-cyan-50">Backend API + Frontend siap untuk operasional toko.</p>
        </div>
      </section>
    </main>
  </div>
</template>
