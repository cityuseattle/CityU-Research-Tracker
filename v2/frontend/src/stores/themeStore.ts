import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface ThemeState {
  isDark: boolean;
  toggleDark: () => void;
  setDark: (value: boolean) => void;
  accentColor: string | null;
  setAccentColor: (color: string | null) => void;
}

export const useThemeStore = create<ThemeState>()(
  persist(
    (set) => ({
      isDark: false,
      toggleDark: () => set((s) => ({ isDark: !s.isDark })),
      setDark: (value: boolean) => set({ isDark: value }),
      accentColor: null,
      setAccentColor: (color: string | null) => set({ accentColor: color }),
    }),
    { name: 'rrp-theme' }
  )
);
