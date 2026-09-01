/**
 * Browser-side diagnostic collection for the ChaosDesk support form.
 *
 * Two jobs: keep a rolling buffer of console errors so a bug report arrives
 * with the stack trace already attached, and capture a screenshot when the
 * user explicitly asks for one.
 *
 * Import once in your bundle:
 *
 *     import 'chaosdesk';
 */

const MAX_CONSOLE_ENTRIES = 50
const MAX_MESSAGE_LENGTH = 2000

const consoleBuffer = []

function record(level, parts) {
  const message = parts
    .map((part) => {
      if (part instanceof Error) {
        return `${part.name}: ${part.message}\n${part.stack ?? ''}`.trim()
      }

      if (typeof part === 'object' && part !== null) {
        try {
          return JSON.stringify(part)
        } catch {
          return String(part)
        }
      }

      return String(part)
    })
    .join(' ')
    .slice(0, MAX_MESSAGE_LENGTH)

  if (message === '') {
    return
  }

  consoleBuffer.push({ level, message, at: new Date().toISOString() })

  if (consoleBuffer.length > MAX_CONSOLE_ENTRIES) {
    consoleBuffer.shift()
  }
}

// Wrap the console without swallowing it: the original still runs.
for (const level of ['error', 'warn']) {
  const original = console[level]

  console[level] = function (...parts) {
    record(level === 'warn' ? 'warning' : 'error', parts)
    original.apply(console, parts)
  }
}

window.addEventListener('error', (event) => {
  record('error', [event.error ?? event.message])
})

window.addEventListener('unhandledrejection', (event) => {
  record('error', [event.reason])
})

/**
 * The page context that travels with a ticket.
 */
function pageContext() {
  return {
    source: 'web',
    page: {
      url: window.location.href,
      referrer: document.referrer || undefined,
      viewport: `${window.innerWidth}x${window.innerHeight}`,
      locale: navigator.language,
    },
    device: {
      platform: 'web',
      locale: navigator.language,
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    },
    console: consoleBuffer.slice(),
  }
}

/**
 * Capture the surface the user picks, as a PNG data URL.
 *
 * The browser always shows its own picker first, so nothing is captured
 * without an explicit choice. The stream is stopped immediately afterwards.
 */
async function captureScreen() {
  if (!navigator.mediaDevices?.getDisplayMedia) {
    throw new Error('Screen capture is not supported in this browser.')
  }

  const stream = await navigator.mediaDevices.getDisplayMedia({
    video: { displaySurface: 'browser' },
    audio: false,
    preferCurrentTab: true,
  })

  try {
    const track = stream.getVideoTracks()[0]

    // Give the compositor a frame to produce before grabbing it.
    await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)))

    const bitmap = await new ImageCapture(track).grabFrame()

    const canvas = document.createElement('canvas')
    canvas.width = bitmap.width
    canvas.height = bitmap.height
    canvas.getContext('2d').drawImage(bitmap, 0, 0)

    return canvas.toDataURL('image/png')
  } finally {
    stream.getTracks().forEach((track) => track.stop())
  }
}

/**
 * Alpine component backing the support form.
 */
function chaosdeskSupport() {
  return {
    capturing: false,
    captureError: null,

    init() {
      // Refresh the context on every submit so it reflects the moment of sending.
      this.$wire.set('clientContext', pageContext(), false)

      this.$el.addEventListener('submit', () => {
        this.$wire.set('clientContext', pageContext(), false)
      })
    },

    async captureScreenshot() {
      this.capturing = true
      this.captureError = null

      try {
        this.$wire.set('screenshot', await captureScreen(), false)
      } catch (error) {
        // A cancelled picker is a normal outcome, not a failure worth shouting about.
        if (error?.name !== 'NotAllowedError' && error?.name !== 'AbortError') {
          this.captureError = error?.message ?? 'Could not capture the screen.'
        }
      } finally {
        this.capturing = false
      }
    },
  }
}

window.chaosdeskSupport = chaosdeskSupport
window.chaosdeskContext = pageContext

export { captureScreen, chaosdeskSupport, pageContext }
