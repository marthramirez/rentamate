<template>
  <figure class="chart-figure">
    <figcaption>
      <div class="chart-title">{{ title }}</div>
      <p v-if="note" class="chart-note">{{ note }}</p>
    </figcaption>
    <div v-if="!categories.length" class="text-muted">No data to chart yet.</div>
    <svg v-else class="chart-svg" :viewBox="`0 0 ${width} ${height}`" role="img" :aria-label="title">
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
      <g v-for="(category, index) in categories" :key="category">
        <rect
          v-for="bar in bars(index)"
          :key="bar.key"
          :x="bar.x"
          :y="bar.y"
          :width="barWidth"
          :height="bar.height"
          :fill="bar.color"
          rx="3"
        >
          <title>{{ bar.tip }}</title>
        </rect>
        <text :x="groupCenter(index)" :y="height - 10" text-anchor="middle" class="chart-axis">{{ shortLabel(category) }}</text>
      </g>
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
  categories: { type: Array, default: () => [] },
  series: { type: Array, default: () => [] },
  format: { type: String, default: 'number' },
})

const height = 280
const pad = { top: 16, right: 12, bottom: 36, left: 56 }
const barWidth = 22
const barGap = 6

const width = computed(() => Math.max(640, props.categories.length * 110))

const max = computed(() => {
  const peak = props.series.reduce((highest, item) => {
    const local = Math.max(0, ...(item.values || []).map((value) => Number(value) || 0))
    return Math.max(highest, local)
  }, 0)
  return niceMax(peak)
})

const ticks = computed(() => chartTicks(max.value, props.format))

const plotWidth = computed(() => width.value - pad.left - pad.right)

function groupCenter(index) {
  const count = Math.max(props.categories.length, 1)
  return pad.left + ((index + 0.5) / count) * plotWidth.value
}

function y(value) {
  const plot = height - pad.top - pad.bottom
  return pad.top + plot - (Number(value) / max.value) * plot
}

function shortLabel(label) {
  const text = String(label || '')
  return text.length > 14 ? `${text.slice(0, 13)}…` : text
}

function shown(value) {
  return props.format === 'money' ? formatMoney(value) : value
}

function bars(index) {
  const count = props.series.length || 1
  const groupWidth = count * barWidth + (count - 1) * barGap
  const origin = groupCenter(index) - groupWidth / 2
  const baseline = y(0)
  return props.series.map((item, seriesIndex) => {
    const value = Number(item.values?.[index]) || 0
    const top = y(value)
    return {
      key: `${item.name}-${index}`,
      x: origin + seriesIndex * (barWidth + barGap),
      y: top,
      height: Math.max(0, baseline - top),
      color: item.color,
      tip: `${props.categories[index]} · ${item.name}: ${shown(value)}`,
    }
  })
}
</script>
