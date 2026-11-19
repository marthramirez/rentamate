<template>
  <div v-if="lastPage > 1" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
    <span class="small text-muted">{{ total }} records</span>
    <div class="btn-group">
      <button class="btn btn-outline-primary btn-sm" type="button" :disabled="page <= 1" @click="$emit('change', page - 1)">Previous</button>
      <span class="btn btn-outline-primary btn-sm disabled">{{ page }} / {{ lastPage }}</span>
      <button class="btn btn-outline-primary btn-sm" type="button" :disabled="page >= lastPage" @click="$emit('change', page + 1)">Next</button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  page: { type: Number, default: 1 },
  lastPage: { type: Number, default: 1 },
  total: { type: Number, default: 0 },
})

defineEmits(['change'])
</script>
