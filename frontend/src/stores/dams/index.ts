/**
 * DAMS Pinia stores — Academy administration state.
 * Implementations remain under `@/stores/admin/academy/*` to avoid a risky mass rename.
 * Learner state remains under `@/stores/academy/*`.
 */

export { useAdminCoursesStore } from '@/stores/admin/academy/courses';
export { useAdminLessonsStore } from '@/stores/admin/academy/lessons';
export { useAdminQuizzesStore } from '@/stores/admin/academy/quizzes';
export { useAdminStudentsStore } from '@/stores/admin/academy/students';
export { useAdminMentorsStore } from '@/stores/admin/academy/mentors';
export { useAdminAssignmentsStore } from '@/stores/admin/academy/assignments';
export { useAdminProgressStore } from '@/stores/admin/academy/progress';
