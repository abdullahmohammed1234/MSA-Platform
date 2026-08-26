import { describe, it, expect } from 'vitest'
import adminRoutes from '@/router/admin'
import damsRoutes from '@/router/dams'
import cmsRoutes from '@/router/cms'
import type { RouteRecordRaw } from 'vue-router'

function flattenRoutes(routes: RouteRecordRaw[], parent = ''): Array<{ path: string; redirect?: unknown; name?: string }> {
  const out: Array<{ path: string; redirect?: unknown; name?: string }> = []
  for (const r of routes) {
    const full = `${parent}/${(r.path || '').replace(/^\//, '')}`.replace(/\/+/g, '/').replace(/\/$/, '') || '/'
    out.push({ path: full === '' ? '/' : full, redirect: r.redirect, name: r.name as string | undefined })
    if (r.children?.length) {
      out.push(...flattenRoutes(r.children, full === '/' ? '' : full))
    }
  }
  return out
}

describe('DAMS Phase 5 route ownership', () => {
  const adminFlat = flattenRoutes(adminRoutes)
  const damsFlat = flattenRoutes(damsRoutes)
  const cmsFlat = flattenRoutes(cmsRoutes)

  it('registers DAMS application at /dams with dashboard', () => {
    expect(damsFlat.some((r) => r.path === '/dams' || r.name === 'dams-dashboard')).toBe(true)
    expect(damsFlat.some((r) => r.name === 'dams-courses')).toBe(true)
    expect(damsFlat.some((r) => r.name === 'dams-students')).toBe(true)
    expect(damsFlat.some((r) => r.name === 'dams-live-admin')).toBe(true)
  })

  it('redirects legacy MSA Admin academy URLs to DAMS', () => {
    const expected: Array<[string, string]> = [
      ['/admin/academy/courses', '/dams/courses'],
      ['/admin/academy/modules', '/dams/modules'],
      ['/admin/academy/lessons', '/dams/lessons'],
      ['/admin/academy/quizzes', '/dams/quizzes'],
      ['/admin/academy/students', '/dams/students'],
      ['/admin/academy/mentors', '/dams/mentors'],
      ['/admin/academy/progress', '/dams/progress'],
      ['/admin/academy/moderation', '/dams/moderation'],
      ['/admin/academy/settings', '/dams/settings'],
      ['/admin/academy/live-admin', '/dams/live-admin'],
      ['/admin/achievements', '/dams/achievements'],
      ['/admin/badges', '/dams/badges'],
      ['/admin/learning-paths', '/dams/learning-paths'],
    ]

    for (const [from, to] of expected) {
      const route = adminFlat.find((r) => r.path === from)
      expect(route, `missing redirect route ${from}`).toBeTruthy()
      expect(route!.redirect).toBe(to)
    }
  })

  it('keeps announcements on CMS and user-management on Platform', () => {
    const announcements = adminFlat.find((r) => r.path === '/admin/academy/announcements')
    expect(announcements?.redirect).toBe('/cms/announcements')

    const userMgmt = adminFlat.find((r) => r.path === '/admin/academy/user-management')
    expect(userMgmt?.redirect).toBeUndefined()
    expect(userMgmt?.name).toBe('admin-academy-user-management')

    expect(cmsFlat.some((r) => r.path.includes('/cms'))).toBe(true)
  })

  it('registers Systems DAMS page under MSA Admin', () => {
    expect(adminFlat.some((r) => r.name === 'admin-systems-dams')).toBe(true)
  })

  it('does not render academy courses component under /admin (redirect only)', () => {
    const courses = adminFlat.find((r) => r.path === '/admin/academy/courses')
    expect(courses?.redirect).toBe('/dams/courses')
  })
})
