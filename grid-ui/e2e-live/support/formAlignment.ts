import { expect, type Locator } from '@playwright/test'

export async function expectControlRowAligned(...controls: Locator[]): Promise<void> {
  const boxes = await Promise.all(controls.map((control) => control.boundingBox()))

  for (const box of boxes) expect(box).not.toBeNull()

  const tops = boxes.map((box) => box!.y)
  expect(Math.max(...tops) - Math.min(...tops)).toBeLessThanOrEqual(1)
}
