<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { notificationsService } from '@/services/notifications';
import { useSystemStore } from '@/stores/system';
import { AlertTriangle, Radio, RefreshCw, Send, BellRing, ServerCrash } from 'lucide-vue-next';

const systemStore = useSystemStore();
const notificationStats = ref<any>(null);
const isBroadcasting = ref(false);
const form = ref({ title: '', message: '', audience: 'All' });

const loadLiveData = async () => {
  await Promise.all([
    systemStore.fetchQueues(),
    systemStore.fetchPerformanceStats(),
  ]);

  notificationStats.value = await notificationsService.getStats();
};

const submitBroadcast = async () => {
  if (!form.value.title.trim() || !form.value.message.trim()) return;

  isBroadcasting.value = true;
  try {
    await notificationsService.broadcast(form.value);
    form.value = { title: '', message: '', audience: 'All' };
    notificationStats.value = await notificationsService.getStats();
  } finally {
    isBroadcasting.value = false;
  }
};

onMounted(loadLiveData);
</script>

<template>
  <div class="space-y-8 max-w-7xl mx-auto p-1 text-neutral-black">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white border border-neutral-ivory rounded-2xl p-6 shadow-xl">
      <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-primary">
          Live Admin Section
        </h1>
        <p class="text-sm text-neutral-muted mt-1">Monitor queues, dispatch announcements, and keep the academy command center active.</p>
      </div>
      <button @click="loadLiveData" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-neutral-background border border-neutral-ivory text-sm font-semibold hover:bg-neutral-background transition">
        <RefreshCw class="h-4 w-4" /> Refresh live data
      </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <div class="rounded-2xl bg-white border border-neutral-ivory p-5">
        <div class="text-xs uppercase tracking-[0.2em] text-neutral-muted font-bold">Queues</div>
        <div class="mt-2 text-3xl font-black text-white flex items-center gap-2"><ServerCrash class="h-5 w-5 text-secondary" />{{ systemStore.queues.length }}</div>
      </div>
      <div class="rounded-2xl bg-white border border-neutral-ivory p-5">
        <div class="text-xs uppercase tracking-[0.2em] text-neutral-muted font-bold">Unread notifications</div>
        <div class="mt-2 text-3xl font-black text-white flex items-center gap-2"><BellRing class="h-5 w-5 text-primary" />{{ notificationStats?.unread_count ?? 0 }}</div>
      </div>
      <div class="rounded-2xl bg-white border border-neutral-ivory p-5">
        <div class="text-xs uppercase tracking-[0.2em] text-neutral-muted font-bold">Failure rate</div>
        <div class="mt-2 text-3xl font-black text-white flex items-center gap-2"><AlertTriangle class="h-5 w-5 text-amber-400" />{{ notificationStats?.failure_rate ?? 0 }}%</div>
      </div>
      <div class="rounded-2xl bg-white border border-neutral-ivory p-5">
        <div class="text-xs uppercase tracking-[0.2em] text-neutral-muted font-bold">Throughput</div>
        <div class="mt-2 text-3xl font-black text-white flex items-center gap-2"><Radio class="h-5 w-5 text-secondary" />{{ systemStore.performanceStats?.throughput || 'n/a' }}</div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
      <div class="lg:col-span-2 rounded-2xl bg-white border border-neutral-ivory p-6">
        <h2 class="text-lg font-bold text-white flex items-center gap-2"><Send class="h-4 w-4 text-secondary" /> Broadcast announcement</h2>
        <div class="mt-4 space-y-4">
          <input v-model="form.title" type="text" placeholder="Announcement title" class="w-full rounded-xl bg-neutral-background border border-neutral-ivory px-4 py-3 text-sm text-neutral-black placeholder:text-neutral-muted focus:outline-none focus:border-primary" />
          <textarea v-model="form.message" rows="5" placeholder="Announcement body" class="w-full rounded-xl bg-neutral-background border border-neutral-ivory px-4 py-3 text-sm text-neutral-black placeholder:text-neutral-muted focus:outline-none focus:border-primary"></textarea>
          <select v-model="form.audience" class="w-full rounded-xl bg-neutral-background border border-neutral-ivory px-4 py-3 text-sm text-neutral-black focus:outline-none focus:border-primary">
            <option>All</option>
            <option>Volunteers</option>
            <option>Mentors</option>
            <option>Admins</option>
          </select>
          <button @click="submitBroadcast" :disabled="isBroadcasting" class="inline-flex items-center gap-2 rounded-xl bg-secondary text-white px-4 py-3 text-sm font-bold hover:bg-secondary transition disabled:opacity-50">
            <span v-if="isBroadcasting" class="h-4 w-4 animate-spin rounded-full border-2 border-neutral-ivory border-t-primary"></span>
            Send broadcast
          </button>
        </div>
      </div>

      <div class="rounded-2xl bg-white border border-neutral-ivory p-6">
        <h2 class="text-lg font-bold text-white">Live status</h2>
        <div class="mt-4 space-y-3 text-sm text-neutral-muted">
          <p>Queue processing, notification delivery, and report generation now share the same privileged admin access path.</p>
          <router-link to="/admin/academy/reports" class="block rounded-xl bg-neutral-background/70 hover:bg-neutral-background px-4 py-3 text-neutral-black transition">Open reports desk</router-link>
          <router-link to="/admin/notifications" class="block rounded-xl bg-neutral-background/70 hover:bg-neutral-background px-4 py-3 text-neutral-black transition">Open notification center</router-link>
        </div>
      </div>
    </div>
  </div>
</template>