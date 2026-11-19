<template>
  <div ref="el" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" :class="wide ? 'modal-lg' : ''">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title h5">{{ title }}</h2>
          <button type="button" class="btn-close" aria-label="Close" @click="$emit('cancel')"></button>
        </div>
        <div class="modal-body">
          <slot />
        </div>
        <div v-if="$slots.footer" class="modal-footer">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Modal } from 'bootstrap'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  wide: { type: Boolean, default: false },
})

const emit = defineEmits(['cancel'])
const el = ref(null)
let modal = null
let confirmed = false

function markConfirmed() {
  confirmed = true
}

defineExpose({ markConfirmed })

onMounted(() => {
  modal = new Modal(el.value, { backdrop: 'static', keyboard: false })
  el.value.addEventListener('hidden.bs.modal', () => {
    if (confirmed) {
      confirmed = false
      return
    }
    emit('cancel')
  })
  if (props.show) {
    modal.show()
  }
})

watch(() => props.show, (value) => {
  if (!modal) {
    return
  }
  if (value) {
    modal.show()
  } else {
    modal.hide()
  }
})

onBeforeUnmount(() => {
  modal?.hide()
  modal?.dispose()
})
</script>
