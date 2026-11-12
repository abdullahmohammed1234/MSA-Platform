import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import adminRoutes from '@/router/admin'
import SystemCard from '@/components/admin/systems/SystemCard.vue'
import type { RouteRecordRaw } from 'vue-router'
import type { SystemApplication } from '@/types/systems'

function flatten(routes: RouteRecordRaw[], parent = ''): Array<{ path: string; name?: string; meta?: any }> {
  const out: Array<{ path: string; name?: string; meta?: any }> = []
  for (const r of routes) {
    const full = `${parent}/${(r.path || '').replace(/^\//, '')}`.replace(/\/+/g, '/').replace(/\/$/, '') || '/'
    out.push({ path: full === '' ? '/' : full, name: r.name as string | undefined, meta: r.meta })
    if (r.children?.length) out.push(...flatten(r.children, full === '/' ? '' : full))
  }
  return out
}

describe('Systems Phase 8 routes', () => {
  const flat = flatten(adminRoutes)

  it('uses unified application detail for apps', () => {
    const apps = ['cms', 'dams', 'main-website', 'dawah-academy', 'ems', 'connect', 'msa-connect', 'pms']
    for (const id of apps) {
      const route = flat.find((r) => r.path === `/admin/systems/${id}`)
      expect(route, id).toBeTruthy()
      expect(route!.meta?.systemId).toBe(id)
      expect(route!.meta?.permissions).toBe('system.view')
    }
  })

  it('registers platform service detail and optional consoles', () => {
    expect(flat.some((r) => r.name === 'admin-systems-service')).toBe(true)
    expect(flat.some((r) => r.path === '/admin/systems/ems/console')).toBe(true)
    expect(flat.some((r) => r.path === '/admin/systems/main-website/console')).toBe(true)
    expect(flat.some((r) => r.path === '/admin/systems/dawah-academy/console')).toBe(true)
  })
})

describe('SystemCard component', () => {
  const baseApp: SystemApplication = {
    id: 'msa-connect',
    name: 'MSA Connect',
    description: 'Member application',
    type: 'application',
    status: 'operational',
    health_status: 'operational',
    connection_status: {},
    version: '1.0.0',
    url: 'http://localhost:5174',
    admin_path: '/admin/systems/msa-connect',
    dependencies: [],
    last_checked_at: new Date().toISOString(),
    errors: [],
    recent_incidents: [],
  }

  it('renders an external anchor tag for external app URLs', () => {
    const wrapper = mount(SystemCard, {
      props: { app: baseApp },
      global: {
        stubs: {
          'router-link': {
            template: '<a :href="to"><slot /></a>',
            props: ['to']
          }
        }
      }
    })

    const openLinks = wrapper.findAll('a').filter(a => a.text().includes('Open application'))
    expect(openLinks.length).toBe(1)
    expect(openLinks[0].attributes('href')).toBe('http://localhost:5174')
    expect(openLinks[0].attributes('target')).toBe('_blank')
    expect(openLinks[0].attributes('rel')).toBe('noopener noreferrer')
  })

  it('renders a router-link for internal app URLs', () => {
    const internalApp: SystemApplication = {
      ...baseApp,
      id: 'cms',
      name: 'Content Management System',
      url: '/cms',
      admin_path: '/admin/systems/cms'
    }

    const wrapper = mount(SystemCard, {
      props: { app: internalApp },
      global: {
        stubs: {
          'router-link': {
            template: '<a class="router-link-stub" :href="to"><slot /></a>',
            props: ['to']
          }
        }
      }
    })

    const openLink = wrapper.find('.router-link-stub')
    expect(openLink.exists()).toBe(true)
    expect(openLink.attributes('href')).toBe('/cms')
    expect(openLink.attributes('target')).toBeUndefined()
  })

  it('renders PMS external link correctly', () => {
    const pmsApp: SystemApplication = {
      ...baseApp,
      id: 'pms',
      name: 'Project Management System (PMS)',
      url: 'https://projects.sfumsa.ca',
      admin_path: '/admin/systems/pms'
    }

    const wrapper = mount(SystemCard, {
      props: { app: pmsApp },
      global: {
        stubs: {
          'router-link': {
            template: '<a :href="to"><slot /></a>',
            props: ['to']
          }
        }
      }
    })

    const openLinks = wrapper.findAll('a').filter(a => a.text().includes('Open application'))
    expect(openLinks.length).toBe(1)
    expect(openLinks[0].attributes('href')).toBe('https://projects.sfumsa.ca')
    expect(openLinks[0].attributes('target')).toBe('_blank')
  })
})
