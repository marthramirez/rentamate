<template>
  <div>
    <PageHeader title="Billing and payments" subtitle="Partial payments are allowed. Overdue is calculated from the due date and remaining balance.">
      <RouterLink class="btn btn-primary" :to="{ name: 'billing-create' }">Create bill</RouterLink>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <button class="nav-link" :class="{ active: tab === 'bills' }" type="button" @click="tab = 'bills'">Bills</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" :class="{ active: tab === 'payments' }" type="button" @click="tab = 'payments'">Payment history</button>
      </li>
    </ul>

    <div v-if="tab === 'bills'">
      <form class="panel p-3 mb-3" @submit.prevent="applyBills">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label" for="bill-search">Search</label>
            <input id="bill-search" v-model="billFilters.search" class="form-control" placeholder="Number or description" />
          </div>
          <div class="col-md-3">
            <label class="form-label" for="bill-status">Status</label>
            <select id="bill-status" v-model="billFilters.status" class="form-select">
              <option value="">All</option>
              <option value="unpaid">Unpaid</option>
              <option value="partially_paid">Partially paid</option>
              <option value="overdue">Overdue</option>
              <option value="paid">Paid</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="due-from">Due from</label>
            <input id="due-from" v-model="billFilters.due_from" type="date" class="form-control" />
          </div>
          <div class="col-md-2">
            <label class="form-label" for="due-to">Due to</label>
            <input id="due-to" v-model="billFilters.due_to" type="date" class="form-control" />
          </div>
          <div class="col-md-auto">
            <button class="btn btn-outline-primary" type="submit">Apply</button>
          </div>
        </div>
      </form>
      <div class="panel">
        <LoadingBlock :loading="loadingBills" />
        <div v-if="!loadingBills" class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr>
                <th scope="col">Number</th>
                <th scope="col">Tenant</th>
                <th scope="col">Description</th>
                <th scope="col">Due</th>
                <th scope="col">Amount</th>
                <th scope="col">Paid</th>
                <th scope="col">Balance</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!bills.length">
                <td colspan="8" class="text-muted">No bills found.</td>
              </tr>
              <tr v-for="bill in bills" :key="bill.id">
                <td><RouterLink :to="{ name: 'billing-detail', params: { id: bill.id } }">{{ bill.billing_number }}</RouterLink></td>
                <td>{{ bill.lease?.tenant?.full_name }}</td>
                <td>{{ bill.description }}</td>
                <td>{{ formatDate(bill.due_date) }}</td>
                <td>{{ formatMoney(bill.amount) }}</td>
                <td>{{ formatMoney(bill.amount_paid) }}</td>
                <td>{{ formatMoney(bill.balance) }}</td>
                <td><StatusBadge :status="bill.computed_status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <PaginationBar :page="billMeta.page" :last-page="billMeta.lastPage" :total="billMeta.total" @change="loadBills" />
    </div>

    <div v-else>
      <form class="panel p-3 mb-3" @submit.prevent="loadPayments(1)">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label" for="method">Method</label>
            <select id="method" v-model="paymentFilters.payment_method" class="form-select">
              <option value="">All</option>
              <option value="cash">Cash</option>
              <option value="gcash">GCash</option>
              <option value="bank_transfer">Bank transfer</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="pay-from">From</label>
            <input id="pay-from" v-model="paymentFilters.from" type="date" class="form-control" />
          </div>
          <div class="col-md-3">
            <label class="form-label" for="pay-to">To</label>
            <input id="pay-to" v-model="paymentFilters.to" type="date" class="form-control" />
          </div>
          <div class="col-md-auto">
            <button class="btn btn-outline-primary" type="submit">Apply</button>
          </div>
        </div>
      </form>
      <div class="panel">
        <LoadingBlock :loading="loadingPayments" />
        <div v-if="!loadingPayments" class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr>
                <th scope="col">Date</th>
                <th scope="col">Tenant</th>
                <th scope="col">Bill</th>
                <th scope="col">Amount</th>
                <th scope="col">Method</th>
                <th scope="col">Reference</th>
                <th scope="col">State</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!payments.length">
                <td colspan="7" class="text-muted">No payments found.</td>
              </tr>
              <tr v-for="payment in payments" :key="payment.id">
                <td>{{ formatDate(payment.payment_date) }}</td>
                <td>{{ payment.billing_record?.lease?.tenant?.full_name }}</td>
                <td>
                  <RouterLink :to="{ name: 'billing-detail', params: { id: payment.billing_record_id } }">
                    {{ payment.billing_record?.billing_number }}
                  </RouterLink>
                </td>
                <td>{{ formatMoney(payment.amount_paid) }}</td>
                <td class="text-capitalize">{{ payment.payment_method.replace('_', ' ') }}</td>
                <td>{{ payment.payment_reference || '—' }}</td>
                <td><StatusBadge :status="payment.voided_at ? 'voided' : 'paid'" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <PaginationBar :page="paymentMeta.page" :last-page="paymentMeta.lastPage" :total="paymentMeta.total" @change="loadPayments" />
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { billingApi, paymentsApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import PageHeader from '@/components/PageHeader.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { errorMessage, pullFlash } from '@/utils/errors'
import { formatDate, formatMoney, readPage } from '@/utils/format'

const tab = ref('bills')
const banner = ref({ type: 'success', message: pullFlash() || '' })
const bills = ref([])
const payments = ref([])
const loadingBills = ref(false)
const loadingPayments = ref(false)
const billMeta = ref({ page: 1, lastPage: 1, total: 0 })
const paymentMeta = ref({ page: 1, lastPage: 1, total: 0 })
const billFilters = reactive({ search: '', status: '', due_from: '', due_to: '' })
const paymentFilters = reactive({ payment_method: '', from: '', to: '' })

async function loadBills(page = 1) {
  loadingBills.value = true
  try {
    const response = await billingApi.list({
      page,
      search: billFilters.search || undefined,
      status: billFilters.status || undefined,
      due_from: billFilters.due_from || undefined,
      due_to: billFilters.due_to || undefined,
    })
    const result = readPage(response.data)
    bills.value = result.items
    billMeta.value = result
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    loadingBills.value = false
  }
}

async function loadPayments(page = 1) {
  loadingPayments.value = true
  try {
    const response = await paymentsApi.list({
      page,
      payment_method: paymentFilters.payment_method || undefined,
      from: paymentFilters.from || undefined,
      to: paymentFilters.to || undefined,
    })
    const result = readPage(response.data)
    payments.value = result.items
    paymentMeta.value = result
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    loadingPayments.value = false
  }
}

function applyBills() {
  loadBills(1)
}

onMounted(() => {
  loadBills(1)
  loadPayments(1)
})
</script>
