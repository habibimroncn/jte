const TYPE = '__realtimeadt_type__'
const TAG = '__realtimeadt_tag__'

// utility to access this static methods more shorter
const keys           = Object.keys
const symbols        = Object.getOwnPropertySymbols
const defineProperty = Object.defineProperty
const property       = Object.getOwnPropertyDescriptor

const mapObject = (obj, f) =>
  keys(obj).reduce((res, k) => {
    res[k] = f(k, obj[k])
    return res
  }, {})

const extend = (target, ...sources) => {
  sources.forEach(source => {
    keys(source).forEach(key => {
      if (key === 'prototype') {
        target[key] = source[key]
      } else {
        defineProperty(target, key, property(source, key))
      }
    })
    symbols(source).forEach(symbol => {
      defineProperty(target, symbol, property(source, symbol))
    })
  })
  return target
}

const rawCase = (adt, cases) =>
  variant => {
    const handler = cases[variant[TAG]]
    return typeof handler === 'function'    ? handler(variant)
    :      typeof cases['_'] === 'function' ? cases['_'](variant)
    :                                         reportMissingCase(keys(cases), adt)
  }

function reportMissingCase(handler, adt) {
  const variantsComma = adt.variants.join(', ')
  const handlerComma = handler.join(', ')
  throw new TypeError(
    `your patterns matching doesnt handle all possible value of ADT ${adt[TYPE]} ` +
    `it has variants ${variantsComma}. You only handle ${handlerComma}`
  )
}

function defineVariants(typeId, patterns, adt) {
  return mapObject(patterns, (name, constructor) => {
    function Variant() {}
    Variant.prototype = Object.create(adt)

    extend(Variant.prototype, {
      [TAG]: name,
      constructor: constructor,
      matchWith(pattern) {
        return rawCase(adt, pattern)(this)
      }
    })

    function makeInstance(...args) {
      let variant = new Variant()
      Object.assign(variant, constructor(...args))
      return variant
    }
    extend(makeInstance, {
      prototype: Variant.prototype,
      get tag() {
        return name
      },
      get type() {
        return typeId
      },
      get constructor() {
        return constructor
      },
      hasInstance(value) {
        return Boolean(value) && adt.hasInstance(value) && value[TAG] === name
      }
    })
    return makeInstance
  })
}

const ADT = {
  derive(...derivations) {
    derivations.forEach(derivation => {
      this.variants.forEach(variant => derivation(this[variant], this))
    })
    return this
  },
  matchWith(patterns) {
    return rawCase(this, patterns)
  }
}

function data(typeId, patterns) {
  const ADTNamespace = Object.create(ADT)
  const variants     = defineVariants(typeId, patterns, ADTNamespace)
  extend(ADTNamespace, variants, {
    [TYPE]: typeId,
    variants: keys(variants),
    hasInstance(value) {
      return Boolean(value) && value[TYPE] === this[TYPE]
    }
  })
  return ADTNamespace
}

module.exports = {
  data,
  ADT,
  TYPE,
  TAG
}