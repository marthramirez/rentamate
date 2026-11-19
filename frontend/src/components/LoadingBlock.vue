<template>
  <div v-if="loading" class="text-center text-muted py-4">
    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
    <span class="ms-2">Loading…</span>
  </div>
</template>

<script setup>
defineProps({
  loading: { type: Boolean, default: false },
})
</script>
