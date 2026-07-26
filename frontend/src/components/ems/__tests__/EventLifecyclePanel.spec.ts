import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import EventLifecyclePanel from '../EventLifecyclePanel.vue';
import type { Event, EventAvailableTransition } from '@/types/ems';

/**
 * The panel must render actions purely from what the API offers. If it ever
 * starts deciding for itself which transition comes next, the frontend and the
 * backend state machine can drift apart.
 */
const makeEvent = (overrides: Partial<Event> = {}): Event =>
  ({
    uuid: 'event-uuid',
    name: 'MSA Welcome Night',
    status: 'registration_open',
    status_label: 'Registration Open',
    status_tone: 'success',
    available_transitions: [],
    ...overrides,
  }) as Event;

const transition = (overrides: Partial<EventAvailableTransition> = {}): EventAvailableTransition => ({
  action: 'close_registration',
  label: 'Close Registration',
  to: 'registration_closed',
  to_label: 'Registration Closed',
  confirmation: 'Close registration for this event?',
  irreversible: false,
  permitted: true,
  ...overrides,
});

describe('EventLifecyclePanel', () => {
  it('shows the current state using the label and tone the API supplied', () => {
    const wrapper = mount(EventLifecyclePanel, {
      props: { event: makeEvent() },
    });

    expect(wrapper.text()).toContain('Registration Open');
  });

  it('renders one button per permitted transition', () => {
    const wrapper = mount(EventLifecyclePanel, {
      props: { event: makeEvent({ available_transitions: [transition()] }) },
    });

    const buttons = wrapper.findAll('button');
    expect(buttons).toHaveLength(1);
    expect(buttons[0].text()).toContain('Close Registration');
  });

  it('emits the transition immediately for a reversible action', async () => {
    const wrapper = mount(EventLifecyclePanel, {
      props: { event: makeEvent({ available_transitions: [transition()] }) },
    });

    await wrapper.find('button').trigger('click');

    expect(wrapper.emitted('transition')).toEqual([['close_registration']]);
  });

  it('asks for confirmation before an irreversible action, and does not emit until confirmed', async () => {
    const wrapper = mount(EventLifecyclePanel, {
      props: {
        event: makeEvent({
          status: 'live',
          status_label: 'Live',
          status_tone: 'live',
          available_transitions: [
            transition({
              action: 'complete',
              label: 'Complete',
              to: 'completed',
              to_label: 'Completed',
              confirmation: 'Complete this event? This cannot be undone.',
              irreversible: true,
            }),
          ],
        }),
      },
    });

    await wrapper.find('button').trigger('click');

    expect(wrapper.emitted('transition')).toBeUndefined();
    expect(wrapper.text()).toContain('This cannot be undone.');
  });

  it('does not offer a transition the viewer lacks permission for', () => {
    const wrapper = mount(EventLifecyclePanel, {
      props: {
        event: makeEvent({ available_transitions: [transition({ permitted: false })] }),
      },
    });

    expect(wrapper.findAll('button')).toHaveLength(0);
    expect(wrapper.text()).toContain('you do not have permission to perform');
  });

  it('says the lifecycle is finished when the state offers nothing further', () => {
    const wrapper = mount(EventLifecyclePanel, {
      props: {
        event: makeEvent({
          status: 'archived',
          status_label: 'Archived',
          status_tone: 'muted',
          available_transitions: [],
        }),
      },
    });

    expect(wrapper.text()).toContain('end of its lifecycle');
    expect(wrapper.findAll('button')).toHaveLength(0);
  });
});
