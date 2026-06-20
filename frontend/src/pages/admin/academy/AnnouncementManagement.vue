<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { cmsService } from '@/services/cms/cmsService';
import { notificationsService } from '@/services/notifications';
import { Megaphone, PencilLine, RefreshCw, Send, Trash2 } from 'lucide-vue-next';

type AnnouncementItem = {
  uuid?: string;
  title: string;
  body?: string;
  status?: string;
  published_at?: string | null;
  updated_at?: string | null;
};

const loading = ref(false);
const submitting = ref(false);
const announcements = ref<AnnouncementItem[]>([]);
const search = ref('');
const broadcast = ref({ title: '', message: '', audience: 'All' });

const fallbackAnnouncements: AnnouncementItem[] = [
  { uuid: 'demo-1', title: 'Weekly Study Circle', body: 'Sessions open every Thursday after Maghrib.', status: 'published', published_at: new Date().toISOString() },
  { uuid: 'demo-2', title: 'Mentor Office Hours', body: 'New mentor drop-in hours are active this week.', status: 'draft', updated_at: new Date().toISOString() },
];

const loadAnnouncements = async () => {
  loading.value = true;
  try {
    const response = await cmsService.getAnnouncements({ page: 1, per_page: 12 });
    announcements.value = response.data.length ? (response.data as AnnouncementItem[]) : fallbackAnnouncements;
  } catch (error) {
    announcements.value = fallbackAnnouncements;
  } finally {
    loading.value = false;
  }
};

const publishBroadcast = async () => {
  if (!broadcast.value.title.trim() || !broadcast.value.message.trim()) return;
  submitting.value = true;
  try {
    await notificationsService.broadcast(broadcast.value);
    broadcast.value = { title: '', message: '', audience: 'All' };
    await loadAnnouncements();
  } finally {
    submitting.value = false;
  }
};

const deleteAnnouncement = async (announcement: AnnouncementItem) => {
  if (!announcement.uuid) return;
  if (!window.confirm(`Delete ${announcement.title}?`)) return;
  await cmsService.deleteAnnouncement(announcement.uuid);
  await loadAnnouncements();
};

const visibleAnnouncements = computed(() => announcements.value.filter((announcement) =>
  [announcement.title, announcement.body || ''].some((field) => field.toLowerCase().includes(search.value.toLowerCase()))
));

onMounted(() => {
  loadAnnouncements();
});
</script>

<template>
  <div class="space-y-6 max-w-7xl mx-auto p-1 text-neutral-black">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div>
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary/70">Academy Admin</p>
        <h1 class="text-3xl font-display font-extrabold text-primary">Announcement Management</h1>
        <p class="text-sm text-neutral-muted mt-1">Draft, publish, and broadcast academy announcements from one desk.</p>
      </div>
      <button @click="loadAnnouncements" class="inline-flex items-center gap-2 rounded-xl border border-neutral-ivory bg-white px-4 py-2.5 text-sm hover:bg-neutral-background transition"><RefreshCw class="h-4 w-4" /> Refresh</button>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
      <section class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
        <div class="flex items-center justify-between gap-4">
          <div class="flex items-center gap-3"><Megaphone class="h-5 w-5 text-primary" /><h2 class="text-lg font-display font-semibold">Published and Draft Announcements</h2></div>
          <input v-model="search" type="search" placeholder="Search announcements" class="w-56 max-w-full rounded-xl border border-neutral-ivory bg-neutral-background px-3 py-2 text-sm outline-none focus:border-primary/30" />
        </div>

        <div v-if="loading" class="mt-6 space-y-3">
          <div v-for="item in 3" :key="item" class="h-20 animate-pulse rounded-2xl bg-neutral-background"></div>
        </div>

        <div v-else class="mt-6 space-y-3">
          <article v-for="announcement in visibleAnnouncements" :key="announcement.uuid || announcement.title" class="rounded-2xl border border-neutral-ivory p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="font-display text-base font-semibold text-neutral-black">{{ announcement.title }}</h3>
                  <span class="rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide" :class="announcement.status === 'published' ? 'bg-secondary/10 text-secondary' : 'bg-accent-gold/20 text-primary'">{{ announcement.status || 'draft' }}</span>
                </div>
                <p class="mt-2 text-sm text-neutral-muted">{{ announcement.body || 'No body available.' }}</p>
              </div>
              <div class="flex gap-2">
                <button class="inline-flex items-center gap-1 rounded-xl border border-neutral-ivory px-3 py-2 text-sm hover:bg-neutral-background transition"><PencilLine class="h-4 w-4" /> Edit</button>
                <button v-if="announcement.uuid" @click="deleteAnnouncement(announcement)" class="inline-flex items-center gap-1 rounded-xl border border-red-200 px-3 py-2 text-sm text-red-700 hover:bg-red-50 transition"><Trash2 class="h-4 w-4" /> Delete</button>
              </div>
            </div>
          </article>
        </div>
      </section>

      <section class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
        <h2 class="text-lg font-display font-semibold">Broadcast a New Announcement</h2>
        <div class="mt-4 space-y-3">
          <input v-model="broadcast.title" type="text" placeholder="Announcement title" class="w-full rounded-xl border border-neutral-ivory bg-neutral-background px-3 py-2.5 text-sm outline-none focus:border-primary/30" />
          <textarea v-model="broadcast.message" rows="6" placeholder="Announcement message" class="w-full rounded-xl border border-neutral-ivory bg-neutral-background px-3 py-2.5 text-sm outline-none focus:border-primary/30"></textarea>
          <select v-model="broadcast.audience" class="w-full rounded-xl border border-neutral-ivory bg-white px-3 py-2.5 text-sm outline-none focus:border-primary/30">
            <option>All</option>
            <option>Volunteers</option>
            <option>Mentors</option>
            <option>Admins</option>
          </select>
          <button @click="publishBroadcast" :disabled="submitting" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-95 disabled:opacity-60"><Send class="h-4 w-4" /> Send Broadcast</button>
        </div>
      </section>
    </div>
  </div>
</template>