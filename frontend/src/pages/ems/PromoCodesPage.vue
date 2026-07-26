<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { EmsErrorState, EmsPageHeader } from '@/components/ems';
import { promoCodesService, type PromoCode } from '@/services/ems/promoCodesService';
import { eventsService } from '@/services/ems/eventsService';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import { useToastStore } from '@/components/feedback/toast';
import { Plus, Tag, Trash2, Edit2 } from 'lucide-vue-next';
import type { Event } from '@/types/ems';

const toast = useToastStore();
const { fieldError, generalError, handle, clear } = useEmsApiError();

const promoCodes = ref<PromoCode[]>([]);
const eventsList = ref<Event[]>([]);
const isLoading = ref(true);
const error = ref<string | null>(null);

// Form / Modal state
const isFormOpen = ref(false);
const isSaving = ref(false);
const editingCode = ref<PromoCode | null>(null);

const form = ref({
  code: '',
  description: '',
  discount_type: 'percentage' as 'percentage' | 'fixed' | 'free',
  discount_value: 0,
  usage_limit: '' as number | '',
  usage_per_attendee: 1,
  start_date: '',
  end_date: '',
  minimum_purchase: '' as number | '',
  is_active: true,
  eligible_events: [] as string[],
});

const loadData = async () => {
  isLoading.value = true;
  error.value = null;
  try {
    const [codes, evts] = await Promise.all([
      promoCodesService.list(),
      eventsService.list({ per_page: 100 }),
    ]);
    promoCodes.value = codes;
    eventsList.value = evts.items;
  } catch (caught) {
    error.value = handle(caught, { silent: true }).message;
  } finally {
    isLoading.value = false;
  }
};

onMounted(loadData);

const openCreate = () => {
  editingCode.value = null;
  form.value = {
    code: '',
    description: '',
    discount_type: 'percentage',
    discount_value: 0,
    usage_limit: '',
    usage_per_attendee: 1,
    start_date: '',
    end_date: '',
    minimum_purchase: '',
    is_active: true,
    eligible_events: [],
  };
  clear();
  isFormOpen.value = true;
};

const openEdit = (code: PromoCode) => {
  editingCode.value = code;
  form.value = {
    code: code.code,
    description: code.description || '',
    discount_type: code.discount_type,
    discount_value: Number(code.discount_value),
    usage_limit: code.usage_limit || '',
    usage_per_attendee: code.usage_per_attendee,
    start_date: code.start_date ? code.start_date.slice(0, 10) : '',
    end_date: code.end_date ? code.end_date.slice(0, 10) : '',
    minimum_purchase: code.minimum_purchase ? Number(code.minimum_purchase) : '',
    is_active: code.is_active,
    eligible_events: code.events?.map(e => e.uuid) || [],
  };
  clear();
  isFormOpen.value = true;
};

const save = async () => {
  clear();
  isSaving.value = true;
  try {
    const payload = {
      code: form.value.code.toUpperCase().trim(),
      description: form.value.description.trim() || null,
      discount_type: form.value.discount_type,
      discount_value: form.value.discount_type === 'free' ? 100 : Number(form.value.discount_value),
      usage_limit: form.value.usage_limit ? Number(form.value.usage_limit) : null,
      usage_per_attendee: Number(form.value.usage_per_attendee),
      start_date: form.value.start_date ? new Date(form.value.start_date).toISOString() : null,
      end_date: form.value.end_date ? new Date(form.value.end_date).toISOString() : null,
      minimum_purchase: form.value.minimum_purchase ? Number(form.value.minimum_purchase) : null,
      is_active: form.value.is_active,
      eligible_events: form.value.eligible_events.length > 0 ? form.value.eligible_events : undefined,
    };

    if (editingCode.value) {
      await promoCodesService.update(editingCode.value.uuid, payload);
      toast.success('Promo code updated successfully.');
    } else {
      await promoCodesService.create(payload);
      toast.success('Promo code created successfully.');
    }
    isFormOpen.value = false;
    await loadData();
  } catch (caught) {
    handle(caught);
  } finally {
    isSaving.value = false;
  }
};

const archive = async (code: PromoCode) => {
  if (!confirm(`Are you sure you want to archive promo code ${code.code}?`)) return;
  try {
    await promoCodesService.remove(code.uuid);
    toast.success('Promo code archived.');
    await loadData();
  } catch (caught) {
    handle(caught);
  }
};

const formatCurrency = (val: number) => {
  return new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' }).format(val);
};

const formatDate = (val: string | null) => {
  if (!val) return '—';
  return new Date(val).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
};
</script>

<template>
  <div class="space-y-6">
    <EmsPageHeader
      title="Promo Codes"
      description="Manage coupons, checkout discounts, and track promotion performance."
      back-to="/ems"
      back-label="Dashboard"
    >
      <template #actions>
        <Button @click="openCreate">
          <template #left-icon><Plus class="h-4 w-4" /></template>
          Create Promo Code
        </Button>
      </template>
    </EmsPageHeader>

    <div v-if="isLoading" class="h-48 animate-pulse rounded-2xl bg-neutral-ivory/50" />
    <EmsErrorState v-else-if="error" title="Unable to load promo codes" :message="error" can-retry @retry="loadData" />

    <div v-else class="overflow-x-auto rounded-2xl border border-neutral-ivory bg-white">
      <table class="min-w-full text-left text-sm">
        <thead class="border-b border-neutral-ivory text-[11px] uppercase tracking-wider text-neutral-muted bg-neutral-background/40">
          <tr>
            <th class="px-5 py-4">Code</th>
            <th class="px-5 py-4">Description</th>
            <th class="px-5 py-4">Discount</th>
            <th class="px-5 py-4">Status</th>
            <th class="px-5 py-4">Uses</th>
            <th class="px-5 py-4">Revenue Impact</th>
            <th class="px-5 py-4">Validity</th>
            <th class="px-5 py-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-ivory/70">
          <tr v-for="code in promoCodes" :key="code.uuid" class="hover:bg-neutral-background/30 transition-colors">
            <td class="px-5 py-4 font-mono font-bold text-primary">
              <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md border border-primary/20 bg-primary/5 text-primary text-xs">
                <Tag class="h-3 w-3" />
                {{ code.code }}
              </span>
            </td>
            <td class="px-5 py-4 text-neutral-muted max-w-xs truncate">{{ code.description || '—' }}</td>
            <td class="px-5 py-4 font-medium text-neutral-black">
              <span v-if="code.discount_type === 'percentage'">{{ code.discount_value }}% Off</span>
              <span v-else-if="code.discount_type === 'fixed'">{{ formatCurrency(code.discount_value) }} Off</span>
              <span v-else class="text-emerald-700 font-bold">100% Free</span>
            </td>
            <td class="px-5 py-4">
              <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold border', code.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-50 text-gray-500 border-gray-200']">
                <span class="h-1.5 w-1.5 rounded-full" :class="code.is_active ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                {{ code.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-5 py-4 text-neutral-black">
              {{ code.times_used }}
              <span class="text-neutral-muted text-xs">
                / {{ code.usage_limit !== null ? code.usage_limit : '∞' }}
              </span>
            </td>
            <td class="px-5 py-4 font-semibold text-neutral-black">
              {{ formatCurrency(code.revenue_impact || 0) }}
            </td>
            <td class="px-5 py-4 text-neutral-muted text-xs">
              <div>Starts: {{ formatDate(code.start_date) }}</div>
              <div>Ends: {{ formatDate(code.end_date) }}</div>
            </td>
            <td class="px-5 py-4 text-right space-x-1.5">
              <Button size="sm" variant="ghost" class="p-1.5" @click="openEdit(code)">
                <Edit2 class="h-3.5 w-3.5" />
              </Button>
              <Button size="sm" variant="ghost" class="p-1.5 text-secondary hover:text-red-700" @click="archive(code)">
                <Trash2 class="h-3.5 w-3.5" />
              </Button>
            </td>
          </tr>
          <tr v-if="promoCodes.length === 0">
            <td colspan="8" class="p-8 text-center text-neutral-muted text-sm">
              No promo codes found. Click "Create Promo Code" to create one.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create / Edit Slide-over/Modal -->
    <div
      v-if="isFormOpen"
      class="fixed inset-0 z-50 flex items-center justify-end bg-black/40 backdrop-blur-xs p-4"
      @click.self="isFormOpen = false"
    >
      <div class="h-full w-full max-w-lg rounded-2xl bg-white shadow-premium flex flex-col animate-slide-left overflow-y-auto">
        <div class="px-6 py-5 border-b border-neutral-ivory flex items-center justify-between">
          <h3 class="text-lg font-bold text-primary">{{ editingCode ? 'Edit Promo Code' : 'Create Promo Code' }}</h3>
          <button type="button" @click="isFormOpen = false" class="text-neutral-muted hover:text-neutral-black">&times;</button>
        </div>

        <form class="flex-1 p-6 space-y-4" @submit.prevent="save">
          <p v-if="generalError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-secondary">
            {{ generalError }}
          </p>

          <Input
            v-model="form.code"
            label="Promo Code"
            placeholder="WELCOME20"
            required
            class="uppercase"
            :disabled="!!editingCode"
            :error="fieldError('code')"
          />

          <Input
            v-model="form.description"
            label="Description"
            placeholder="20% off for first-time attendees"
            :error="fieldError('description')"
          />

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Discount Type</label>
              <select
                v-model="form.discount_type"
                class="w-full rounded-lg border border-neutral-ivory bg-neutral-background/40 px-3 py-2 text-xs text-neutral-black focus:border-primary focus:outline-none"
              >
                <option value="percentage">Percentage Discount</option>
                <option value="fixed">Fixed Cash Discount</option>
                <option value="free">100% Free Entry</option>
              </select>
            </div>

            <Input
              v-if="form.discount_type !== 'free'"
              v-model="form.discount_value"
              type="number"
              :label="form.discount_type === 'percentage' ? 'Percent Value (%)' : 'Amount Value ($)'"
              placeholder="10"
              required
              :error="fieldError('discount_value')"
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <Input
              v-model="form.usage_limit"
              type="number"
              label="Usage Limit"
              placeholder="e.g. 100 uses (optional)"
              :error="fieldError('usage_limit')"
            />
            <Input
              v-model="form.usage_per_attendee"
              type="number"
              label="Uses per Attendee"
              placeholder="1"
              required
              :error="fieldError('usage_per_attendee')"
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Starts Date</label>
              <input type="date" v-model="form.start_date" class="w-full rounded-lg border border-neutral-ivory bg-neutral-background/40 px-3 py-2 text-xs text-neutral-black focus:border-primary focus:outline-none" />
            </div>
            <div class="space-y-1.5">
              <label class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Ends Date</label>
              <input type="date" v-model="form.end_date" class="w-full rounded-lg border border-neutral-ivory bg-neutral-background/40 px-3 py-2 text-xs text-neutral-black focus:border-primary focus:outline-none" />
            </div>
          </div>

          <Input
            v-model="form.minimum_purchase"
            type="number"
            label="Minimum Purchase ($)"
            placeholder="optional"
            :error="fieldError('minimum_purchase')"
          />

          <!-- Eligible Events (Multi-Select style) -->
          <div class="space-y-1.5">
            <label class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Restricted to Specific Events</label>
            <p class="text-[10px] text-neutral-muted">Leave empty to allow the coupon code across all events.</p>
            <div class="max-h-32 overflow-y-auto border border-neutral-ivory rounded-lg p-2 space-y-1">
              <label v-for="e in eventsList" :key="e.uuid" class="flex items-center gap-2 text-xs text-neutral-black cursor-pointer hover:bg-neutral-background/30 p-1 rounded">
                <input type="checkbox" :value="e.uuid" v-model="form.eligible_events" />
                {{ e.name }}
              </label>
            </div>
          </div>

          <div class="py-2 border-t border-neutral-ivory">
            <Switch
              v-model="form.is_active"
              label="Active Status"
              description="Whether this promo code is currently redeemable."
            />
          </div>

          <div class="flex justify-end gap-2 pt-4 border-t border-neutral-ivory">
            <Button variant="ghost" type="button" @click="isFormOpen = false">Cancel</Button>
            <Button variant="primary" type="submit" :is-loading="isSaving">
              {{ editingCode ? 'Save Changes' : 'Create Promo Code' }}
            </Button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
