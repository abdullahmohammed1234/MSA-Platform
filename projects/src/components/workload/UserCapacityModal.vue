<script setup lang="ts">
import { ref, watch } from 'vue';
import { projectsApi } from '../../api/projects';

interface Props {
  userId: number | null;
  userName: string;
  isOpen: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved'): void;
}>();

const loading = ref(false);
const saving = ref(false);

const weeklyHours = ref(10);
const dailyHours = ref(2);
const maxConcurrentTasks = ref(5);
const notes = ref('');

const dailyAvailability = ref<Record<string, number>>({
  mon: 2,
  tue: 2,
  wed: 2,
  thu: 2,
  fri: 2,
  sat: 0,
  sun: 0,
});

const loadCapacity = async () => {
  if (!props.isOpen || !props.userId) return;
  loading.value = true;
  try {
    const data = await projectsApi.getUserCapacity(props.userId);
    weeklyHours.value = Number(data.weekly_capacity_hours) || 10;
    dailyHours.value = Number(data.daily_capacity_hours) || 2;
    maxConcurrentTasks.value = Number(data.max_concurrent_tasks) || 5;
    notes.value = data.notes || '';
    if (data.daily_availability) {
      dailyAvailability.value = { ...dailyAvailability.value, ...data.daily_availability };
    }
  } catch (err: any) {
    console.error('Failed to load user capacity:', err);
  } finally {
    loading.value = false;
  }
};

watch(() => props.isOpen, (newVal) => {
  if (newVal) loadCapacity();
});

const saveCapacity = async () => {
  if (!props.userId) return;
  saving.value = true;
  try {
    await projectsApi.updateUserCapacity(props.userId, {
      weekly_capacity_hours: weeklyHours.value,
      daily_capacity_hours: dailyHours.value,
      max_concurrent_tasks: maxConcurrentTasks.value,
      daily_availability: dailyAvailability.value,
      notes: notes.value,
    });
    emit('saved');
    emit('close');
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to update user capacity.');
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4 animate-in fade-in"
  >
    <div class="bg-white border border-[#ebe8de] rounded-2xl shadow-elevated max-w-md w-full p-6 text-[#1e1e1e]">
      <div class="flex items-center justify-between pb-3 border-b border-[#ebe8de]">
        <div>
          <h3 class="text-base font-bold text-[#640c0e]">Capacity & Availability Profile</h3>
          <p class="text-xs text-[#6b7280]">{{ userName }}</p>
        </div>

        <button
          type="button"
          class="text-[#6b7280] hover:text-[#1e1e1e] p-1 rounded-lg hover:bg-[#fbfbf9] transition-colors"
          @click="emit('close')"
        >
          ✕
        </button>
      </div>

      <div v-if="loading" class="py-12 flex items-center justify-center text-[#6b7280]">
        <div class="animate-spin w-6 h-6 border-2 border-[#640c0e] border-t-transparent rounded-full mr-3" />
        <span class="text-xs font-medium">Loading profile...</span>
      </div>

      <form v-else class="py-4 space-y-4" @submit.prevent="saveCapacity">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-[#1e1e1e] mb-1">Weekly Hours Cap</label>
            <input
              v-model.number="weeklyHours"
              type="number"
              step="0.5"
              min="0"
              max="168"
              required
              class="w-full bg-[#fbfbf9] border border-[#ebe8de] rounded-xl px-3 py-1.5 text-xs text-[#1e1e1e] focus:ring-1 focus:ring-[#640c0e] focus:border-[#640c0e]"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#1e1e1e] mb-1">Daily Standard Hours</label>
            <input
              v-model.number="dailyHours"
              type="number"
              step="0.5"
              min="0"
              max="24"
              required
              class="w-full bg-[#fbfbf9] border border-[#ebe8de] rounded-xl px-3 py-1.5 text-xs text-[#1e1e1e] focus:ring-1 focus:ring-[#640c0e] focus:border-[#640c0e]"
            />
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-[#1e1e1e] mb-1">Max Concurrent Active Tasks</label>
          <input
            v-model.number="maxConcurrentTasks"
            type="number"
            min="1"
            max="50"
            required
            class="w-full bg-[#fbfbf9] border border-[#ebe8de] rounded-xl px-3 py-1.5 text-xs text-[#1e1e1e] focus:ring-1 focus:ring-[#640c0e] focus:border-[#640c0e]"
          />
        </div>

        <!-- Weekly schedule breakdown -->
        <div>
          <label class="block text-xs font-semibold text-[#1e1e1e] mb-1.5">Weekday Daily Hours Schedule</label>
          <div class="grid grid-cols-7 gap-1 text-center text-[10px]">
            <div v-for="day in ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']" :key="day">
              <span class="uppercase text-[#6b7280] font-semibold block mb-1">{{ day }}</span>
              <input
                v-model.number="dailyAvailability[day]"
                type="number"
                step="0.5"
                min="0"
                max="24"
                class="w-full bg-[#fbfbf9] border border-[#ebe8de] rounded-lg px-1 py-1 text-center text-xs text-[#1e1e1e] focus:ring-1 focus:ring-[#640c0e]"
              />
            </div>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-[#1e1e1e] mb-1">Notes / Volunteer Preferences</label>
          <textarea
            v-model="notes"
            rows="2"
            class="w-full bg-[#fbfbf9] border border-[#ebe8de] rounded-xl px-3 py-1.5 text-xs text-[#1e1e1e] focus:ring-1 focus:ring-[#640c0e] focus:border-[#640c0e]"
            placeholder="e.g. Free on Monday and Thursday evenings..."
          />
        </div>

        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-[#ebe8de]">
          <button
            type="button"
            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#fbfbf9] transition-colors"
            @click="emit('close')"
          >
            Cancel
          </button>

          <button
            type="submit"
            class="px-4 py-1.5 rounded-xl text-xs font-bold bg-[#640c0e] hover:bg-[#7e1012] text-white shadow-2xs disabled:opacity-50 transition-colors"
            :disabled="saving"
          >
            {{ saving ? 'Saving...' : 'Save Profile' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

