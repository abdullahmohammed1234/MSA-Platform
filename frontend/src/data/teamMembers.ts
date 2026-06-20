import { TEAM_FALLBACK_IMAGE, teamImage } from '@/constants/publicAssets';

/** Default executive council roster (mirrors Main/src/pages/Team.tsx). Used when the API is unavailable. */
export interface TeamMemberRecord {
  name: string;
  role: string;
  dept: string;
  img: string;
}

export const DEFAULT_TEAM_MEMBERS: TeamMemberRecord[] = [
  { name: 'FATIMA HAYAT', role: 'President', dept: 'President', img: teamImage('Fatima+hayat.webp') },
  { name: 'HAMMAD ZAIDI', role: 'Brothers VP', dept: 'Vice Presidents', img: teamImage('Hammad+Zaidi.webp') },
  { name: 'HAFSA IRSHAD', role: 'Sisters VP', dept: 'Vice Presidents', img: teamImage('Hafsa+Irshad.webp') },
  { name: 'LEEZA ABDULFATAH', role: 'Secretary', dept: 'Secretary', img: TEAM_FALLBACK_IMAGE },
  { name: 'WAJIHAH MALIK', role: 'Director of Finance', dept: 'Directors', img: teamImage('Wajihah+Malik.webp') },
  { name: 'AHAD ALI', role: 'Finance Coordinator', dept: 'Coordinators', img: teamImage('Ahad+Ali.webp') },
  { name: 'MARYAM SHAH', role: 'Finance Coordinator', dept: 'Coordinators', img: teamImage('Maryam+Shah.webp') },
  { name: 'RUMEJSA REXHAJ', role: 'Sponsorship Outreach Coordinator', dept: 'Coordinators', img: teamImage('Rumejsa+Rexhaj.webp') },
  { name: 'AMNA SIDDIQUI', role: 'Logistics Outreach Coordinator', dept: 'Coordinators', img: teamImage('Amna+Siddiqui.webp') },
  { name: 'HAMZA ADNAN KAMDAR', role: 'Marketing Lead', dept: 'Directors', img: teamImage('hamza.webp') },
  { name: 'TOQA ABU ALKAS', role: 'Marketing Lead', dept: 'Directors', img: teamImage('Toqa+Abu+Alkas.webp') },
  { name: 'MAHNOOR WASIM', role: 'Marketing Coordinator', dept: 'Coordinators', img: teamImage('Mahnoor+Wasim.webp') },
  { name: 'CHOROUK RAFI', role: 'Marketing Coordinator', dept: 'Coordinators', img: teamImage('Chorouk_Rafi.webp') },
  { name: 'MARYAM MOHSEN', role: 'Lead Graphics Designer', dept: 'Directors', img: TEAM_FALLBACK_IMAGE },
  { name: 'ESHAAL PATEL', role: 'Graphics Designer', dept: 'Coordinators', img: TEAM_FALLBACK_IMAGE },
  { name: 'SHARON DEO', role: 'Graphics Designer', dept: 'Coordinators', img: teamImage('sharon.webp') },
  { name: 'RASSELL LABASH', role: 'Director of Events', dept: 'Directors', img: teamImage('Rassell+Labash.webp') },
  { name: 'ZAKARIYA HAMDY', role: 'Events Coordinator', dept: 'Coordinators', img: teamImage('Zakariya+Hamdy.webp') },
  { name: 'QUSAI SHERIF', role: 'Events Coordinator', dept: 'Coordinators', img: TEAM_FALLBACK_IMAGE },
  { name: 'MANAL FATIMA', role: 'Events Coordinator', dept: 'Coordinators', img: teamImage('Manal_Fatima.webp') },
  { name: 'OMAR ALI', role: 'Director of Education', dept: 'Directors', img: teamImage('Omar+Salem.webp') },
  { name: 'LEENA KANADIL', role: 'Education Coordinator', dept: 'Coordinators', img: teamImage('Leena_Kanadil.webp') },
  { name: 'FAROUK TOUNI', role: 'Education Coordinator', dept: 'Coordinators', img: TEAM_FALLBACK_IMAGE },
  { name: 'ZAYED KHAN', role: 'NCCM Coordinator', dept: 'Coordinators', img: teamImage('Zayed+Khan.webp') },
  { name: 'ZULKIFL BIN ANOWAR', role: 'Prayer Services Coordinator', dept: 'Coordinators', img: teamImage('Zulkifl+Bin+Anowar.webp') },
  { name: 'ABDALLA ELBORAEI', role: 'IT Coordinator', dept: 'Coordinators', img: teamImage('Abdullah+Elboraei.webp') },
];
