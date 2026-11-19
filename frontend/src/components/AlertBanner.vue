<template>
  <div v-if="message" class="alert alert-dismissible" :class="`alert-${type}`" role="alert">
    {{ message }}
    <button type="button" class="btn-close" aria-label="Dismiss" @click="$emit('close')"></button>
  </div>
</template>

<script setup>
defineProps({
  type: { type: String, default: 'success' },
  message: { type: String, default: '' },
})

defineEmits(['close'])
</script>
