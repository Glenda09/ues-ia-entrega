import { defineStore } from 'pinia'
import systemRoutes from '@/shared/router/system-routes'

// Constantes
const BIGRAM_SIZE = 2

// Utils internos para búsqueda por bigramas
function extractBigrams(text) {
  if (!text) return []
  const grams = []
  const words = String(text)
    .toLowerCase()
    .split(/[^a-z0-9áéíóúñü]+/i)
    .filter(Boolean)

  for (const w of words) {
    if (w.length < BIGRAM_SIZE) continue
    for (let i = 0; i <= w.length - BIGRAM_SIZE; i++) {
      grams.push(w.slice(i, i + BIGRAM_SIZE))
    }
  }
  return grams
}

function toFrequencyMap(arr) {
  const map = new Map()
  for (const k of arr) map.set(k, (map.get(k) || 0) + 1)
  return map
}

function scoreBigrams(query, target) {
  if (!query.length || !target.length) return 0
  const qMap = toFrequencyMap(query)
  const tMap = toFrequencyMap(target)
  let score = 0
  for (const [g, qCount] of qMap) {
    const tCount = tMap.get(g) || 0
    if (tCount) score += Math.min(qCount, tCount)
  }
  return score
}

export const useRootStore = defineStore('root', {
  state: () => ({
    isSideBarOpen: false,
    isSearchModalOpen: false,
    menu: [],
    plainMenu: [],
    searchResults: {},
    resultsNotFound: false
  }),
  actions: {
    toggleSideBarState() {
      this.isSideBarOpen = !this.isSideBarOpen
    },
    generateMenuByRouter() {
      this.menu = systemRoutes
        .filter((route) => route.meta?.show || route.meta?.base)
        .map((route) => {
          const items = []

          if (!route.meta?.base && route.meta?.show) {
            const route = {
              title: route.meta?.title,
              icon: route.meta?.icon || '',
              path: route.path
            }
            items.push(route)
          }

          if (route.meta?.base && !route.meta?.show && route.children?.length) {
            items.push(
              ...route.children
                .filter((child) => child.meta?.show)
                .map((child) => {
                  const childItem = {
                    title: child.meta?.title,
                    icon: child.meta?.icon || '',
                    path: child.path
                  }
                  return childItem
                })
            )
          }

          if (route.meta?.base && route.meta?.show && route.children?.length) {
            items.push({
              title: route.meta?.title,
              icon: route.meta?.icon || '',
              children: route.children
                .filter((child) => child.meta?.show)
                .map((child) => {
                  let childPath
                  if (child.path?.startsWith('/')) {
                    childPath = child.path
                  } else {
                    childPath = route.path + (child.path ? '/' + child.path : '')
                  }

                  const childItem = {
                    title: child.meta?.title,
                    icon: child.meta?.icon || '',
                    path: childPath
                  }
                  return childItem
                })
            })
          }

          this.menuToFlatList()

          return items
        })
        .flat()
    },
    setMenu(menu) {
      this.menu = menu
      this.plainMenu = this.menuToFlatList(menu)
    },
    menuToFlatList(menu = this.menu, parentTitle = '') {
      const list = []
      for (const item of menu) {
        if (item.children?.length) {
          list.push(...this.menuToFlatList(item.children, item.title))
        } else {
          list.push({
            title: parentTitle ? `${parentTitle} > ${item.title}` : item.title,
            icon: item.icon || '',
            path: item.path || ''
          })
        }
      }

      return list
    },
    clearSearch() {
      this.searchResults = {}
    },
    toggleSearchModal() {
      this.isSideBarOpen = false

      setTimeout(() => {
        this.isSearchModalOpen = !this.isSearchModalOpen

        if (!this.isSearchModalOpen) {
          this.clearSearch()
        } else {
          this.isSideBarOpen = false
        }
      }, 300)
    },
    search(query) {
      if (!query || query.trim() === '') {
        this.clearSearch()
        return
      }

      const externalResults = []

      const lowerQuery = query.toLowerCase()
      const qBigrams = extractBigrams(lowerQuery)
      const results = { menu: [], others: [] }

      // Búsqueda en menú
      // Calcular puntaje por coincidencias de bigramas
      for (const item of this.plainMenu) {
        const titleBigrams = extractBigrams(item.title)
        const matchScore = scoreBigrams(qBigrams, titleBigrams)
        if (matchScore > 1) {
          results.menu.push({ ...item, _score: matchScore })
        }
      }

      // Ordenar por ponderación (desc) y título (asc)
      results.menu.sort((a, b) => b._score - a._score || a.title.localeCompare(b.title))

      // Búsqueda en otras opciones
      if (externalResults.length) {
        for (const item of externalResults) {
          const titleBigrams = extractBigrams(item.title)
          const matchScore = scoreBigrams(qBigrams, titleBigrams)
          if (matchScore > 1) {
            results.others.push({ ...item, _score: matchScore })
          }
        }

        // Ordenar por ponderación (desc) y título (asc)
        results.others.sort((a, b) => b._score - a._score || a.title.localeCompare(b.title))
      }

      if (results.menu.length === 0 && results.others.length === 0) {
        this.resultsNotFound = true
      } else {
        this.resultsNotFound = false
      }

      this.searchResults = results
    }
  }
})
