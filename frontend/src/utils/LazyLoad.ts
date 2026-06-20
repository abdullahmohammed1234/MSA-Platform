import { defineAsyncComponent, h } from 'vue'

interface LazyLoadOptions {
  loading?: any
  error?: any
  delay?: number
  timeout?: number
}

/**
 * Wraps a component loader with defineAsyncComponent and provides a premium default loading spinner.
 */
export function lazyLoadComponent(loader: () => Promise<any>, options: LazyLoadOptions = {}) {
  return defineAsyncComponent({
    loader,
    loadingComponent: options.loading || {
      render() {
        return h('div', { 
          class: 'flex flex-col items-center justify-center p-12 bg-slate-950/40 border border-white/5 rounded-2xl min-h-[200px] backdrop-blur-sm' 
        }, [
          h('svg', { 
            class: 'animate-spin h-8 w-8 text-indigo-400 mb-3', 
            xmlns: 'http://www.w3.org/2000/svg', 
            fill: 'none', 
            viewBox: '0 0 24 24' 
          }, [
            h('circle', { class: 'opacity-25', cx: '12', cy: '12', r: '10', stroke: 'currentColor', 'stroke-width': '4' }),
            h('path', { class: 'opacity-75', fill: 'currentColor', d: 'M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z' })
          ]),
          h('span', { class: 'text-sm text-slate-400 font-medium' }, 'Loading component...')
        ])
      }
    },
    delay: options.delay ?? 200,
    timeout: options.timeout ?? 30000,
    suspensible: false
  })
}
