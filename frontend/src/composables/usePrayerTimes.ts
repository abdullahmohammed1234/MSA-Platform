import { ref, onMounted } from 'vue';

export type Campus = 'Burnaby' | 'Surrey' | 'Vancouver';
export type PrayerTab = Campus | "Jumu'ah";
export type PrayerName = 'Fajr' | 'Dhuhr' | 'Asr' | 'Maghrib' | 'Isha';

export interface CampusPrayerInfo {
  name: string;
  location: string;
  rooms: string[];
  coordinates: {
    latitude: number;
    longitude: number;
  };
  mapUrl: string;
  wuduDetails: string;
}

export interface JumuahTiming {
  label: string;
  time: string;
}

export interface JumuahSession {
  id: string;
  name: string;
  location: string;
  timings: JumuahTiming[];
}

export const prayerTabs: PrayerTab[] = ['Burnaby', 'Surrey', 'Vancouver', "Jumu'ah"];

export const jumuahSessions: JumuahSession[] = [
  {
    id: 'burnaby-1',
    name: "Burnaby 1st Jumu'ah",
    location: 'Educational Building Gym',
    timings: [
      { label: 'Setup', time: '1:00 PM' },
      { label: 'Khutbah', time: '1:30 PM' },
      { label: 'Iqamah', time: '2:00 PM' },
    ],
  },
  {
    id: 'burnaby-2',
    name: "Burnaby 2nd Jumu'ah",
    location: 'Educational Building Gym',
    timings: [
      { label: 'Khutbah', time: '2:40 PM' },
      { label: 'Iqamah', time: '2:50 PM' },
    ],
  },
  {
    id: 'surrey-1',
    name: "Surrey 1st Jumu'ah",
    location: 'SRYE 1005',
    timings: [
      { label: 'Setup', time: '1:10 PM' },
      { label: 'Khutbah', time: '1:30 PM' },
      { label: 'Iqamah', time: '2:00 PM' },
    ],
  },
];

export interface PrayerTime {
  name: PrayerName;
  time: string;
}

export type PrayerTimesByCampus = Partial<Record<Campus, PrayerTime[]>>;

const PRAYER_NAMES: PrayerName[] = ['Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'];

export const campusPrayerInfo: Record<Campus, CampusPrayerInfo> = {
  Burnaby: {
    name: 'SFU Burnaby',
    location: 'AQ 3200, SUB 2402, and Residence Prayer Room',
    rooms: [
      'AQ 3200, on the east concourse between Renaissance Coffee and the Mackenzie Cafeteria',
      'SUB 2402, 2nd floor',
      'Residence Prayer Room',
    ],
    coordinates: { latitude: 49.2781, longitude: -122.9199 },
    mapUrl: 'https://www.sfu.ca/maps/burnaby-campus.html',
    wuduDetails: 'Wudu access varies by building. Use nearby washrooms and check posted room guidance for each prayer space.',
  },
  Surrey: {
    name: 'SFU Surrey',
    location: 'SRYC 3002 and SRYE 3004',
    rooms: [
      'SRYC 3002, Central City',
      'SRYE 3004, Engineering building',
    ],
    coordinates: { latitude: 49.1897, longitude: -122.849 },
    mapUrl: 'https://www.sfu.ca/maps/surrey-campus.html',
    wuduDetails: 'Wudu can be performed in nearby campus washrooms before entering the prayer rooms.',
  },
  Vancouver: {
    name: 'SFU Vancouver',
    location: 'Harbour Centre, Room 7314',
    rooms: ['Room 7314, Harbour Centre'],
    coordinates: { latitude: 49.2847, longitude: -123.1119 },
    mapUrl: 'https://www.sfu.ca/maps/vancouver-campus.html',
    wuduDetails: 'Use nearby Harbour Centre washrooms before entering the prayer space.',
  },
};

export const fallbackPrayerTimes: PrayerTime[] = PRAYER_NAMES.map((name) => ({
  name,
  time: 'Updating',
}));

function formatPrayerTime(value: string) {
  const cleanValue = value.split(' ')[0];
  const [hourValue, minute] = cleanValue.split(':').map(Number);
  if (Number.isNaN(hourValue) || Number.isNaN(minute)) return value;

  const period = hourValue >= 12 ? 'PM' : 'AM';
  const hour = hourValue % 12 || 12;
  return `${hour}:${minute.toString().padStart(2, '0')} ${period}`;
}

function getTodayForApi() {
  return new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    timeZone: 'America/Vancouver',
  }).format(new Date()).replace(/\//g, '-');
}

async function fetchCampusPrayerTimes(campus: Campus): Promise<[Campus, PrayerTime[]]> {
  const { latitude, longitude } = campusPrayerInfo[campus].coordinates;
  const date = getTodayForApi();
  const params = new URLSearchParams({
    latitude: latitude.toString(),
    longitude: longitude.toString(),
    method: '2',
    school: '1',
    timezonestring: 'America/Vancouver',
  });

  const response = await fetch(`https://api.aladhan.com/v1/timings/${date}?${params.toString()}`);
  if (!response.ok) {
    throw new Error(`Unable to fetch prayer times for ${campus}`);
  }

  const payload = await response.json();
  const timings = payload?.data?.timings ?? {};
  return [
    campus,
    PRAYER_NAMES.map((name) => ({
      name,
      time: formatPrayerTime(timings[name] ?? 'Updating'),
    })),
  ];
}

async function fetchAllPrayerTimes(): Promise<PrayerTimesByCampus> {
  const response = await fetch('/api/v1/website/prayer-times');
  if (!response.ok) {
    throw new Error('Unable to fetch prayer times');
  }

  const payload = await response.json();
  return payload.times ?? {};
}

export function usePrayerTimes() {
  const times = ref<PrayerTimesByCampus>({});
  const isLoading = ref(true);
  const error = ref<string | null>(null);

  const loadPrayerTimes = async () => {
    try {
      let nextTimes: PrayerTimesByCampus;
      try {
        nextTimes = await fetchAllPrayerTimes();
      } catch {
        const entries = await Promise.all(
          (Object.keys(campusPrayerInfo) as Campus[]).map(fetchCampusPrayerTimes)
        );
        nextTimes = Object.fromEntries(entries) as PrayerTimesByCampus;
      }
      times.value = nextTimes;
      error.value = null;
    } catch (err) {
      console.error('Failed to load prayer times:', err);
      error.value = 'Prayer times could not be updated. Please try again later.';
    } finally {
      isLoading.value = false;
    }
  };

  onMounted(() => {
    loadPrayerTimes();
  });

  return { times, isLoading, error };
}
