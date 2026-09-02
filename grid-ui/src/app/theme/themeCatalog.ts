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

export type ApplicationThemeTokens = {
  canvas: string
  surface: string
  surfaceMuted: string
  border: string
  foreground: string
  muted: string
  accent50: string
  accent100: string
  accent500: string
  accent600: string
  accent700: string
  focus: string
}

export type ApplicationTheme = {
  id: string
  label: string
  description: string
  headerTheme: string
  sidebarTheme: string
  swatches: [string, string, string]
  tokens: ApplicationThemeTokens
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

const applicationTheme = (
  id: string,
  label: string,
  description: string,
  headerTheme: string,
  sidebarTheme: string,
  swatches: [string, string, string],
  tokens: ApplicationThemeTokens,
): ApplicationTheme => ({
  id,
  label,
  description,
  headerTheme,
  sidebarTheme,
  swatches,
  tokens,
})

/**
 * Curated, coordinated application themes. Shell themes intentionally remain a
 * larger catalog because they are advanced overrides; these presets are the
 * safe one-click choices that keep navigation, content, and focus states legible.
 */
export const applicationThemes: ApplicationTheme[] = [
  applicationTheme(
    'cloud',
    'Cloud',
    'Bright and familiar',
    'light',
    'light',
    ['#ffffff', '#f1f4f6', '#3f6ad8'],
    {
      canvas: '#f1f4f6',
      surface: '#ffffff',
      surfaceMuted: '#f8fafc',
      border: '#dce2ea',
      foreground: '#343a40',
      muted: '#5f6f85',
      accent50: '#eef2ff',
      accent100: '#d9e1f7',
      accent500: '#3f6ad8',
      accent600: '#3255ad',
      accent700: '#1a367e',
      focus: 'rgb(63 106 216 / 24%)',
    },
  ),
  applicationTheme(
    'graphite',
    'Graphite',
    'Neutral and focused',
    'graphite',
    'graphite',
    ['#343a40', '#eef0f2', '#64748b'],
    {
      canvas: '#eef0f2',
      surface: '#ffffff',
      surfaceMuted: '#f6f7f8',
      border: '#d5d9de',
      foreground: '#2f3439',
      muted: '#66707a',
      accent50: '#f1f5f9',
      accent100: '#e2e8f0',
      accent500: '#64748b',
      accent600: '#475569',
      accent700: '#334155',
      focus: 'rgb(71 85 105 / 24%)',
    },
  ),
  applicationTheme(
    'midnight',
    'Midnight',
    'Deep navy workspace',
    'midnight',
    'midnight',
    ['#17213a', '#eef2f8', '#5579d7'],
    {
      canvas: '#eef2f8',
      surface: '#ffffff',
      surfaceMuted: '#f5f7fb',
      border: '#d8e0ec',
      foreground: '#26334b',
      muted: '#64748b',
      accent50: '#edf2ff',
      accent100: '#dbe6ff',
      accent500: '#5579d7',
      accent600: '#3f62bd',
      accent700: '#2c478e',
      focus: 'rgb(85 121 215 / 25%)',
    },
  ),
  applicationTheme(
    'cobalt',
    'Cobalt',
    'Confident blue contrast',
    'cobalt',
    'navy',
    ['#3157b7', '#edf3ff', '#3f6ad8'],
    {
      canvas: '#edf3fb',
      surface: '#ffffff',
      surfaceMuted: '#f5f8ff',
      border: '#d4dff0',
      foreground: '#263653',
      muted: '#61708a',
      accent50: '#edf3ff',
      accent100: '#dbe7ff',
      accent500: '#3f6ad8',
      accent600: '#3157b7',
      accent700: '#24418a',
      focus: 'rgb(63 106 216 / 25%)',
    },
  ),
  applicationTheme(
    'ocean',
    'Ocean',
    'Clear and energetic',
    'ocean',
    'navy',
    ['#087ea4', '#ecf8fc', '#0891b2'],
    {
      canvas: '#edf7fa',
      surface: '#ffffff',
      surfaceMuted: '#f3fafc',
      border: '#cfe3e9',
      foreground: '#24414b',
      muted: '#5c7680',
      accent50: '#ecfeff',
      accent100: '#cffafe',
      accent500: '#0891b2',
      accent600: '#0e7490',
      accent700: '#155e75',
      focus: 'rgb(8 145 178 / 24%)',
    },
  ),
  applicationTheme(
    'teal',
    'Teal',
    'Calm operational clarity',
    'teal',
    'teal',
    ['#0f766e', '#effaf8', '#0d9488'],
    {
      canvas: '#eef8f6',
      surface: '#ffffff',
      surfaceMuted: '#f3faf8',
      border: '#cfe4df',
      foreground: '#25433f',
      muted: '#607a75',
      accent50: '#f0fdfa',
      accent100: '#ccfbf1',
      accent500: '#0d9488',
      accent600: '#0f766e',
      accent700: '#115e59',
      focus: 'rgb(13 148 136 / 24%)',
    },
  ),
  applicationTheme(
    'emerald',
    'Emerald',
    'Fresh service workspace',
    'emerald',
    'forest',
    ['#17795b', '#eef8f3', '#15966b'],
    {
      canvas: '#eef7f2',
      surface: '#ffffff',
      surfaceMuted: '#f3faf6',
      border: '#d0e5da',
      foreground: '#29463a',
      muted: '#62796f',
      accent50: '#ecfdf5',
      accent100: '#d1fae5',
      accent500: '#15966b',
      accent600: '#087a55',
      accent700: '#066044',
      focus: 'rgb(21 150 107 / 24%)',
    },
  ),
  applicationTheme(
    'violet',
    'Violet',
    'Creative but composed',
    'violet',
    'midnight',
    ['#65459b', '#f4f0fa', '#7654b5'],
    {
      canvas: '#f3f0f8',
      surface: '#ffffff',
      surfaceMuted: '#f8f5fb',
      border: '#dfd7e9',
      foreground: '#3f3650',
      muted: '#746980',
      accent50: '#f5f3ff',
      accent100: '#ede9fe',
      accent500: '#7654b5',
      accent600: '#65459b',
      accent700: '#52377e',
      focus: 'rgb(118 84 181 / 24%)',
    },
  ),
  applicationTheme(
    'rose',
    'Rose',
    'Warm and distinctive',
    'rose',
    'graphite',
    ['#a92f55', '#faf0f3', '#c03964'],
    {
      canvas: '#f8f0f2',
      surface: '#ffffff',
      surfaceMuted: '#fcf6f8',
      border: '#ead7dd',
      foreground: '#4e343d',
      muted: '#806973',
      accent50: '#fff1f2',
      accent100: '#ffe4e6',
      accent500: '#c03964',
      accent600: '#a92f55',
      accent700: '#872542',
      focus: 'rgb(192 57 100 / 24%)',
    },
  ),
  applicationTheme(
    'amber',
    'Amber',
    'Bright and welcoming',
    'amber',
    'graphite',
    ['#f5ba3d', '#faf5e9', '#c98308'],
    {
      canvas: '#f7f3e9',
      surface: '#ffffff',
      surfaceMuted: '#fcf9f1',
      border: '#e7deca',
      foreground: '#4a4030',
      muted: '#7b705e',
      accent50: '#fffbeb',
      accent100: '#fef3c7',
      accent500: '#d8910c',
      accent600: '#b87308',
      accent700: '#8f5707',
      focus: 'rgb(216 145 12 / 25%)',
    },
  ),
  applicationTheme(
    'aurora',
    'Aurora',
    'Teal-to-blue energy',
    'aurora',
    'midnight',
    ['#0f766e', '#eff6ff', '#2563eb'],
    {
      canvas: '#eef5f8',
      surface: '#ffffff',
      surfaceMuted: '#f3f8fb',
      border: '#d3e1e8',
      foreground: '#263e4a',
      muted: '#617682',
      accent50: '#eff6ff',
      accent100: '#dbeafe',
      accent500: '#2563eb',
      accent600: '#1d4ed8',
      accent700: '#1e40af',
      focus: 'rgb(37 99 235 / 24%)',
    },
  ),
  applicationTheme(
    'sunset',
    'Sunset',
    'Warm gradient character',
    'sunset',
    'plum',
    ['#c2410c', '#fff3ed', '#be185d'],
    {
      canvas: '#f9f1ef',
      surface: '#ffffff',
      surfaceMuted: '#fdf7f5',
      border: '#eadad5',
      foreground: '#503b36',
      muted: '#806d67',
      accent50: '#fff1f2',
      accent100: '#ffe4e6',
      accent500: '#be185d',
      accent600: '#9f1239',
      accent700: '#881337',
      focus: 'rgb(190 24 93 / 24%)',
    },
  ),
]

export function findShellTheme(region: ShellThemeRegion, id: string): ShellTheme {
  const themes = region === 'header' ? headerThemes : sidebarThemes

  return themes.find((theme) => theme.id === id) ?? lightTheme
}

export function findApplicationTheme(id: string): ApplicationTheme {
  return applicationThemes.find((theme) => theme.id === id) ?? applicationThemes[0]!
}
