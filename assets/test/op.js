var assert = require('assert'),
  op = require('../src/op'),
  TextOperation = require('ot/lib/text-operation')


describe('Operation', () => {
  it('diff 2 text', () => {
    var d = op.diffText('Good morning', 'Bad morning')
    assert(true, Array.isArray(d))
  })
  it('create operations from diff', () => {
    var d   = op.diffText('Good morning', 'Bad morning'),
        ops = op.operationFromDiff(d)
    assert(true, d.length === ops.length)
  })
  it('can applied to str', () => {
    var d   = op.diffText('Good morning', 'Bad morning'),
        ops = op.operationFromDiff(d)
        s = ops.apply('Good morning')
    assert.equal(s, 'Bad morning')
  })
  it('test applied', () => {
    var op = new TextOperation(),
      doc = 'Lorem ipsum'
    op.delete(1).insert('l').retain(4).delete(4).retain(2).insert('s')
    assert.equal(op.apply(doc), 'loremums')
  })
})
