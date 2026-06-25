import type { RouteRecordRaw } from 'vue-router'

const publicRoutes: Array<RouteRecordRaw> = [
  {
    path: '/',
    component: () => import('@/layouts/PublicLayout.vue'),
    children: [
      { 
        path: '', 
        name: 'home', 
        component: () => import('@/pages/public/HomePage.vue'), 
        meta: { title: 'Simon Fraser University MSA', desc: 'SFU Muslim Students Association official portal.' } 
      },
      { 
        path: 'about', 
        name: 'about', 
        component: () => import('@/pages/public/AboutPage.vue'), 
        meta: { title: 'About Us | SFU MSA', desc: 'Discover our mission, vision, history, and core values.' } 
      },
      { 
        path: 'team', 
        name: 'team', 
        component: () => import('@/pages/public/TeamPage.vue'), 
        meta: { title: 'Our Team | SFU MSA', desc: 'Meet the executive team and coordinators driving the SFU MSA.' } 
      },
      { 
        path: 'events', 
        name: 'events', 
        component: () => import('@/pages/public/EventsPage.vue'), 
        meta: { title: 'Events | SFU MSA', desc: 'Stay updated on upcoming seminars, congregations, and socials.' } 
      },
      { 
        path: 'resources', 
        name: 'resources', 
        component: () => import('@/pages/public/ResourcesPage.vue'), 
        meta: { title: 'Resources | SFU MSA', desc: 'Student accommodation guides, halal food maps, and mental health directories.' } 
      },
      { 
        path: 'contact', 
        name: 'contact', 
        component: () => import('@/pages/public/ContactPage.vue'), 
        meta: { title: 'Contact Us | SFU MSA', desc: 'Get in touch with the SFU Muslim Students Association.' } 
      },
      { 
        path: 'sponsors', 
        name: 'sponsors', 
        component: () => import('@/pages/public/SponsorsPage.vue'), 
        meta: { title: 'Sponsors | SFU MSA', desc: 'Partner with SFU MSA and support campus community programs.' } 
      },
      { 
        path: 'donations', 
        name: 'donations', 
        component: () => import('@/pages/public/DonationsPage.vue'), 
        meta: { title: 'Support Us | SFU MSA', desc: 'Contribute to our physical prayer rooms and community programs.' } 
      },
      { 
        path: 'prayer', 
        name: 'prayer', 
        component: () => import('@/pages/public/PrayerPage.vue'), 
        meta: { title: 'Prayer Times & Spaces | SFU MSA', desc: 'Daily and Friday Jumu\'ah prayer times and musalla locations at SFU.' } 
      },
      { 
        path: 'media', 
        name: 'media', 
        component: () => import('@/pages/public/MediaPage.vue'), 
        meta: { title: 'Media Gallery | SFU MSA', desc: 'Visual archives capturing community gatherings, lectures, and memories.' } 
      },
      {
        path: 'volunteer',
        redirect: '/contact'
      }
    ]
  },
  {
    path: '/design-system',
    name: 'design-system',
    component: () => import('@/pages/DesignSystem.vue')
  }
]

export default publicRoutes
