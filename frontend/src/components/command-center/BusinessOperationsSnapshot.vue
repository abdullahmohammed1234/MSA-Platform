<script setup lang="ts">
import type { BusinessSnapshot } from '@/services/commandCenterService';

const props = defineProps<{
  business: BusinessSnapshot;
}>();

const formatCurrency = (amount: number | undefined) => {
  if (amount === undefined || amount === null) return '$0.00';
  return new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' }).format(amount);
};
</script>

<template>
  <div class="bg-white rounded-xl border border-neutral-ivory p-6 shadow-sm">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-bold text-neutral-black">
          Business Operations Telemetry Snapshot
        </h3>
        <p class="text-xs text-neutral-muted">
          Aggregated domain telemetry composed from Phase 23 Intelligence Services.
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <!-- EMS -->
      <div class="p-4 rounded-xl border border-neutral-ivory bg-white">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-neutral-black">EMS Events</span>
          <span class="text-[10px] font-bold uppercase text-primary bg-primary/10 px-2 py-0.5 rounded">
            Events & Tickets
          </span>
        </div>

        <div v-if="!business.ems?.access_granted" class="py-4 text-center text-xs text-neutral-muted">
          {{ business.ems?.message || 'Access Restricted' }}
        </div>
        <div v-else-if="business.ems?.status === 'unavailable'" class="py-4 text-center text-xs text-neutral-muted">
          Telemetry Unavailable
        </div>
        <div v-else class="space-y-2 text-xs">
          <div class="flex justify-between">
            <span class="text-neutral-muted">Total Registrations</span>
            <span class="font-bold text-neutral-black">{{ business.ems?.total_registrations ?? 0 }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-muted">Paid Registrations</span>
            <span class="font-bold text-emerald-700">{{ business.ems?.paid_registrations ?? 0 }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-muted">Attendance Rate</span>
            <span class="font-bold text-neutral-black">{{ business.ems?.attendance_rate ?? 100 }}%</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-muted">Ticket Mismatch Alerts</span>
            <span class="font-bold text-amber-600">{{ business.ems?.ticket_mismatch_alerts_count ?? 0 }}</span>
          </div>
        </div>
      </div>

      <!-- Donations -->
      <div class="p-4 rounded-xl border border-neutral-ivory bg-white">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-neutral-black">Donations (DMS)</span>
          <span class="text-[10px] font-bold uppercase text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">
            Revenue
          </span>
        </div>

        <div v-if="!business.donations?.access_granted" class="py-4 text-center text-xs text-neutral-muted">
          {{ business.donations?.message || 'Access Restricted' }}
        </div>
        <div v-else-if="business.donations?.status === 'unavailable'" class="py-4 text-center text-xs text-neutral-muted">
          Telemetry Unavailable
        </div>
        <div v-else class="space-y-2 text-xs">
          <div class="flex justify-between">
            <span class="text-neutral-muted">Total Volume</span>
            <span class="font-bold text-emerald-700">{{ formatCurrency(business.donations?.total_amount) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-muted">Successful Donations</span>
            <span class="font-bold text-neutral-black">{{ business.donations?.total_count ?? 0 }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-muted">Unreconciled Refunds</span>
            <span class="font-bold text-amber-600">{{ business.donations?.unreconciled_refunds_count ?? 0 }}</span>
          </div>
        </div>
      </div>

      <!-- Store -->
      <div class="p-4 rounded-xl border border-neutral-ivory bg-white">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-neutral-black">Store Operations</span>
          <span class="text-[10px] font-bold uppercase text-blue-700 bg-blue-50 px-2 py-0.5 rounded">
            E-Commerce
          </span>
        </div>

        <div v-if="!business.store?.access_granted" class="py-4 text-center text-xs text-neutral-muted">
          {{ business.store?.message || 'Access Restricted' }}
        </div>
        <div v-else-if="business.store?.status === 'unavailable'" class="py-4 text-center text-xs text-neutral-muted">
          Telemetry Unavailable
        </div>
        <div v-else class="space-y-2 text-xs">
          <div class="flex justify-between">
            <span class="text-neutral-muted">Total Orders</span>
            <span class="font-bold text-neutral-black">{{ business.store?.total_orders ?? 0 }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-muted">Revenue</span>
            <span class="font-bold text-emerald-700">{{ formatCurrency(business.store?.revenue) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-muted">Low Inventory Items</span>
            <span class="font-bold text-amber-600">{{ business.store?.low_inventory_count ?? 0 }}</span>
          </div>
        </div>
      </div>

      <!-- MLibMS -->
      <div class="p-4 rounded-xl border border-neutral-ivory bg-white">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-neutral-black">Library (MLibMS)</span>
          <span class="text-[10px] font-bold uppercase text-purple-700 bg-purple-50 px-2 py-0.5 rounded">
            Catalogue & Loans
          </span>
        </div>

        <div v-if="!business.mlibms?.access_granted" class="py-4 text-center text-xs text-neutral-muted">
          {{ business.mlibms?.message || 'Access Restricted' }}
        </div>
        <div v-else-if="business.mlibms?.status === 'unavailable'" class="py-4 text-center text-xs text-neutral-muted">
          Telemetry Unavailable
        </div>
        <div v-else class="space-y-2 text-xs">
          <div class="flex justify-between">
            <span class="text-neutral-muted">Active Loans</span>
            <span class="font-bold text-neutral-black">{{ business.mlibms?.active_loans_count ?? 0 }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-muted">Overdue Loans</span>
            <span class="font-bold text-amber-600">{{ business.mlibms?.overdue_loans_count ?? 0 }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-muted">Severe Overdue (&gt;14d)</span>
            <span class="font-bold text-red-600">{{ business.mlibms?.severe_overdue_count ?? 0 }}</span>
          </div>
        </div>
      </div>

      <!-- Volunteers -->
      <div class="p-4 rounded-xl border border-neutral-ivory bg-white">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-neutral-black">Volunteering</span>
          <span class="text-[10px] font-bold uppercase text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded">
            Registrations
          </span>
        </div>

        <div v-if="!business.volunteers?.access_granted" class="py-4 text-center text-xs text-neutral-muted">
          {{ business.volunteers?.message || 'Access Restricted' }}
        </div>
        <div v-else-if="business.volunteers?.status === 'unavailable'" class="py-4 text-center text-xs text-neutral-muted">
          Telemetry Unavailable
        </div>
        <div v-else class="space-y-2 text-xs">
          <div class="flex justify-between">
            <span class="text-neutral-muted">Pending Applications</span>
            <span class="font-bold text-neutral-black">{{ business.volunteers?.pending_applications_count ?? 0 }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-muted">Approved Volunteers</span>
            <span class="font-bold text-emerald-700">{{ business.volunteers?.approved_count ?? 0 }}</span>
          </div>
        </div>
      </div>

      <!-- Communications -->
      <div class="p-4 rounded-xl border border-neutral-ivory bg-white">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold text-neutral-black">Communications</span>
          <span class="text-[10px] font-bold uppercase text-teal-700 bg-teal-50 px-2 py-0.5 rounded">
            Notifications
          </span>
        </div>

        <div v-if="!business.communications?.access_granted" class="py-4 text-center text-xs text-neutral-muted">
          {{ business.communications?.message || 'Access Restricted' }}
        </div>
        <div v-else-if="business.communications?.status === 'unavailable'" class="py-4 text-center text-xs text-neutral-muted">
          Telemetry Unavailable
        </div>
        <div v-else class="space-y-2 text-xs">
          <div class="flex justify-between">
            <span class="text-neutral-muted">Sent Notifications</span>
            <span class="font-bold text-neutral-black">{{ business.communications?.sent_count ?? 0 }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-muted">Failed Notifications</span>
            <span class="font-bold text-red-600">{{ business.communications?.failed_count ?? 0 }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-muted">Delivery Failure Rate</span>
            <span class="font-bold text-neutral-black">{{ business.communications?.failure_rate ?? 0 }}%</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
