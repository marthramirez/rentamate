<template>
  <div>
    <PageHeader title="Dashboard" subtitle="Operational summary from current records.">
      <RouterLink class="btn btn-primary" :to="{ name: 'property-create' }">Add property</RouterLink>
      <RouterLink class="btn btn-outline-primary" :to="{ name: 'allocation' }">Allocate room</RouterLink>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <LoadingBlock :loading="loading" />
    <div v-if="summary" class="row g-3">
      <div v-for="card in cards" :key="card.label" class="col-sm-6 col-xl-4">
        <div class="summary-card">
          <div class="label">{{ card.label }}</div>
          <div class="value">{{ card.value }}</div>
          <div v-if="card.hint" class="hint">{{ card.hint }}</div>
        </div>
      </div>
    </div>
    <div v-if="report" class="row g-3 mt-1">
      <div class="col-12">
        <div class="panel p-3">
          <LineChart
            title="Charges and collections"
            note="The last six months. Charges use the billing date. Collections use the payment date and leave out voided payments and cancelled bills."
            :labels="monthLabels"
            :series="moneySeries"
            format="money"
          />
        </div>
      </div>
      <div class="col-lg-7">
        <div class="panel p-3 h-100">
          <BarChart
            title="Occupancy by property"
            note="Active stays compared with the total room capacity."
            :categories="occupancyLabels"
            :series="occupancySeries"
          />
        </div>
      </div>
      <div class="col-lg-5">
        <div class="panel p-3 h-100">
          <BarChart
            title="Bills by status"
            note="Stored bill status. Overdue bills stay under unpaid or partially paid."
            :categories="billLabels"
            :series="billSeries"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { analyticsApi, dashboardApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import BarChart from '@/components/BarChart.vue'
import LineChart from '@/components/LineChart.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import PageHeader from '@/components/PageHeader.vue'
import { errorMessage } from '@/utils/errors'
import { formatMoney } from '@/utils/format'

const loading = ref(true)
const summary = ref(null)
const report = ref(null)
const banner = ref({ type: 'danger', message: '' })

const cards = computed(() => {
  const data = summary.value
  if (!data) {
    return []
  }
  return [
    { label: 'Properties', value: data.properties, hint: `${data.active_properties} active` },
    { label: 'Rooms', value: data.rooms },
    {
      label: 'Occupied spaces',
      value: `${data.occupied_spaces} / ${data.total_capacity}`,
      hint: data.reserved_spaces ? `${data.reserved_spaces} reserved` : 'Active stays / total capacity',
    },
    { label: 'Active tenants', value: data.active_tenants, hint: 'Tenants with an active lease' },
    { label: 'Unpaid bills', value: data.unpaid_bills, hint: 'Unpaid and partially paid' },
    { label: 'Overdue bills', value: data.overdue_bills, hint: 'Past due with a balance' },
    { label: 'Outstanding balance', value: formatMoney(data.outstanding_balance) },
  ]
})

const monthLabels = computed(() => (report.value?.months || []).map((month) => month.label))
const moneySeries = computed(() => [
  { name: 'Billed', color: '#93c5fd', values: (report.value?.months || []).map((month) => month.billed) },
  { name: 'Collected', color: '#2563eb', values: (report.value?.months || []).map((month) => month.collected) },
])
const occupancyLabels = computed(() => (report.value?.occupancy || []).map((row) => row.property))
const occupancySeries = computed(() => [
  { name: 'Occupied', color: '#2563eb', values: (report.value?.occupancy || []).map((row) => row.occupied) },
  { name: 'Capacity', color: '#bfdbfe', values: (report.value?.occupancy || []).map((row) => row.capacity) },
])
const billLabels = ['Unpaid', 'Partial', 'Paid', 'Cancelled']
const billSeries = computed(() => {
  const counts = report.value?.bill_counts || {}
  return [{
    name: 'Bills',
    color: '#1e40af',
    values: [counts.unpaid, counts.partially_paid, counts.paid, counts.cancelled],
  }]
})

onMounted(async () => {
  const [summaryResult, reportResult] = await Promise.allSettled([
    dashboardApi.get(),
    analyticsApi.get(),
  ])
  if (summaryResult.status === 'fulfilled') {
    summary.value = summaryResult.value.data.data
  }
  if (reportResult.status === 'fulfilled') {
    report.value = reportResult.value.data.data
  }
  const failed = [summaryResult, reportResult].find((result) => result.status === 'rejected')
  if (failed) {
    banner.value = { type: 'danger', message: errorMessage(failed.reason) }
  }
  loading.value = false
})
</script>
