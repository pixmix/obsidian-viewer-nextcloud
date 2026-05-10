export default function wikilinkPlugin(md) {
	md.inline.ruler.after('image', 'wikilink', (state, silent) => {
		const src = state.src
		const pos = state.pos

		if (src.charCodeAt(pos) !== 0x5B || src.charCodeAt(pos + 1) !== 0x5B) {
			return false
		}

		const end = src.indexOf(']]', pos + 2)
		if (end === -1) {
			return false
		}

		if (!silent) {
			const raw = src.slice(pos + 2, end)
			const pipeIdx = raw.indexOf('|')
			const target = pipeIdx >= 0 ? raw.slice(0, pipeIdx).trim() : raw.trim()
			const alias = pipeIdx >= 0 ? raw.slice(pipeIdx + 1).trim() : null

			const headingIdx = target.indexOf('#')
			const filePart = headingIdx >= 0 ? target.slice(0, headingIdx) : target
			const heading = headingIdx >= 0 ? target.slice(headingIdx + 1) : null

			const token = state.push('wikilink', '', 0)
			token.content = raw
			token.meta = { target, filePart, heading, alias }
		}

		state.pos = end + 2
		return true
	})

	md.renderer.rules.wikilink = (tokens, idx) => {
		const { filePart, heading, alias } = tokens[idx].meta
		const display = alias || filePart || heading || tokens[idx].content
		const dataTarget = filePart || ''
		const dataHeading = heading || ''
		return `<a class="obsidian-wikilink" data-target="${md.utils.escapeHtml(dataTarget)}" data-heading="${md.utils.escapeHtml(dataHeading)}" href="#">${md.utils.escapeHtml(display)}</a>`
	}
}
