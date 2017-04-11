/**
 * This used to enable realtime editing using Pusher.
 */
// Globals vars: jQuery, tinyMCE, Pusher
var async = require('./task'),
    diffmatch = require('./diffmatch')


const noop = () => {}

function diffPatchText(t1, t2) {
  const dmp = new diffmatch.diff_match_patch()
  const diff = dmp.diff_main(t1, t2, true)
  if (diff.length > 2) {
    dmp.diff_cleanupSemantic(diff);
  }
  const patch_list = dmp.patch_make(t1, t2, diff)
  return dmp.patch_toText(patch_list)
}

// Pusher
function whisperPusher(channel, name, data) {
  return async.Task((_, resolve) => {
    channel.trigger('client-' + name, data)
    resolve()
    return noop
  })
}

//
function presenceChannel(pusher, name) {
  return async.Task((_, resolve) => {
    resolve(pusher.subscribe('private-' + name))
    return noop
  })
}

function leaveChannel(channel) {
  return async.Task((_, resolve) => {
    channel.unsubscribe()
    resolve()
    return noop
  })
}

// Ref
function newRef(val) {
  return async.Task((_, resolve) => {
    resolve({ value: val })
    return noop
  })
}

function readRef(ref) {
  return async.Task((_, resolve) => {
    resolve(ref.value)
    return noop
  })
}

function modifyRef_(ref, f) {
  return async.Task((_, resolve) => {
    var t = f(ref.value)
    ref.value = t.state
    resolve(t.value)
    return noop
  })
}