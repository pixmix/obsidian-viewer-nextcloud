export default function highlightPlugin(md) {
	md.inline.ruler.after('emphasis', 'obsidian_highlight', (state, silent) => {
		const src = state.src
		const pos = state.pos

		if (src.charCodeAt(pos) !== 0x3D || src.charCodeAt(pos + 1) !== 0x3D) {
			return false
		}

		const end = src.indexOf('==', pos + 2)
		if (end === -1) return false

		if (!silent) {
			const token = state.push('obsidian_highlight', '', 0)
			token.content = src.slice(pos + 2, end)
		}

		state.pos = end + 2
		return true
	})

	md.renderer.rules.obsidian_highlight = (tokens, idx) => {
		return `<mark class="obsidian-highlight">${md.utils.escapeHtml(tokens[idx].content)}</mark>`
	}
}
