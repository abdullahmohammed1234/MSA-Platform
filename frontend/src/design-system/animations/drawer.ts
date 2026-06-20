import { easings, durations } from './presets';

/**
 * Slide transition from left side
 */
export const drawerLeft = {
  initial: { x: '-100%' },
  animate: { x: 0 },
  exit: { x: '-100%' },
  transition: { duration: durations.normal, easing: easings.premium }
};

/**
 * Slide transition from right side
 */
export const drawerRight = {
  initial: { x: '100%' },
  animate: { x: 0 },
  exit: { x: '100%' },
  transition: { duration: durations.normal, easing: easings.premium }
};

/**
 * Slide transition from bottom edge
 */
export const drawerBottom = {
  initial: { y: '100%' },
  animate: { y: 0 },
  exit: { y: '100%' },
  transition: { duration: durations.normal, easing: easings.premium }
};
