import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useAuthorization } from './useAuthorization';

export function useAppAccess() {
  const authStore = useAuthStore();
  const { hasPermission } = useAuthorization();

  const isSuperOrAdmin = computed(() => {
    if (typeof authStore.isPrivilegedAdmin === 'boolean') {
      return authStore.isPrivilegedAdmin;
    }
    return authStore.roles.includes('admin') || authStore.roles.includes('super-admin');
  });

  const hasCmsAccess = computed(() => {
    if (isSuperOrAdmin.value) return true;
    const cmsPermissions = [
      'manage_homepage',
      'manage_announcements',
      'manage_team',
      'manage_resources',
      'manage_media',
      'view_analytics',
      'view_reports',
      'manage_analytics',
      'export_analytics',
    ];
    return cmsPermissions.some((p) => hasPermission(p));
  });

  const hasDamsAccess = computed(() => {
    if (isSuperOrAdmin.value) return true;
    const damsPermissions = [
      'manage_courses',
      'manage_modules',
      'manage_lessons',
      'manage_quizzes',
      'manage_learning_paths',
      'manage_mentors',
      'manage_students',
      'view_progress',
      'manage_achievements',
      'manage_badges',
      'manage_settings',
      'manage_notifications',
      'manage_discussions',
      'view_analytics',
      'view_reports',
      'manage_analytics',
      'export_analytics',
    ];
    return damsPermissions.some((p) => hasPermission(p));
  });

  const hasEmsAccess = computed(() => {
    const EMS_ROLES = ['super-admin', 'event-administrator', 'event-organizer', 'event-staff'];
    // EMS calculation uses existing EMS rules exactly, plus admin/super-admin bypass
    return (authStore.user?.roles?.some((role) => EMS_ROLES.includes(role)) ?? false) || isSuperOrAdmin.value;
  });

  const hasAdminAccess = computed(() => {
    return isSuperOrAdmin.value;
  });

  return {
    hasCmsAccess,
    hasDamsAccess,
    hasEmsAccess,
    hasAdminAccess,
  };
}
