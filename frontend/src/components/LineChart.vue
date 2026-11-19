<template>
  <figure class="chart-figure">
    <figcaption>
      <div class="chart-title">{{ title }}</div>
      <p v-if="note" class="chart-note">{{ note }}</p>
    </figcaption>
    <svg class="chart-svg" :viewBox="`0 0 ${width} ${height}`" role="img" :aria-label="title">
      <line
        v-for="tick in ticks"
        :key="tick"
        :x1="pad.left"
        :x2="width - pad.right"
        :y1="y(tick)"
        :y2="y(tick)"
        class="chart-grid"
      />
      <text
        v-for="tick in ticks"
        :key="`label-${tick}`"
        :x="pad.left - 8"
        :y="y(tick) + 4"
        text-anchor="end"
        class="chart-axis"
      >{{ formatTick(tick, format) }}</text>
      <polyline
        v-for="item in drawn"
        :key="item.name"
        :points="item.points"
        fill="none"
        :stroke="item.color"
        stroke-width="2.5"
        stroke-linejoin="round"
        stroke-linecap="round"
      />
      <g v-for="item in drawn" :key="`${item.name}-points`">
        <circle
          v-for="point in item.circles"
          :key="point.key"
          :cx="point.x"
          :cy="point.y"
          r="4"
          :fill="item.color"
        >
          <title>{{ point.tip }}</title>
        </circle>
      </g>
      <text
        v-for="(label, index) in labels"
        :key="label"
        :x="x(index)"
        :y="height - 10"
        text-anchor="middle"
        class="chart-axis"
      >{{ axisLabel(label) }}</text>
    </svg>
    <ul class="chart-legend">
      <li v-for="item in series" :key="item.name">
        <span class="chart-swatch" :style="{ background: item.color }"></span>
        {{ item.name }}
      </li>
    </ul>
  </figure>
</template>

<script setup>
import { computed } from 'vue'
import { formatMoney } from '@/utils/format'
import { chartTicks, formatTick, niceMax } from '@/utils/chartScale'

const props = defineProps({
  title: { type: String, required: true },
  note: { type: String, default: '' },
  labels: { type: Array, default: () => [] },
  series: { type: Array, default: () => [] },
  format: { type: String, default: 'number' },
})

const width = 640
const height = 280
const pad = { top: 16, right: 12, bottom: 36, left: 56 }

const max = computed(() => {
  const peak = props.series.reduce((highest, item) => {
    const local = Math.max(0, ...(item.values || []).map((value) => Number(value) || 0))
    return Math.max(highest, local)
  }, 0)
  return niceMax(peak)
})

const ticks = computed(() => chartTicks(max.value, props.format))

function x(index) {
  const count = props.labels.length
  if (count <= 1) {
    return pad.left + (width - pad.left - pad.right) / 2
  }
  return pad.left + (index / (count - 1)) * (width - pad.left - pad.right)
}

function y(value) {
  const plot = height - pad.top - pad.bottom
  return pad.top + plot - (Number(value) / max.value) * plot
}

function axisLabel(label) {
  return String(label || '').split(' ')[0]
}

function tip(label, value) {
  const shown = props.format === 'money' ? formatMoney(value) : value
  return `${label}: ${shown}`
}

const drawn = computed(() => props.series.map((item) => {
  const circles = (item.values || []).map((value, index) => ({
    key: `${item.name}-${index}`,
    x: x(index),
    y: y(value),
    tip: tip(props.labels[index] || '', value),
  }))
  return {
    name: item.name,
    color: item.color,
    points: circles.map((point) => `${point.x},${point.y}`).join(' '),
    circles,
  }
}))
</script>
