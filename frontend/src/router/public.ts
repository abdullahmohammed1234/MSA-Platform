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
      // Public events — EMS discovery, registration & checkout (keeps /events URL)
      {
        path: 'events',
        name: 'events',
        component: () => import('@/pages/public/ems/EmsPublicEventsPage.vue'),
        meta: {
          title: 'Events | SFU MSA',
          desc: 'Browse upcoming SFU MSA community events and register for free.',
        },
      },
      {
        path: 'events/calendar',
        name: 'ems-public-calendar',
        component: () => import('@/pages/public/ems/EmsPublicCalendarPage.vue'),
        meta: {
          title: 'Event Calendar | SFU MSA',
          desc: 'Monthly and weekly calendar of SFU MSA events.',
        },
      },
      {
        path: 'events/:slug/checkout/success',
        name: 'ems-checkout-success',
        component: () => import('@/pages/public/ems/EmsCheckoutSuccessPage.vue'),
        meta: {
          title: 'Payment Confirmed | SFU MSA',
          desc: 'Your Square payment was received.',
        },
      },
      {
        path: 'events/:slug/checkout/cancel',
        name: 'ems-checkout-cancel',
        component: () => import('@/pages/public/ems/EmsCheckoutCancelPage.vue'),
        meta: {
          title: 'Checkout Cancelled | SFU MSA',
          desc: 'Checkout was cancelled. No payment was taken.',
        },
      },
      {
        path: 'events/:slug',
        name: 'ems-public-event',
        component: () => import('@/pages/public/ems/EmsPublicEventDetailPage.vue'),
        meta: {
          title: 'Event | SFU MSA',
          desc: 'Event details and registration.',
        },
      },
      // Legacy /ems-events URLs → /events (bookmarks & old Square redirects)
      { path: 'ems-events', redirect: '/events' },
      { path: 'ems-events/calendar', redirect: '/events/calendar' },
      {
        path: 'ems-events/:slug/checkout/success',
        redirect: (to) => ({
          name: 'ems-checkout-success',
          params: { slug: to.params.slug },
          query: to.query,
        }),
      },
      {
        path: 'ems-events/:slug/checkout/cancel',
        redirect: (to) => ({
          name: 'ems-checkout-cancel',
          params: { slug: to.params.slug },
          query: to.query,
        }),
      },
      {
        path: 'ems-events/:slug',
        redirect: (to) => ({
          name: 'ems-public-event',
          params: { slug: to.params.slug },
          query: to.query,
        }),
      },
      {
        path: 'my-tickets',
        name: 'ems-my-tickets',
        component: () => import('@/pages/public/ems/EmsMyTicketsPage.vue'),
        meta: {
          title: 'My Tickets | SFU MSA',
          desc: 'Manage your event registrations and tickets.',
          requiresAuth: true,
        },
      },
      {
        path: 'tickets/:code',
        name: 'ems-public-ticket',
        component: () => import('@/pages/public/ems/EmsPublicTicketPage.vue'),
        meta: {
          title: 'My Ticket | SFU MSA',
          desc: 'View your SFU MSA event ticket and QR code.',
        },
      },
      // { 
      //   path: 'resources', 
      //   name: 'resources', 
      //   component: () => import('@/pages/public/ResourcesPage.vue'), 
      //   meta: { title: 'Resources | SFU MSA', desc: 'Student accommodation guides, halal food maps, and mental health directories.' } 
      // },
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
        redirect: '/contact?volunteer=true'
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
