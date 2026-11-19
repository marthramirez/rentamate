<template>
  <span class="badge rounded-pill text-capitalize" :class="tone">{{ label }}</span>
</template>

<script setup>
import { computed } from 'vue'
import { statusLabel } from '@/utils/format'

const props = defineProps({
  status: { type: String, default: '' },
})

const tones = {
  active: 'text-bg-success',
  inactive: 'text-bg-secondary',
  maintenance: 'text-bg-warning',
  draft: 'text-bg-secondary',
  expired: 'text-bg-dark',
  terminated: 'text-bg-dark',
  cancelled: 'text-bg-secondary',
  reserved: 'text-bg-info',
  completed: 'text-bg-secondary',
  unpaid: 'text-bg-warning',
  partially_paid: 'text-bg-info',
  paid: 'text-bg-success',
  voided: 'text-bg-secondary',
  overdue: 'text-bg-danger',
}

const tone = computed(() => tones[props.status] || 'text-bg-secondary')
const label = computed(() => statusLabel(props.status))
</script>
