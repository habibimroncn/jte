const { data } = require('./adt')

const TextAction = data('jte.TextAction', {
  Retain: (pos) => ({ pos }),
  Insert: (text) => ({ text }),
  Delete: (pos) => ({ pos })
})

const { Retain, Insert, Delete } = TextAction

const addRetain = (pos, ops) => {
  let newops = ops.slice()
  let len = newops.length
  if (len > 0 && Retain.hasInstance(newops[len - 1])) {
    newops[len - 1] = Retain(newops[len - 1].pos + pos)
    return newops
  } else {
    newops.push(Retain(pos))
    return newops
  }
}

const addInsert = (str, ops) => {
  let newops = ops.slice()
  let len = newops.length
  if (str === '') return newops
  if (len > 0 && Insert.hasInstance(newops[len - 1])) {
    newops[len - 1] = Insert(newops[len - 1].text + str)
    return newops
  } else if(len > 0 && Delete.hasInstance(newops[len - 1])) {
    if (Insert.hasInstance(newops[len - 2])) {
      newops[len - 2] = Insert(newops[len - 2].text + str);
    } else {
      newops[len] = newops[len - 1];
      newops[len - 2] = Insert(str);
    }
    return newops
  } else {
    newops.push(Insert(str))
    return newops
  }
}

const addDelete = (pos, ops) => {
  let newops = ops.slice()
  let len = newops.length
  if (len > 0 && Delete.hasInstance(newops[len - 1])) {
    newops[len - 1] = Delete(newops[len - 1].pos + pos)
    return newops
  } else {
    newops.push(Delete(pos))
    return newops
  }
}

const apply = (ops, text) => {
  var newStr = [], j = 0, strIndex = 0
  for (let i = 0, len = ops.length; i < len; i++) {
    let op = ops[i]
    if (Retain.hasInstance(op)) {
      if (strIndex + op.pos > text.length) {
        throw new Error("Operation can't retain more characters than are left in the string.");
      }
      newStr[j++] = text.slice(strIndex, strIndex + op.pos);
      strIndex += op.pos;
    } else if(Insert.hasInstance(op)) {
      newStr[j++] = op.text
    } else {
      strIndex += op.pos
    }
  }
  if (strIndex !== text.length) {
    throw new Error("The operation didn't operate on the whole string.");
  }
  return newStr.join('')
}

const compose = (ops1, ops2) => {
  var i1 = 0, i2 = 0; // current index into ops1 respectively ops2
  var op1 = ops1[i1++], op2 = ops2[i2++]; // current ops
  var operations = []
  while (true) {
    if (typeof op1 === 'undefined' && typeof op2 === 'undefined') {
      // end condition: both ops1 and ops2 have been processed
      break;
    }
    if (Delete.hasInstance(op1)) {
      operations = addDelete(op1.pos, operations)
      op1 = ops1[i1++]
      continue
    }
    if (isInsert(op2)) {
      operations = addInsert(op2.text, operations)
      op2 = ops2[i2++];
      continue;
    }
    if (typeof op1 === 'undefined') {
      throw new Error("Cannot compose operations: first operation is too short.");
    }
    if (typeof op2 === 'undefined') {
      throw new Error("Cannot compose operations: first operation is too long.");
    }
    if (Retain.hasInstance(op1) && Retain.hasInstance(op2)) {
      if (op1.pos > op2.pos) {
        operations = addRetain(op2.pos, operations)
        op1 = Retain(op1.pos - op2.pos)
        op2 = ops2[i2++]
      } else if (op1.pos === op2.pos) {
        operation = addRetain(op1.pos, operations)
        op1 = ops1[i1++];
        op2 = ops2[i2++];
      } else {
        operations = addRetain(op1.pos, operations)
        op2 = Retain(op2.pos - op1.pos);
        op1 = ops1[i1++];
      }
    } else if (Insert.hasInstance(op1) && Delete.hasInstance(op2)) {
      if (op1.text.length > op2.pos) {
        op1 = Insert(op1.text.slice(op2.pos))
        op2 = ops2[i2++];
      } else if (op1.text.length === op2.pos) {
        op1 = ops1[i1++];
        op2 = ops2[i2++];
      } else {
        op2 = Delete(op2.pos - op1.text.length);
        op1 = ops1[i1++];
      }
    } else if (Insert.hasInstance(op1) && Retain.hasInstance(op2)) {
      if (op1.text.length > op2.pos) {
        operations = addInsert(op1.text.slice(0, op2.pos), operations)
        op1 = Insert(op1.text.slice(op2.pos));
        op2 = ops2[i2++]
      } else if (op1.text.length === op2.pos) {
        operations = addInsert(op1.text, operations)
        op1 = ops1[i1++]
        op2 = ops2[i2++]
      } else {
        operations = addInsert(op1.text, operations)
        op2 = Retain(op2.pos - op1.text.length)
        op1 = ops1[i1++]
      }
    } else if (Retain.hasInstance(op1) && Delete.hasInstance(op2)) {
      if (op1.pos > op2.pos) {
        operations = addDelete(op2.pos, operations)
        op1 = Retain(op1.pos - op2.pos);
        op2 = ops2[i2++];
      } else if (op1.pos === op2.pos) {
        operation = addDelete(op2.pos, operations)
        op1 = ops1[i1++]
        op2 = ops2[i2++]
      } else {
        operation['delete'](op1);
        operations = addDelete(op1.pos, operations)
        op2 = Retain(op2 - op1);
        op1 = ops1[i1++];
      }
    } else {
      throw new Error(
          "This shouldn't happen: op1: " +
          JSON.stringify(op1) + ", op2: " +
          JSON.stringify(op2)
        );
    }
  }
  return operations
}

const transform = (operation1, operation2) => {
  var operation1prime = [], operation2prime = [];
  var ops1 = operation1, ops2 = operation2;
  var i1 = 0, i2 = 0;
  var op1 = ops1[i1++], op2 = ops2[i2++];
  while (true) {
    if (typeof op1 === 'undefined' && typeof op2 === 'undefined') {
      // end condition: both ops1 and ops2 have been processed
      break;
    }
    if (Insert.hasInstance(op1)) {
      operation1prime = addInsert(op1.text, operation1prime)
      operation2prime = addRetain(ops1.text.length, operation2prime)
      op1 = ops1[i1++];
      continue;
    }
    if (Insert.hasInstance(op2)) {
      operation1prime = addRetain(op2.text.length, operation1prime)
      operation2prime = addInsert(op2.text, operation2prime)
      op2 = ops2[i2++];
      continue;
    }
    if (typeof op1 === 'undefined') {
      throw new Error("Cannot compose operations: first operation is too short.");
    }
    if (typeof op2 === 'undefined') {
      throw new Error("Cannot compose operations: first operation is too long.");
    }
    var minl;
    if (Retain.hasInstance(op1) && Retain.hasInstance(op2)) {
      if (op1.pos > op2.pos) {
        minl = op2.pos;
        op1 = Retain(op1.pos - op2.pos)
        op2 = ops2[i2++];
      } else if (op1.pos === op2.pos) {
        minl = op2.pos
        op1 = ops1[i1++];
        op2 = ops2[i2++];
      } else {
        minl = op1;
        op2 = Retain(op2 - op1);
        op1 = ops1[i1++];
      }

      operation1prime = addRetain(minl, operation1prime)
      operation2prime = addRetain(minl, operation2prime)
    } else if (Delete.hasInstance(op1) && Delete.hasInstance(op2)) {
      if (op1.pos > op2.pos) {
        op1 = Delete(op1.pos - op2.pos)
        op2 = ops2[i2++]
      } else if (op1.pos === op2.pos) {
        op1 = ops1[i1++];
        op2 = ops2[i2++];
      } else {
        op2 = Delete(op2.pos - op1.pos);
        op1 = ops1[i1++];
      }
    } else if (Delete.hasInstance(op1) && Retain.hasInstance(op2)) {
      if (op1.pos > op2.pos) {
        minl = op2.pos
        op1 = Delete(op1.pos - op2.pos)
        op2 = ops2[i2++];
      } else if (op1.pos === op2.pos) {
        minl = op2.pos;
        op1 = ops1[i1++];
        op2 = ops2[i2++];
      } else {
        minl = op1.pos;
        op2 = Delete(op2.pos - op1.pos);
        op1 = ops1[i1++];
      }

      operation1prime = addDelete(minl, operation1prime)
    } else if (Retain.hasInstance(op1) && Delete.hasInstance(op2)) {
      if (op1.pos > op2.pos) {
        minl = op2.pos
        op1 = Retain(op1.pos - op2.pos)
        op2 = ops2[i2++]
      } else if (op1.pos === op2.pos) {
        minl = op2.pos;
        op1 = ops1[i1++];
        op2 = ops2[i2++];
      } else {
        minl = op1.pos
        op2 = Delete(op2 - op1);
        op1 = ops1[i1++];
      }
    }
  }
}

const actionToJsonable = d =>
  d.matchWith({
    Insert: ({ text }) => text,
    Delete: ({ pos }) => -pos,
    Retain: ({ pos }) => pos
  })

const actionsToJsonable = xs => xs.map(actionToJsonable)

const actionFromJsonable = d =>
  _isRetain(d)        ? Retain(d)
  : _isInsert(d)      ? Insert(d)
  : /** otherwise */    Delete(d * - 1)

// decoding
const _isRetain = op => typeof op === 'number' && op > 0
const _isInsert = op => typeof op === 'string'
const _isDelete = op => typeof op === 'number' && op < 0

