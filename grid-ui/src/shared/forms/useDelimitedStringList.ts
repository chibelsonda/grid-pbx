import { computed, type WritableComputedRef } from 'vue'

export function useDelimitedStringList(
  read: () => string[],
  write: (values: string[]) => void,
): WritableComputedRef<string> {
  return computed({
    get: () => read().join(', '),
    set: (value) => {
      const unique = new Set(
        value
          .split(/[\n,]/)
          .map((item) => item.trim())
          .filter(Boolean),
      )

      write([...unique])
    },
  })
}
