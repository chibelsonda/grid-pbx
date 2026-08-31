export type ShellThemeRegion = 'header' | 'sidebar'

export type ShellTheme = {
  id: string
  label: string
  swatch: string
  tokens: {
    background: string
    border: string
    foreground: string
    muted: string
    hover: string
    activeBackground: string
    activeForeground: string
    accent: string
  }
}

const lightTheme: ShellTheme = {
  id: 'light',
  label: 'Cloud',
  swatch: '#ffffff',
  tokens: {
    background: '#ffffff',
    border: '#e2e8f0',
    foreground: '#334155',
    muted: '#64748b',
    hover: '#f8fafc',
    activeBackground: '#eef2ff',
    activeForeground: '#3255ad',
    accent: '#3f6ad8',
  },
}

const darkTheme = (id: string, label: string, background: string, accent: string): ShellTheme => ({
  id,
  label,
  swatch: background,
  tokens: {
    background,
    border: 'rgb(255 255 255 / 12%)',
    foreground: '#f8fafc',
    muted: '#cbd5e1',
    hover: 'rgb(255 255 255 / 8%)',
    activeBackground: 'rgb(255 255 255 / 14%)',
    activeForeground: '#ffffff',
    accent,
  },
})

const softTheme = (
  id: string,
  label: string,
  background: string,
  border: string,
  foreground: string,
  muted: string,
  activeBackground: string,
  accent: string,
): ShellTheme => ({
  id,
  label,
  swatch: background,
  tokens: {
    background,
    border,
    foreground,
    muted,
    hover: 'rgb(255 255 255 / 48%)',
    activeBackground,
    activeForeground: foreground,
    accent,
  },
})

const gradientTheme = (
  id: string,
  label: string,
  background: string,
  accent: string,
): ShellTheme => ({
  id,
  label,
  swatch: background,
  tokens: {
    background,
    border: 'rgb(255 255 255 / 18%)',
    foreground: '#ffffff',
    muted: 'rgb(255 255 255 / 76%)',
    hover: 'rgb(255 255 255 / 10%)',
    activeBackground: 'rgb(255 255 255 / 18%)',
    activeForeground: '#ffffff',
    accent,
  },
})

const coloredThemes: ShellTheme[] = [
  darkTheme('graphite', 'Graphite', '#343a40', '#aab2bd'),
  darkTheme('slate', 'Slate', '#475569', '#cbd5e1'),
  darkTheme('midnight', 'Midnight', '#17213a', '#7aa2ff'),
  darkTheme('navy', 'Navy', '#12345b', '#7dd3fc'),
  darkTheme('cobalt', 'Cobalt', '#3157b7', '#9bb8ff'),
  darkTheme('sky', 'Sky', '#0369a1', '#7dd3fc'),
  darkTheme('ocean', 'Ocean', '#087ea4', '#67d4ff'),
  darkTheme('teal', 'Teal', '#0f766e', '#5eead4'),
  darkTheme('emerald', 'Emerald', '#17795b', '#73e2bd'),
  darkTheme('forest', 'Forest', '#276749', '#86efac'),
  darkTheme('violet', 'Violet', '#65459b', '#c7a8ff'),
  darkTheme('plum', 'Plum', '#702963', '#f0abfc'),
  darkTheme('rose', 'Rose', '#a92f55', '#ff9fbd'),
  darkTheme('coral', 'Coral', '#b94f45', '#fecaca'),
  {
    id: 'amber',
    label: 'Amber',
    swatch: '#f5ba3d',
    tokens: {
      background: '#f5ba3d',
      border: 'rgb(63 50 18 / 18%)',
      foreground: '#3f3212',
      muted: '#66541f',
      hover: 'rgb(255 255 255 / 22%)',
      activeBackground: 'rgb(255 255 255 / 34%)',
      activeForeground: '#2f250c',
      accent: '#704f00',
    },
  },
  softTheme('ice', 'Ice', '#e0f2fe', '#bae6fd', '#0c4a6e', '#0369a1', '#bae6fd', '#0284c7'),
  softTheme('mint', 'Mint', '#d1fae5', '#a7f3d0', '#064e3b', '#047857', '#a7f3d0', '#059669'),
  softTheme('sand', 'Sand', '#fef3c7', '#fde68a', '#713f12', '#92400e', '#fde68a', '#d97706'),
  softTheme('blush', 'Blush', '#ffe4e6', '#fecdd3', '#881337', '#be123c', '#fecdd3', '#e11d48'),
  softTheme(
    'lavender',
    'Lavender',
    '#ede9fe',
    '#ddd6fe',
    '#4c1d95',
    '#6d28d9',
    '#ddd6fe',
    '#7c3aed',
  ),
  gradientTheme(
    'twilight',
    'Twilight',
    'linear-gradient(135deg, #334155 0%, #6d28d9 100%)',
    '#c4b5fd',
  ),
  gradientTheme('aurora', 'Aurora', 'linear-gradient(135deg, #0f766e 0%, #2563eb 100%)', '#67e8f9'),
  gradientTheme('sunset', 'Sunset', 'linear-gradient(135deg, #c2410c 0%, #be185d 100%)', '#fed7aa'),
]

export const headerThemes: ShellTheme[] = [lightTheme, ...coloredThemes]
export const sidebarThemes: ShellTheme[] = [lightTheme, ...coloredThemes]

export function findShellTheme(region: ShellThemeRegion, id: string): ShellTheme {
  const themes = region === 'header' ? headerThemes : sidebarThemes

  return themes.find((theme) => theme.id === id) ?? lightTheme
}
