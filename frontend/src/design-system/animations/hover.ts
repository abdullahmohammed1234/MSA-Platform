import { springs } from './presets';

/**
 * Scale and hover lift transitions for buttons and links
 */
export const buttonHover = {
  hover: { scale: 1.02, y: -2 },
  tap: { scale: 0.98 },
  transition: springs.subtle
};

/**
 * Elevation, shadow, and border transitions for cards
 */
export const cardHover = {
  hover: { y: -8 },
  transition: { duration: 0.3, ease: 'easeOut' }
};

/**
 * Focus scale transition for text inputs
 */
export const inputFocus = {
  focus: { scale: 1.01 },
  transition: springs.subtle
};
