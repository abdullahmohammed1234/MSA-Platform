import { easings, durations } from './presets';

/**
 * Slide-in from bottom and fade
 */
export const slideInUp = {
  initial: { opacity: 0, y: 20 },
  animate: { opacity: 1, y: 0 },
  exit: { opacity: 0, y: -20 },
  transition: { duration: durations.normal, easing: easings.premium }
};

/**
 * Slide-in from top and fade
 */
export const slideInDown = {
  initial: { opacity: 0, y: -20 },
  animate: { opacity: 1, y: 0 },
  exit: { opacity: 0, y: 20 },
  transition: { duration: durations.normal, easing: easings.premium }
};

/**
 * Slide-in from left and fade
 */
export const slideInLeft = {
  initial: { opacity: 0, x: -20 },
  animate: { opacity: 1, x: 0 },
  exit: { opacity: 0, x: 20 },
  transition: { duration: durations.normal, easing: easings.premium }
};

/**
 * Slide-in from right and fade
 */
export const slideInRight = {
  initial: { opacity: 0, x: 20 },
  animate: { opacity: 1, x: 0 },
  exit: { opacity: 0, x: -20 },
  transition: { duration: durations.normal, easing: easings.premium }
};
