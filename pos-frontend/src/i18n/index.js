import { createI18n } from 'vue-i18n'
import id from './locales/id'
import en from './locales/en'

const LOCALE_KEY = 'pos_locale'

function getInitialLocale() {
  const saved = localStorage.getItem(LOCALE_KEY)
  return saved === 'en' ? 'en' : 'id'
}

const i18n = createI18n({
  legacy: false,
  locale: getInitialLocale(),
  fallbackLocale: 'id',
  messages: { id, en },
})

export function toggleLocale() {
  const next = i18n.global.locale.value === 'id' ? 'en' : 'id'
  i18n.global.locale.value = next
  localStorage.setItem(LOCALE_KEY, next)
}

export default i18n
