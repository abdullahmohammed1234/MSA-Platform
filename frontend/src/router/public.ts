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
        redirect: '/sponsorship'
      },
      { 
        path: 'sponsorship', 
        name: 'public-sponsorship', 
        component: () => import('@/pages/public/PublicSponsorshipPage.vue'), 
        meta: { title: 'Corporate & Community Sponsorship | SFU MSA', desc: 'Partner with SFU MSA and support campus community programs.' } 
      },
      { 
        path: 'sponsorship/inquire', 
        name: 'public-sponsorship-inquire', 
        component: () => import('@/pages/public/PublicSponsorshipInquirePage.vue'), 
        meta: { title: 'Become a Partner | SFU MSA', desc: 'Submit a sponsorship or partnership inquiry.' } 
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
      },
      // Public Store — Catalogue, Cart, Checkout, Success, My Orders
      {
        path: 'store',
        name: 'public-store-catalogue',
        component: () => import('@/pages/public/store/StoreCataloguePage.vue'),
        meta: { title: 'MSA Store | SFU MSA', desc: 'Browse official SFU MSA merchandise and gear.' },
      },
      {
        path: 'store/product/:slug',
        name: 'public-store-product-detail',
        component: () => import('@/pages/public/store/StoreProductDetailPage.vue'),
        meta: { title: 'Product Details | SFU MSA Store', desc: 'View merchandise details and select options.' },
      },
      {
        path: 'store/cart',
        name: 'public-store-cart',
        component: () => import('@/pages/public/store/StoreCartPage.vue'),
        meta: { title: 'Shopping Cart | SFU MSA Store', desc: 'Review your cart items.' },
      },
      {
        path: 'store/checkout',
        name: 'public-store-checkout',
        component: () => import('@/pages/public/store/StoreCheckoutPage.vue'),
        meta: { title: 'Store Checkout | SFU MSA Store', desc: 'Complete merchandise checkout.' },
      },
      {
        path: 'store/checkout/success',
        name: 'public-store-checkout-success',
        component: () => import('@/pages/public/store/StoreCheckoutSuccessPage.vue'),
        meta: { title: 'Order Confirmed | SFU MSA Store', desc: 'Merchandise order received.' },
      },
      {
        path: 'store/my-orders',
        name: 'public-store-my-orders',
        component: () => import('@/pages/public/store/StoreMyOrdersPage.vue'),
        meta: { title: 'My Store Orders | SFU MSA', desc: 'View past merchandise purchases.', requiresAuth: true },
      },
      // Public Donations
      {
        path: 'donate',
        name: 'public-donate',
        component: () => import('@/pages/public/DonatePage.vue'),
        meta: { title: 'Donate | SFU MSA', desc: 'Support SFU MSA student programs, Friday prayer, and community services.' },
      },
      {
        path: 'donate/success',
        name: 'public-donate-success',
        component: () => import('@/pages/public/DonateSuccessPage.vue'),
        meta: { title: 'Donation Received | SFU MSA', desc: 'Thank you for supporting SFU MSA.' },
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
