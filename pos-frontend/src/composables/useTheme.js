import { ref } from 'vue'

const THEME_KEY = 'pos_theme'

// Mirrors the inline script in index.html that sets the class before Vue mounts
// (avoids a flash of the wrong theme) — this just syncs the reactive ref to it.
const isDark = ref(document.documentElement.classList.contains('dark'))

function apply() {
  document.documentElement.classList.toggle('dark', isDark.value)
  localStorage.setItem(THEME_KEY, isDark.value ? 'dark' : 'light')
}

function toggleTheme() {
  isDark.value = !isDark.value
  apply()
}

export function useTheme() {
  return { isDark, toggleTheme }
}
