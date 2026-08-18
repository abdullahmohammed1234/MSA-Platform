const STORAGE_KEY = 'ems.hidden_tickets.v1';

interface HiddenTicketsState {
  registrationUuids: string[];
  pendingSlugs: string[];
}

function emptyState(): HiddenTicketsState {
  return { registrationUuids: [], pendingSlugs: [] };
}

function read(): HiddenTicketsState {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return emptyState();
    const parsed = JSON.parse(raw) as Partial<HiddenTicketsState>;
    return {
      registrationUuids: Array.isArray(parsed.registrationUuids) ? parsed.registrationUuids : [],
      pendingSlugs: Array.isArray(parsed.pendingSlugs) ? parsed.pendingSlugs : [],
    };
  } catch {
    return emptyState();
  }
}

function write(state: HiddenTicketsState): void {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      registrationUuids: state.registrationUuids.slice(0, 200),
      pendingSlugs: state.pendingSlugs.slice(0, 50),
    }));
  } catch {
    // Private browsing or quota — hide still works for this visit via the caller.
  }
}

export const hiddenTicketsStorage = {
  hasRegistration(uuid: string): boolean {
    return read().registrationUuids.includes(uuid);
  },

  hasPending(slug: string): boolean {
    return read().pendingSlugs.includes(slug);
  },

  hideRegistration(uuid: string): void {
    const state = read();
    if (!state.registrationUuids.includes(uuid)) {
      state.registrationUuids.push(uuid);
      write(state);
    }
  },

  hidePending(slug: string): void {
    const state = read();
    if (!state.pendingSlugs.includes(slug)) {
      state.pendingSlugs.push(slug);
      write(state);
    }
  },
};

export default hiddenTicketsStorage;
