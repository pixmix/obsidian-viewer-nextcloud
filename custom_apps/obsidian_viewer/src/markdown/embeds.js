const IMAGE_EXTS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'bmp', 'ico']
const PDF_EXTS = ['pdf']

export default function embedPlugin(md) {
	md.inline.ruler.after('image', 'obsidian_embed', (state, silent) => {
		const src = state.src
		const pos = state.pos

		if (src.charCodeAt(pos) !== 0x21
			|| src.charCodeAt(pos + 1) !== 0x5B
			|| src.charCodeAt(pos + 2) !== 0x5B) {
			return false
		}

		const end = src.indexOf(']]', pos + 3)
		if (end === -1) {
			return false
		}

		if (!silent) {
			const raw = src.slice(pos + 3, end)
			const pipeIdx = raw.indexOf('|')
			const fullTarget = pipeIdx >= 0 ? raw.slice(0, pipeIdx).trim() : raw.trim()
			const params = pipeIdx >= 0 ? raw.slice(pipeIdx + 1).trim() : null

			const hashIdx = fullTarget.indexOf('#')
			const filePart = hashIdx >= 0 ? fullTarget.slice(0, hashIdx) : fullTarget
			const anchor = hashIdx >= 0 ? fullTarget.slice(hashIdx + 1) : null

			const dotIdx = filePart.lastIndexOf('.')
			const ext = dotIdx >= 0 ? filePart.slice(dotIdx + 1).toLowerCase() : null
			const isImage = ext !== null && IMAGE_EXTS.includes(ext)
			const isPdf = ext !== null && PDF_EXTS.includes(ext)
			const isNote = ext === null || ext === 'md' || ext === 'canvas'

			const token = state.push('obsidian_embed', '', 0)
			token.content = raw
			token.meta = { fullTarget, filePart, anchor, params, isImage, isPdf, isNote }
		}

		state.pos = end + 2
		return true
	})

	md.renderer.rules.obsidian_embed = (tokens, idx) => {
		const { fullTarget, filePart, anchor, params, isImage, isPdf, isNote } = tokens[idx].meta

		if (isImage) {
			let sizeAttrs = ''
			if (params) {
				if (params.includes('x')) {
					const [w, h] = params.split('x')
					sizeAttrs = ` width="${md.utils.escapeHtml(w)}" height="${md.utils.escapeHtml(h)}"`
				} else if (/^\d+$/.test(params)) {
					sizeAttrs = ` width="${params}"`
				}
			}
			return `<div class="obsidian-embed obsidian-embed--image"><img data-embed-target="${md.utils.escapeHtml(filePart)}" alt="${md.utils.escapeHtml(filePart)}"${sizeAttrs} class="obsidian-embed-image" /></div>`
		}

		if (isNote) {
			return `<div class="obsidian-embed obsidian-embed--note" data-embed-target="${md.utils.escapeHtml(fullTarget)}"><div class="obsidian-embed__header">${md.utils.escapeHtml(filePart || anchor)}</div><div class="obsidian-embed__content">Loading...</div></div>`
		}

		if (isPdf) {
			const escapedFile = md.utils.escapeHtml(filePart)
			return `<div class="obsidian-embed obsidian-embed--pdf" data-embed-file="${escapedFile}"><iframe class="obsidian-embed-pdf"></iframe><a class="obsidian-embed-pdf__link" href="#" target="_blank" rel="noopener noreferrer"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg> Open in Nextcloud</a></div>`
		}

		const displayName = params || filePart
		return `<a class="obsidian-embed obsidian-embed--file" data-embed-file="${md.utils.escapeHtml(filePart)}" href="#" target="_blank" rel="noopener noreferrer"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg> <span>${md.utils.escapeHtml(displayName)}</span></a>`
	}
}
