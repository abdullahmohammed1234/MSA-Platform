<script setup lang="ts">
import { ref } from 'vue';
import { projectsApi } from '../../api/projects';
import type { ProjectMember, ProjectRole } from '../../types/pm';
import { X, UserPlus, Trash2, Loader2, AlertCircle } from 'lucide-vue-next';

const props = defineProps<{
  isOpen: boolean;
  projectUuid: string;
  members: ProjectMember[];
  canManage?: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'refresh'): void;
}>();

const targetUserId = ref<number | ''>('');
const selectedRole = ref<ProjectRole>('contributor');
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);

const handleInvite = async () => {
  if (!targetUserId.value || isSubmitting.value) return;

  isSubmitting.value = true;
  errorMessage.value = null;

  try {
    await projectsApi.inviteMember(props.projectUuid, {
      user_id: Number(targetUserId.value),
      project_role: selectedRole.value,
    });
    targetUserId.value = '';
    selectedRole.value = 'contributor';
    emit('refresh');
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to send invitation.';
  } finally {
    isSubmitting.value = false;
  }
};

const handleRemoveMember = async (memberId: number, name: string) => {
  if (!confirm(`Remove ${name} from this project?`)) return;
  try {
    await projectsApi.removeMember(props.projectUuid, memberId);
    emit('refresh');
  } catch (err) {
    console.error('Failed to remove member:', err);
  }
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs animate-in fade-in duration-150"
  >
    <div class="bg-white rounded-2xl shadow-elevated border border-[#ebe8de] w-full max-w-xl overflow-hidden animate-in zoom-in-95 duration-150">
      <!-- Header -->
      <div class="px-6 py-4 border-b border-[#ebe8de] flex items-center justify-between">
        <div>
          <h3 class="text-base font-bold text-[#640c0e] font-display">Project Team & Collaborators</h3>
          <p class="text-xs text-[#6b7280]">Manage team members, roles, and cross-department collaboration.</p>
        </div>
        <button
          type="button"
          @click="$emit('close')"
          class="p-1.5 rounded-lg text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#fbfbf9]"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <div class="p-6 space-y-6">
        <!-- Invite Form (if canManage) -->
        <div v-if="canManage" class="p-4 rounded-xl bg-[#fbfbf9] border border-[#ebe8de] space-y-3">
          <h4 class="text-xs font-bold text-[#1e1e1e] uppercase tracking-wider flex items-center gap-1.5">
            <UserPlus class="w-3.5 h-3.5 text-[#640c0e]" />
            <span>Invite Collaborator</span>
          </h4>

          <div v-if="errorMessage" class="p-2.5 rounded-lg bg-[#ea2128]/10 text-[#ea2128] text-xs flex items-center gap-2">
            <AlertCircle class="w-4 h-4 shrink-0" />
            <span>{{ errorMessage }}</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
            <div class="sm:col-span-2">
              <input
                v-model="targetUserId"
                type="number"
                placeholder="User ID (appointed MSA member)"
                class="w-full px-3.5 py-2 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none bg-white"
              />
            </div>
            <div>
              <select
                v-model="selectedRole"
                class="w-full px-3.5 py-2 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none bg-white"
              >
                <option value="manager">Manager</option>
                <option value="contributor">Contributor</option>
                <option value="viewer">Viewer</option>
              </select>
            </div>
          </div>

          <div class="flex justify-end">
            <button
              type="button"
              :disabled="!targetUserId || isSubmitting"
              @click="handleInvite"
              class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-[#640c0e] text-white hover:bg-[#7e1012] disabled:opacity-50 transition-colors shadow-xs"
            >
              <Loader2 v-if="isSubmitting" class="w-3.5 h-3.5 animate-spin" />
              <span>Send Invitation</span>
            </button>
          </div>
        </div>

        <!-- Members Roster -->
        <div class="space-y-2">
          <h4 class="text-xs font-bold text-[#1e1e1e] uppercase tracking-wider">Current Roster</h4>

          <div class="divide-y divide-[#ebe8de] max-h-72 overflow-y-auto">
            <div
              v-for="m in members"
              :key="m.id"
              class="py-3 flex items-center justify-between"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#640c0e] to-[#b02e32] text-white flex items-center justify-center text-xs font-bold">
                  {{ m.user?.name ? m.user.name.slice(0, 1) : 'U' }}
                </div>
                <div>
                  <h5 class="text-xs font-bold text-[#1e1e1e]">{{ m.user?.name || 'MSA Member' }}</h5>
                  <p class="text-[11px] text-[#6b7280]">{{ m.user?.email }}</p>
                </div>
              </div>

              <div class="flex items-center gap-2">
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full capitalize bg-[#fbfbf9] text-[#640c0e] border border-[#ebe8de]">
                  {{ m.project_role }}
                </span>

                <span
                  v-if="m.invitation_status !== 'accepted'"
                  class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 uppercase tracking-wider"
                >
                  {{ m.invitation_status }}
                </span>

                <button
                  v-if="canManage && m.project_role !== 'owner'"
                  type="button"
                  @click="handleRemoveMember(m.id, m.user?.name || 'this member')"
                  class="p-1 rounded-lg text-[#6b7280] hover:text-[#ea2128] hover:bg-[#ea2128]/5 transition-colors"
                  title="Remove from project"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
