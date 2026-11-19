<template>
  <div>
    <PageHeader :title="bill ? bill.billing_number : 'Bill'" :subtitle="bill ? bill.description : ''">
      <RouterLink class="btn btn-outline-primary" :to="{ name: 'billing' }">Back</RouterLink>
      <button v-if="bill && bill.status !== 'cancelled' && Number(bill.amount_paid) === 0" class="btn btn-outline-danger" type="button" @click="cancelOpen = true">Cancel bill</button>
    </PageHeader>
    <AlertBanner :type="banner.type" :message="banner.message" @close="banner.message = ''" />
    <LoadingBlock :loading="loading" />
    <div v-if="bill" class="row g-3">
      <div class="col-lg-4">
        <div class="panel p-3">
          <dl class="mb-0">
            <dt>Tenant</dt>
            <dd>{{ bill.lease?.tenant?.full_name }}</dd>
            <dt>Lease</dt>
            <dd><RouterLink :to="{ name: 'lease-detail', params: { id: bill.lease_id } }">{{ bill.lease?.lease_number }}</RouterLink></dd>
            <dt>Room</dt>
            <dd>{{ bill.lease?.room?.property?.name }} · {{ bill.lease?.room?.room_number }}</dd>
            <dt>Billing date</dt>
            <dd>{{ formatDate(bill.billing_date) }}</dd>
            <dt>Due date</dt>
            <dd>{{ formatDate(bill.due_date) }}</dd>
            <dt>Period</dt>
            <dd>{{ formatDate(bill.period_start) }} – {{ formatDate(bill.period_end) }}</dd>
            <dt>Amount</dt>
            <dd>{{ formatMoney(bill.amount) }}</dd>
            <dt>Paid</dt>
            <dd>{{ formatMoney(bill.amount_paid) }}</dd>
            <dt>Balance</dt>
            <dd>{{ formatMoney(bill.balance) }}</dd>
            <dt>Status</dt>
            <dd class="mb-0"><StatusBadge :status="bill.computed_status" /></dd>
          </dl>
        </div>
      </div>
      <div class="col-lg-8">
        <form v-if="canPay" class="panel p-3 mb-3" @submit.prevent="recordPayment">
          <h2 class="h6">Record payment</h2>
          <p class="text-muted small">Remaining balance {{ formatMoney(bill.balance) }}. A payment cannot exceed that amount.</p>
          <div class="row g-2">
            <div class="col-md-3">
              <label class="form-label req" for="pay-date">Date</label>
              <input id="pay-date" v-model="payment.payment_date" type="date" class="form-control" :class="{ 'is-invalid': errors.payment_date }" />
              <FieldError :errors="errors" name="payment_date" />
            </div>
            <div class="col-md-3">
              <label class="form-label req" for="pay-amount">Amount</label>
              <input id="pay-amount" v-model="payment.amount_paid" type="number" min="0.01" step="0.01" class="form-control" :class="{ 'is-invalid': errors.amount_paid }" />
              <FieldError :errors="errors" name="amount_paid" />
            </div>
            <div class="col-md-3">
              <label class="form-label req" for="pay-method">Method</label>
              <select id="pay-method" v-model="payment.payment_method" class="form-select" :class="{ 'is-invalid': errors.payment_method }">
                <option value="cash">Cash</option>
                <option value="gcash">GCash</option>
                <option value="bank_transfer">Bank transfer</option>
                <option value="other">Other</option>
              </select>
              <FieldError :errors="errors" name="payment_method" />
            </div>
            <div class="col-md-3">
              <label class="form-label" for="reference">Reference</label>
              <input id="reference" v-model="payment.payment_reference" class="form-control" :class="{ 'is-invalid': errors.payment_reference }" />
              <FieldError :errors="errors" name="payment_reference" />
            </div>
            <div class="col-12">
              <label class="form-label" for="notes">Notes</label>
              <input id="notes" v-model="payment.notes" class="form-control" />
            </div>
          </div>
          <button class="btn btn-primary mt-3" type="submit" :disabled="paying">{{ paying ? 'Saving…' : 'Record payment' }}</button>
        </form>
        <div class="panel">
          <div class="p-3 border-bottom"><h2 class="h6 mb-0">Payments</h2></div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr>
                  <th scope="col">Date</th>
                  <th scope="col">Amount</th>
                  <th scope="col">Method</th>
                  <th scope="col">Reference</th>
                  <th scope="col">State</th>
                  <th scope="col"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!(bill.payments || []).length">
                  <td colspan="6" class="text-muted">No payments yet.</td>
                </tr>
                <tr v-for="item in bill.payments" :key="item.id">
                  <td>{{ formatDate(item.payment_date) }}</td>
                  <td>{{ formatMoney(item.amount_paid) }}</td>
                  <td class="text-capitalize">{{ item.payment_method.replace('_', ' ') }}</td>
                  <td>{{ item.payment_reference || '—' }}</td>
                  <td>
                    <StatusBadge :status="item.voided_at ? 'voided' : 'paid'" />
                    <div v-if="item.void_reason" class="small text-muted">{{ item.void_reason }}</div>
                  </td>
                  <td class="text-end">
                    <button v-if="!item.voided_at" class="btn btn-sm btn-outline-danger" type="button" @click="askVoid(item)">Void</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <ModalShell :show="voiding.show" title="Void payment" @cancel="voiding.show = false">
      <p>The payment stays in the history and is removed from the paid total.</p>
      <label class="form-label req" for="void-reason">Reason</label>
      <textarea id="void-reason" v-model="voiding.reason" class="form-control" rows="3"></textarea>
      <template #footer>
        <button class="btn btn-outline-secondary" type="button" @click="voiding.show = false">Cancel</button>
        <button class="btn btn-danger" type="button" :disabled="voiding.busy" @click="submitVoid">Void payment</button>
      </template>
    </ModalShell>
    <ModalShell :show="cancelOpen" title="Cancel bill" @cancel="cancelOpen = false">
      <p class="mb-0">The bill will be marked cancelled. It is not deleted. Void any payments first if some are still active.</p>
      <template #footer>
        <button class="btn btn-outline-secondary" type="button" @click="cancelOpen = false">Keep bill</button>
        <button class="btn btn-danger" type="button" :disabled="cancelling" @click="cancelBill">Cancel bill</button>
      </template>
    </ModalShell>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { billingApi, paymentsApi } from '@/api'
import AlertBanner from '@/components/AlertBanner.vue'
import FieldError from '@/components/FieldError.vue'
import LoadingBlock from '@/components/LoadingBlock.vue'
import ModalShell from '@/components/ModalShell.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { errorMessage, fieldErrors } from '@/utils/errors'
import { emptyToNull, formatDate, formatMoney, todayISO } from '@/utils/format'

const route = useRoute()
const bill = ref(null)
const loading = ref(true)
const paying = ref(false)
const cancelling = ref(false)
const cancelOpen = ref(false)
const errors = ref({})
const banner = ref({ type: 'success', message: '' })
const payment = reactive({
  payment_date: todayISO(),
  amount_paid: '',
  payment_method: 'cash',
  payment_reference: '',
  notes: '',
})
const voiding = reactive({ show: false, busy: false, id: null, reason: '' })
const canPay = computed(() => bill.value && bill.value.status !== 'cancelled' && Number(bill.value.balance) > 0)

async function load() {
  loading.value = true
  try {
    const response = await billingApi.get(route.params.id)
    bill.value = response.data.data
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    loading.value = false
  }
}

async function recordPayment() {
  paying.value = true
  errors.value = {}
  try {
    await billingApi.pay(bill.value.id, {
      payment_date: payment.payment_date,
      amount_paid: payment.amount_paid,
      payment_method: payment.payment_method,
      payment_reference: emptyToNull(payment.payment_reference),
      notes: emptyToNull(payment.notes),
    })
    payment.amount_paid = ''
    payment.payment_reference = ''
    payment.notes = ''
    banner.value = { type: 'success', message: 'Payment recorded.' }
    await load()
  } catch (error) {
    errors.value = fieldErrors(error)
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    paying.value = false
  }
}

function askVoid(item) {
  voiding.id = item.id
  voiding.reason = ''
  voiding.show = true
}

async function submitVoid() {
  voiding.busy = true
  try {
    await paymentsApi.void(voiding.id, { void_reason: voiding.reason })
    voiding.show = false
    banner.value = { type: 'success', message: 'Payment voided. The record was kept.' }
    await load()
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
  } finally {
    voiding.busy = false
  }
}

async function cancelBill() {
  cancelling.value = true
  try {
    await billingApi.cancel(bill.value.id)
    cancelOpen.value = false
    banner.value = { type: 'success', message: 'Bill cancelled.' }
    await load()
  } catch (error) {
    banner.value = { type: 'danger', message: errorMessage(error) }
    cancelOpen.value = false
  } finally {
    cancelling.value = false
  }
}

onMounted(load)
</script>
