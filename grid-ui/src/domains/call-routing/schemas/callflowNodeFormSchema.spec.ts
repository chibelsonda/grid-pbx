import { describe, expect, it } from 'vitest'
import { createCallflowNodeFormSchema } from './callflowNodeFormSchema'

describe('callflow node form schema', () => {
  it('accepts only synchronized destinations and currently empty public branches', () => {
    const schema = createCallflowNodeFormSchema(
      ['54d9431a-f090-413b-a17e-88e02f0c0b44'],
      ['_', 'timeout'],
      true,
    )

    expect(
      schema.safeParse({
        branch: '_',
        destination_id: '54d9431a-f090-413b-a17e-88e02f0c0b44',
      }).success,
    ).toBe(true)
    expect(
      schema.safeParse({
        branch: '1',
        destination_id: '54d9431a-f090-413b-a17e-88e02f0c0b44',
      }).success,
    ).toBe(false)
    expect(
      schema.safeParse({
        branch: '_',
        destination_id: '8224c7ce-e17a-4ff5-abf6-54a502705a19',
      }).success,
    ).toBe(false)
  })

  it('does not require a branch when retargeting an existing node', () => {
    const schema = createCallflowNodeFormSchema(['54d9431a-f090-413b-a17e-88e02f0c0b44'], [], false)

    expect(
      schema.safeParse({
        branch: null,
        destination_id: '54d9431a-f090-413b-a17e-88e02f0c0b44',
      }).success,
    ).toBe(true)
  })
})
