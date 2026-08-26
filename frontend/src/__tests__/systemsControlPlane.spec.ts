import { describe, it, expect } from 'vitest'
import adminRoutes from '@/router/admin'
import type { RouteRecordRaw } from 'vue-router'

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

  it('uses unified application detail for all five apps', () => {
    const apps = ['cms', 'dams', 'main-website', 'dawah-academy', 'ems']
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
