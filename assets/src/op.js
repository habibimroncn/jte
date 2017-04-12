const TextOperation = require('ot/lib/text-operation')
const diffmatch = require('./diffmatch')

function toLength(op) {
  return TextOperation.isRetain(op)     ? op
  :      TextOperation.isDelete(op)     ? op
  :      /** otherwise */                 op.length
}

function diffText(t1, t2) {
  const dmp = new diffmatch.diff_match_patch()
  const diff = dmp.diff_main(t1, t2, true)
  if (diff.length > 2) {
    dmp.diff_cleanupSemantic(diff);
  }
  return diff
}

function operationFromDiff(diff) {
  var ops = new TextOperation()
  var offset = 0
  for (let i = 0, len = diff.length; i < len; i++) {
    switch (diff[i][0]) {
      case diffmatch.DIFF_INSERT:
        ops.insert(diff[i][1])
        offset += diff[i][1].length
        break
      case diffmatch.DIFF_DELETE:
        ops['delete'](diff[i][1])
        offset += diff[i][1].length
        break
      case diffmatch.DIFF_EQUAL:
        ops.retain(diff[i][1].length)
        offset += diff[i][1].length
        break
    }
  }
  return ops
}

module.exports = {
  operationFromDiff,
  diffText
}
