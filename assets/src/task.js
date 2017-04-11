function nextRec(value) {
  return { value, done: false }
}

function doneRec(value) {
  return { value, done: true }
}

function generatorStep(n, d, last) {
  let { next } = last
  let { done, value } = next(last.value)
  return done ? value.map(d) : value.map(x => n({ value: x, next: next }))
}

const noop = () => {}
const call = f => f()

function forkTask(error, success, computation) {
  let open = true
  let canceller = computation(err => {
    if (open) {
      open = false
      error(err)
    }
  }, v => {
    if (open) {
      open = false
      success(v)
    }
  })
  return () => {
    canceller()
    canceller = noop
  }
}

/**
 * Creates an asynchronous effect from a function that accepts error and
 * success callbacks, and returns a canceler for the computation.
 *
 */
function Task(computation) {
  if (!(this instanceof Task)) {
    return new Task(computation)
  }
  this._computation = computation
}

/**
 * Map successfull value of Task to a function
 *
 */
Task.prototype.map = function (func) {
  return new Task((error, success) => {
    return this.fork(error, v => success(func(v)))
  })
}

Task.of = function (v) {
  return new Task((error, success) => {
    success(v)
    return noop
  })
}

Task.rejected = function (err) {
  return new Task((error, success) => {
    error(err)
    return noop
  })
}

Task.prototype.ap = function (other) {
  return other.chain(f => this.map(f))
}

Task.prototype.chain = function (func) {
  return new Task((error, success) => {
    let canceller = noop
    let selfCanceller = this.fork(error, v => {
      const task = func(v)
      canceller = task.fork(error, success)
    })
    return canceller ? canceller : (canceller = selfCancel, () => canceller())
  })
}

Task.chainRec = function (func, initial) {
  return new Task((error, success) => {
    return (function step(acc) {
      let status
      let elem = nextRec(acc)
      let canceller = noop
      function onSuccess(v) {
        if (status === 0) {
          status = 1
          elem = v
        } else {
          let handler = v.done ? success : step
          handler(v.value)
        }
      }
      while (!elem.done) {
        status = 0
        canceller = func(nextRec, doneRec, elem.value).fork(error, onSuccess)
        if (status === 1) {
          if (elem.done) {
            success(elem.value)
          } else {
            continue
          }
        } else {
          status = 2
          return canceller
        }
      }
      return canceller
    })(initial)
  })
}

Task.do = function (func) {
  return new Task((error, success) => {
    const gen = func()
    const next = x => gen.next(x)
    const task = Task.chainRec(generatorStep, { value: undefined, next: next })
    return task.fork(error, success)
  })
}

Task.zero = function () {
  return Task.rejected('zero was used')
}

Task.prototype.alt = function (other) {
  return new Task((error, success) => {
    let canceller2 = noop
    let canceller1 = this.fork(_ => {
      canceller2 = other.fork(error, success)
    }, success)
    return canceller2 ? canceller2 : (canceller2 = canceller1, () => canceller1())
  })
}

Task.prototype.sequential = function () {
  return new Task((error, success) => this.fork(error, success))
}

Task.prototype.parallel = function () {
  return new TaskPar(this._computation)
}

Task.prototype.fork = function (error, success) {
  return forkTask(error, success, this._computation)
}

function TaskPar(computation) {
  if (!(this instanceof TaskPar)) {
    return new TaskPar(computation)
  }
  this._computation = computation
}

TaskPar.prototype.map = function (func) {
  return new TaskPar((error, success) => {
    return this.fork(error, v => success(func(v)))
  })
}

TaskPar.of = function (v) {
  return new TaskPar((error, success) => {
    success(v)
    return noop
  })
}

TaskPar.prototype.ap = function (other) {
  return new TaskPar((error, success) => {
    let value
    let func
    let thisOk = 0
    let otherOk = 0
    let ko = 0
    const guardReject = x => ko || (ko = 1, error(x))
    const canceller = this.fork(guardReject, v => {
      if (!otherOk) return void (thisOk = 1, value = v)
      return success(func(v))
    })
    const canceller1 = other.fork(guardReject, f => {
      if (!thisOk) return void (otherOk = 1, func = f)
      return success(f(value))
    })
    return () => {
      canceller()
      canceller1()
    }
  })
}

TaskPar.zero = function () {
  return new TaskPar(() => noop)
}

TaskPar.prototype.alt = function (other) {
  return new TaskPar((error, success) => {
    let settled = false
    let failed = 0
    let va
    const guardReject = v => {
      if (failed === 1) {
        error(va)
      } else {
        va = v
        failed += 1
      }
    }
    const guardResolve = v => {
      if (settled) return
      settled = true
      success(v)
    }
    const cancellers = [this, other].map(x => x.fork(guardReject, guardResolve))
    return () => {
      cancellers.forEach(call)
    }
  })
}

TaskPar.prototype.sequential = function () {
  return new Task(this._computation)
}

TaskPar.prototype.parallel = function () {
  return new TaskPar((error, success) => this.fork(error, success))
}

TaskPar.prototype.fork = function (error, success) {
  return forkTask(error, success, this._computation)
}

function patchFantasyLandMethod(constructor) {
  // Functor
  const fantasyM = [
    'map', 'ap', 'chain', 'alt'
  ]
  const fantasyS = [
    'of', 'chainRec', 'zero'
  ]
  fantasyM.forEach(method => {
    if (typeof constructor.prototype[method] === 'function') {
      constructor.prototype['fantasy-land/' + method] = constructor.prototype[method]
    }
  })
  // static method
  fantasyS.forEach(method => {
    // applicative
    if (typeof constructor[method] === 'function') {
      constructor['fantasy-land/' + method] = constructor[method]
    }
  })
}

patchFantasyLandMethod(Task)
patchFantasyLandMethod(TaskPar)

function voidF() {}

/**
 * Forks the specified asynchronous computation so subsequent computations
 * will not block on the result of the computation. return canceller for the given
 * forkable (Task or TaskPar).
 *
 * @param Task|TaskPar forkable
 * @return Task
 */
function forkedTask(forkable) {
  return new Task((_, success) => {
    let canceller = forkable.fork(voidF, voidF)
    success(canceller)
    return voidF
  })
}

/**
 * Runs the asynchronous computation off the current execution context.
 */
function later(milis, forkable) {
  return new Task((error, success) => {
    let set = setTimeout
    let clear = clearTimeout
    let canceler = undefined
    if (milis <= 0 && typeof setImmediate === 'function') {
      set = setImmediate
      clear = clearImmediate
    }
    const id = set(() => {
      canceler = forkable.fork(error, success)
    }, milis)
    // canceller
    return () => {
      if (typeof canceller !== 'undefined') {
        canceler()
      } else {
        clear(id)
      }
    }
  })
}

function attempt(f, g) {
  return forkable => {
    return new Task((_, success) => {
      return forkable.fork(err => success(f(err)), v => success(g(v)))
    })
  }
}

module.exports = {
  Task,
  TaskPar,
  forkedTask,
  later,
  attempt
}