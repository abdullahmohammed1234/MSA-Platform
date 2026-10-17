<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { connectAdminService } from '@/services/admin/connectAdminService';
import type { ConnectConfig } from '@/services/admin/connectAdminService';
import { useConnectAdminAccessStore } from '@/stores/connect/connectAdminAccess';
import { useToastStore } from '@/components/feedback/toast';
import {
  SlidersVertical,
  Radio,
  ShieldAlert,
  Save,
  RotateCcw,
  AlertCircle,
} from 'lucide-vue-next';

const accessStore = useConnectAdminAccessStore();
const toast = useToastStore();

const isLoading = ref(true);
const isSaving = ref(false);
const error = ref<string | null>(null);

const form = ref<ConnectConfig>({
  connect_resources_enabled: false,
  member_directory_enabled: true,
  direct_messaging_enabled: true,
  events_feed_enabled: true,
  announcements_feed_enabled: true,
  webpush_enabled: true,
  max_devices_per_user: 10,
  delivery_timeout_seconds: 5.0,
  support_inquiries_enabled: true,
  maintenance_mode: false,
  maintenance_message: null,
});

const initialConfig = ref<ConnectConfig | null>(null);

const loadConfig = async () => {
  try {
    isLoading.value = true;
    error.value = null;
    const config = await connectAdminService.getConfig();
    form.value = { ...config };
    initialConfig.value = { ...config };
  } catch (err: any) {
    error.value = err?.response?.data?.message || err?.message || 'Failed to load configuration.';
  } finally {
    isLoading.value = false;
  }
};

const handleSave = async () => {
  if (!accessStore.canManage) {
    toast.error('You do not have permission to modify Connect configuration.');
    return;
  }

  try {
    isSaving.value = true;
    error.value = null;
    const result = await connectAdminService.updateConfig(form.value);
    form.value = { ...result.config };
    initialConfig.value = { ...result.config };
    toast.success(result.message || 'Configuration saved successfully.');
  } catch (err: any) {
    const msg = err?.response?.data?.message || err?.message || 'Failed to save configuration.';
    error.value = msg;
    toast.error(msg);
  } finally {
    isSaving.value = false;
  }
};

const handleReset = () => {
  if (initialConfig.value) {
    form.value = { ...initialConfig.value };
    toast.info('Form reset to saved configuration.');
  }
};

onMounted(() => {
  void loadConfig();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl font-bold text-neutral-900">Connect Configuration & Controls</h1>
        <p class="text-xs text-neutral-500 mt-1">
          Inspect and configure member-facing features, Web Push delivery bounds, and operational maintenance states.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          @click="handleReset"
          :disabled="isSaving || isLoading"
          class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-[#ebe8de] text-xs font-semibold text-neutral-700 hover:bg-[#faf8f5] transition-colors cursor-pointer"
        >
          <RotateCcw class="w-3.5 h-3.5" />
          <span>Reset</span>
        </button>
        <button
          type="button"
          @click="handleSave"
          :disabled="isSaving || isLoading || !accessStore.canManage"
          class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#640c0e] text-white text-xs font-semibold hover:bg-[#500a0b] transition-colors shadow-xs disabled:opacity-50 cursor-pointer"
        >
          <Save class="w-3.5 h-3.5" :class="{ 'animate-spin': isSaving }" />
          <span>{{ isSaving ? 'Saving...' : 'Save Configuration' }}</span>
        </button>
      </div>
    </div>

    <!-- Error Alert -->
    <div v-if="error" class="bg-red-50 border border-red-200 rounded-2xl p-4 text-xs text-red-700 flex items-center gap-3">
      <AlertCircle class="w-5 h-5 shrink-0 text-red-500" />
      <div class="flex-1">{{ error }}</div>
    </div>

    <!-- Permission Notice -->
    <div v-if="!accessStore.canManage" class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs text-amber-800 flex items-center gap-3">
      <AlertCircle class="w-5 h-5 shrink-0 text-amber-600" />
      <div>
        <strong>Read-Only Mode:</strong> Your account has permission to view Connect configuration, but requires <code class="font-mono bg-amber-100 px-1 py-0.5 rounded">connect.manage</code> permission to save updates.
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="bg-white rounded-2xl border border-[#ebe8de] p-8 text-center text-xs text-neutral-400">
      Loading configuration...
    </div>

    <form v-else @submit.prevent="handleSave" class="space-y-6">
      <!-- Section 1: Feature Publication & Visibility -->
      <section class="bg-white rounded-2xl border border-[#ebe8de] p-6 shadow-xs space-y-4">
        <div class="flex items-center gap-2 border-b border-[#ebe8de] pb-3">
          <SlidersVertical class="w-4 h-4 text-[#640c0e]" />
          <h2 class="text-sm font-bold text-neutral-900">Feature Publication & Visibility</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Connect Resources -->
          <div class="flex items-start justify-between p-3.5 rounded-xl border border-[#ebe8de] bg-[#faf8f5]">
            <div class="pr-3">
              <div class="flex items-center gap-2">
                <label class="text-xs font-bold text-neutral-900 block">Connect Resources</label>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800">Operational</span>
              </div>
              <p class="text-[11px] text-neutral-500 mt-0.5">
                Expose dedicated resource directories and study guides inside member-facing Connect.
              </p>
            </div>
            <input
              type="checkbox"
              v-model="form.connect_resources_enabled"
              :disabled="!accessStore.canManage"
              class="h-4 w-4 rounded text-[#640c0e] focus:ring-[#640c0e] border-[#ebe8de] mt-1 cursor-pointer"
            />
          </div>

          <!-- Member Directory (Deferred) -->
          <div class="flex items-start justify-between p-3.5 rounded-xl border border-[#ebe8de] bg-[#faf8f5]/60">
            <div class="pr-3">
              <div class="flex items-center gap-2">
                <label class="text-xs font-bold text-neutral-900 block">Member Directory</label>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-neutral-200 text-neutral-700">Deferred / Non-Operational</span>
              </div>
              <p class="text-[11px] text-neutral-500 mt-0.5">
                Member discovery and public directory views are deferred to a future phase. Stored for configuration completeness but not currently operational in member clients.
              </p>
            </div>
            <input
              type="checkbox"
              v-model="form.member_directory_enabled"
              :disabled="true"
              class="h-4 w-4 rounded text-[#640c0e] border-[#ebe8de] mt-1 opacity-50 cursor-not-allowed"
            />
          </div>

          <!-- Direct Messaging (Active Default · Flag Enforcement Deferred) -->
          <div class="flex items-start justify-between p-3.5 rounded-xl border border-[#ebe8de] bg-[#faf8f5]/60">
            <div class="pr-3">
              <div class="flex items-center gap-2">
                <label class="text-xs font-bold text-neutral-900 block">Direct Messaging</label>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800">Active Default · Kill-Switch Deferred</span>
              </div>
              <p class="text-[11px] text-neutral-500 mt-0.5">
                Direct messaging runs under its default active state. A runtime toggle / emergency kill-switch is deferred to prevent abrupt communication loss. Stored for configuration completeness.
              </p>
            </div>
            <input
              type="checkbox"
              v-model="form.direct_messaging_enabled"
              :disabled="true"
              class="h-4 w-4 rounded text-[#640c0e] border-[#ebe8de] mt-1 opacity-50 cursor-not-allowed"
            />
          </div>

          <!-- Events Feed -->
          <div class="flex items-start justify-between p-3.5 rounded-xl border border-[#ebe8de] bg-[#faf8f5]">
            <div class="pr-3">
              <div class="flex items-center gap-2">
                <label class="text-xs font-bold text-neutral-900 block">Campus Events Feed</label>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800">Operational</span>
              </div>
              <p class="text-[11px] text-neutral-500 mt-0.5">
                Deliver authoritative EMS published events into member cards and calendars. When disabled, the public catalog delivery is suspended.
              </p>
            </div>
            <input
              type="checkbox"
              v-model="form.events_feed_enabled"
              :disabled="!accessStore.canManage"
              class="h-4 w-4 rounded text-[#640c0e] focus:ring-[#640c0e] border-[#ebe8de] mt-1 cursor-pointer"
            />
          </div>

          <!-- Announcements Feed -->
          <div class="flex items-start justify-between p-3.5 rounded-xl border border-[#ebe8de] bg-[#faf8f5]">
            <div class="pr-3">
              <div class="flex items-center gap-2">
                <label class="text-xs font-bold text-neutral-900 block">Announcements & Bulletins</label>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800">Operational</span>
              </div>
              <p class="text-[11px] text-neutral-500 mt-0.5">
                Deliver authoritative CMS announcements and community notices to Connect. When disabled, the live feed delivery is suspended.
              </p>
            </div>
            <input
              type="checkbox"
              v-model="form.announcements_feed_enabled"
              :disabled="!accessStore.canManage"
              class="h-4 w-4 rounded text-[#640c0e] focus:ring-[#640c0e] border-[#ebe8de] mt-1 cursor-pointer"
            />
          </div>

          <!-- Support Inquiries (Active Default · Intake Gate Deferred) -->
          <div class="flex items-start justify-between p-3.5 rounded-xl border border-[#ebe8de] bg-[#faf8f5]/60">
            <div class="pr-3">
              <div class="flex items-center gap-2">
                <label class="text-xs font-bold text-neutral-900 block">Support Desk Intake</label>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800">Active Default · Intake Gate Deferred</span>
              </div>
              <p class="text-[11px] text-neutral-500 mt-0.5">
                Support desk intake runs under its default active state. Runtime gating / intake shutoff is deferred. Stored for configuration completeness.
              </p>
            </div>
            <input
              type="checkbox"
              v-model="form.support_inquiries_enabled"
              :disabled="true"
              class="h-4 w-4 rounded text-[#640c0e] border-[#ebe8de] mt-1 opacity-50 cursor-not-allowed"
            />
          </div>
        </div>
      </section>

      <!-- Section 2: Web Push Delivery Bounds -->
      <section class="bg-white rounded-2xl border border-[#ebe8de] p-6 shadow-xs space-y-4">
        <div class="flex items-center gap-2 border-b border-[#ebe8de] pb-3">
          <Radio class="w-4 h-4 text-[#640c0e]" />
          <h2 class="text-sm font-bold text-neutral-900">Web Push Delivery Controls</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Web Push Master Toggle -->
          <div class="flex items-start justify-between p-3.5 rounded-xl border border-[#ebe8de] bg-[#faf8f5]">
            <div class="pr-3">
              <div class="flex items-center gap-2">
                <label class="text-xs font-bold text-neutral-900 block">Web Push Activation</label>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800">Operational</span>
              </div>
              <p class="text-[11px] text-neutral-500 mt-0.5">
                Enable browser PushManager registrations and immediate synchronous delivery.
              </p>
            </div>
            <input
              type="checkbox"
              v-model="form.webpush_enabled"
              :disabled="!accessStore.canManage"
              class="h-4 w-4 rounded text-[#640c0e] focus:ring-[#640c0e] border-[#ebe8de] mt-1 cursor-pointer"
            />
          </div>

          <!-- Max Devices Per User -->
          <div class="p-3.5 rounded-xl border border-[#ebe8de] bg-[#faf8f5] space-y-1.5">
            <div class="flex items-center gap-2">
              <label class="text-xs font-bold text-neutral-900 block">Max Devices Per Member</label>
              <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800">Operational</span>
            </div>
            <p class="text-[11px] text-neutral-500">
              Bounds synchronous fan-out latency (range: 1 - 50).
            </p>
            <input
              type="number"
              min="1"
              max="50"
              v-model.number="form.max_devices_per_user"
              :disabled="!accessStore.canManage"
              class="w-full text-xs font-mono px-3 py-1.5 rounded-lg border border-[#ebe8de] bg-white focus:outline-none focus:border-[#640c0e]"
            />
          </div>

          <!-- Synchronous Delivery Timeout -->
          <div class="p-3.5 rounded-xl border border-[#ebe8de] bg-[#faf8f5] space-y-1.5">
            <div class="flex items-center gap-2">
              <label class="text-xs font-bold text-neutral-900 block">Delivery Timeout (Seconds)</label>
              <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800">Operational</span>
            </div>
            <p class="text-[11px] text-neutral-500">
              Strict per-endpoint HTTP timeout (range: 1.0 - 30.0).
            </p>
            <input
              type="number"
              step="0.5"
              min="1"
              max="30"
              v-model.number="form.delivery_timeout_seconds"
              :disabled="!accessStore.canManage"
              class="w-full text-xs font-mono px-3 py-1.5 rounded-lg border border-[#ebe8de] bg-white focus:outline-none focus:border-[#640c0e]"
            />
          </div>
        </div>
      </section>

      <!-- Section 3: Operations & Maintenance -->
      <section class="bg-white rounded-2xl border border-[#ebe8de] p-6 shadow-xs space-y-4">
        <div class="flex items-center gap-2 border-b border-[#ebe8de] pb-3">
          <ShieldAlert class="w-4 h-4 text-[#640c0e]" />
          <h2 class="text-sm font-bold text-neutral-900">Maintenance & Barrier Controls</h2>
        </div>

        <div class="space-y-4">
          <!-- Maintenance Mode (Deferred) -->
          <div class="flex items-start justify-between p-3.5 rounded-xl border border-[#ebe8de] bg-[#faf8f5]/60">
            <div class="pr-3">
              <div class="flex items-center gap-2">
                <label class="text-xs font-bold text-neutral-900 block">Maintenance Mode</label>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-neutral-200 text-neutral-700">Deferred / Non-Operational (Phase 15)</span>
              </div>
              <p class="text-[11px] text-neutral-500 mt-0.5">
                Client-side maintenance splash barrier and member route interception are deferred to Phase 15. This setting is stored for configuration completeness but not currently operational in member clients.
              </p>
            </div>
            <input
              type="checkbox"
              v-model="form.maintenance_mode"
              :disabled="true"
              class="h-4 w-4 rounded text-[#640c0e] border-[#ebe8de] mt-1 opacity-50 cursor-not-allowed"
            />
          </div>

          <!-- Maintenance Notice (Deferred) -->
          <div class="space-y-1.5 p-3.5 rounded-xl border border-[#ebe8de] bg-[#faf8f5]/60">
            <div class="flex items-center gap-2">
              <label class="text-xs font-bold text-neutral-900 block">Maintenance Notice</label>
              <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-neutral-200 text-neutral-700">Deferred / Non-Operational (Phase 15)</span>
            </div>
            <p class="text-[11px] text-neutral-500">
              Custom maintenance splash banner message displayed to members during maintenance periods. Deferred to Phase 15.
            </p>
            <input
              type="text"
              maxlength="255"
              v-model="form.maintenance_message"
              :disabled="true"
              placeholder="e.g. MSA Connect is temporarily offline for scheduled system upgrades. Please check back shortly."
              class="w-full text-xs px-3.5 py-2.5 rounded-xl border border-[#ebe8de] bg-neutral-100 text-neutral-500 cursor-not-allowed opacity-60"
            />
          </div>
        </div>
      </section>
    </form>
  </div>
</template>
