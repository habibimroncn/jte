/**
 * This used to enable realtime editing using Pusher.
 */
// Globals vars: jQuery, tinyMCE, Pusher
var async = require('./task'),
    diffmatch = require('./diffmatch'),
    syncendContent = '',
    syncendTitle = '',
    channel = undefined,
    post_id = 0,
    secret = '',
    users = [],
    $ = jQuery

// credential for Pusher
const APP_KEY = 'a93b916c848e1743e492'

const pusher = new Pusher(APP_KEY, {
  encrypted: true,
  authEndpoint: '/jte-pusher/v1/auth'
})
const prop = k => obj => obj[k]
const noop = () => {}

function jsonpCallback(s) {
  secret = s
}

function handleContentEdited(data) {
  // store the cursor, before we replace the content
  var editor = tinyMCE.get('content'),
      selection = editor.selection
  if (JTEC.current_uid == data.user_id) return
  var content = editor.getContent({format: 'html'}),
      changesets = data.changesets,
      dmp = new diffmatch.diff_match_patch(),
      patches = dmp.patch_fromText(changesets),
      results = dmp.patch_apply(patches, content),
      bookmark = selection.getBookmark(2, true);

  editor.setContent(results[0], {format: "html"})
  syncendContent = results[0]
  selection.moveToBookmark(bookmark)
}

function handleTitleEdited(data) {
  if (JTEC.current_uid == data.user_id) return

  const title = document.getElementById('title')
  var start, end
  if (title === document.activeElement) {
    start = title.selectionStart
    end = title.selectionEnd
  }
  var currentTitle = document.getElementById('title').value,
      changesets = data.changesets,
      dmp = new diffmatch.diff_match_patch(),
      patches = dmp.patch_fromText(changesets),
      results = dmp.patch_apply(patches, currentTitle)
  title.value = results[0]
  syncendTitle = results[0]
  // maintain cursor
  if (title === document.activeElement) {
    title.setSelectionRange(start, end)
  }
}

function debounce(func, wait, immediate) {
  var timeout;
  return function() {
    var context = this, args = arguments;
    var later = function() {
      timeout = null
      if (!immediate) func.apply(context, args)
    }
    var callNow = immediate && !timeout
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
    if (callNow) func.apply(context, args)
  }
}

const askContentToServer =
  async.Task((reject, resolve) => {
    $.ajax({
      url: ajaxurl,
      method: 'GET',
      success: (res) => resolve(res),
      error: (_, e) => reject(e),
      data: {
        post_id: document.getElementById('post_ID').value,
        action: 'jte_ajax_ask_current_post_info'
      }
    })
  })

function sendDiffPatchContent(patch) {
  return async.Task((reject, resolve) => {
    $.ajax({
      url: ajaxurl,
      method: 'POST',
      success: resolve,
      error: (_, e) => reject(e),
      data: {
        action: 'jte_ajax_sync_post_content',
        post_id: document.getElementById('post_ID').value,
        changesets: patch
      }
    })
  })
}

function broadcastDiffPatchContent(patch) {
  return async.Task((reject, resolve) => {
    var post_id = document.getElementById('post_ID').value
    var data = {
      user_id: JTEC.current_uid,
      changesets: patch,
      post_id: post_id,
      timestamp: Date.now(),
    }
    channel.trigger('client-content-updated', data)
    resolve(data)
  })
}

function broadcastDiffPatchTitle(patch) {
  return async.Task((reject, resolve) => {
    var post_id = document.getElementById('post_ID').value
    var data = {
      user_id: JTEC.current_uid,
      changesets: patch,
      post_id: post_id,
      timestamp: Date.now(),
    }
    channel.trigger('client-title-updated', data)
    console.log(channel.trigger('client-content-updated', data))
    resolve(data)
  })
}

function sendDiffPatchTitle(patch) {
  return async.Task((reject, resolve) => {
    $.ajax({
      url: ajaxurl,
      method: 'POST',
      success: resolve,
      error: (_, e) => reject(e),
      data: {
        action: 'jte_ajax_sync_post_title',
        post_id: document.getElementById('post_ID').value,
        changesets: patch
      }
    })
  })
}

const pair = a => b => [a, b]

function diffContentWithServer(content) {
  return async.Task((_, resolve) => {
    const dmp = new diffmatch.diff_match_patch()
    const diff = dmp.diff_main(syncendContent, content, true)
    if (diff.length > 2) {
      dmp.diff_cleanupSemantic(diff);
    }
    const patch_list = dmp.patch_make(syncendContent, content, diff);
    resolve(dmp.patch_toText(patch_list))
  }).chain(r => {
    return async.forkedTask(broadcastDiffPatchContent(r)).chain(() => async.Task.of(r));
  })
  // return askContentToServer.map(res => {
  //   const dmp = new diffmatch.diff_match_patch()
  //   const diff = dmp.diff_main(res.content, content, true)
  //   if (diff.length > 2) {
  //     dmp.diff_cleanupSemantic(diff);
  //   }
  //   const patch_list = dmp.patch_make(res.content, content, diff);
  //   return dmp.patch_toText(patch_list)
  // }).chain(sendDiffPatchContent)
}

function diffTitleWithServer(title) {
  return async.Task((_, resolve) => {
    const dmp = new diffmatch.diff_match_patch()
    const diff = dmp.diff_main(syncendTitle, title, true)
    if (diff.length > 2) {
      dmp.diff_cleanupSemantic(diff);
    }
    const patch_list = dmp.patch_make(syncendTitle, title, diff);
    resolve(dmp.patch_toText(patch_list))
  }).chain(r => {
    return async.forkedTask(broadcastDiffPatchTitle(r)).chain(() => async.Task.of(r));
  })
  // return askContentToServer.map(res => {
  //   const dmp = new diffmatch.diff_match_patch()
  //   const diff = dmp.diff_main(res.title, title, true)
  //   if (diff.length > 2) {
  //     dmp.diff_cleanupSemantic(diff);
  //   }
  //   const patch_list = dmp.patch_make(res.title, title, diff);
  //   return dmp.patch_toText(patch_list)
  // }).chain(sendDiffPatchTitle)
}

const sendSyncPost = debounce(function (content) {
  return diffContentWithServer(content).fork(noop, noop)
}, 700)
const sendSyncTitle = debounce(function (title) {
  return diffTitleWithServer(title).fork(noop, noop)
}, 700)

function main() {
  post_id = document.getElementById('post_ID').value
  channel = pusher.subscribe('private-jte-post-editing-' + post_id)
  // channel.bind('pusher:subscription_succeeded', (data) => {
  //   var us = Object.keys(data.members).map(k => data.members[k])
  //   users = us
  // });
  // channel.bind('pusher:member_added', user => {
  //  users.push(user)
  // })
  // channel.bind('pusher:member_removed', user => {
  //   users = user.filter(u => u != uid)
  // })
  channel.bind('client-content-updated', handleContentEdited);
  channel.bind('client-title-updated', handleTitleEdited);
  document.getElementById('title').addEventListener('input', (e) => {
    sendSyncTitle(e.currentTarget.value)
  })

  // on first load update title
  askContentToServer.map(res => {
    // title
    const t = res.title
    document.getElementById('title').value = t

    // content
    var editor = tinyMCE.get('content')
    editor.setContent(res.content, {format: "html"})

    syncendTitle = res.title
    syncendContent = res.content

    return title
  }).fork(noop, noop)
}

requestAnimationFrame(main)

tinyMCE.on('SetupEditor', function (editor) {
  if (editor.id === 'content') {
    editor.on('keyup', function (e) {
      sendSyncPost(this.getContent({format: "html"}))
    })

    editor.on('change', function (e) {
      sendSyncPost(this.getContent({format: "html"}))
    })

    editor.on('paste', function (e) {
      var content = ((e.originalEvent || e).clipboardData || window.clipboardData).getData('Text')
      var s = editor.execCommand('mceInsertContent', false, content)
    })
  }
})