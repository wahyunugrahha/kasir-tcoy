import { onUnmounted, watch } from 'vue'

/** Closes a modal on Escape while it's open — wire `isOpenRef` to the same ref that gates `v-if` on the modal. */
export function useEscToClose(isOpenRef, onClose) {
  function handleKeydown(event) {
    if (event.key === 'Escape') {
      onClose()
    }
  }

  watch(isOpenRef, (open) => {
    if (open) {
      window.addEventListener('keydown', handleKeydown)
    } else {
      window.removeEventListener('keydown', handleKeydown)
    }
  }, { immediate: true })

  onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
  })
}
