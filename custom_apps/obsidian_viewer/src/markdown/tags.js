export default function tagPlugin(md) {
	md.inline.ruler.after('emphasis', 'obsidian_tag', (state, silent) => {
		const src = state.src
		const pos = state.pos

		if (src.charCodeAt(pos) !== 0x23) return false

		if (pos > 0) {
			const prev = src.charCodeAt(pos - 1)
			if (prev !== 0x20 && prev !== 0x09 && prev !== 0x0A && prev !== 0x0D) return false
		}

		const rest = src.slice(pos + 1)
		const match = rest.match(/^[a-zA-Z_][a-zA-Z0-9_/-]*/)
		if (!match) return false

		if (!silent) {
			const tag = match[0]
			const token = state.push('obsidian_tag', '', 0)
			token.content = tag
		}

		state.pos = pos + 1 + match[0].length
		return true
	})

	md.renderer.rules.obsidian_tag = (tokens, idx) => {
		const tag = tokens[idx].content
		return `<span class="obsidian-tag">#${md.utils.escapeHtml(tag)}</span>`
	}
}
