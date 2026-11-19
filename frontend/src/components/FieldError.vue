<template>
  <div v-if="messages.length" class="invalid-feedback d-block">{{ messages[0] }}</div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  errors: { type: Object, default: () => ({}) },
  name: { type: String, required: true },
})

const messages = computed(() => props.errors?.[props.name] || [])
</script>
