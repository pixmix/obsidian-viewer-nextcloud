import katex from 'katex'

export default function mathPlugin(md) {
	// Block math: $$...$$ (closing $$ can be at end of a line, not just alone)
	md.block.ruler.after('fence', 'math_block', (state, startLine, endLine, silent) => {
		const startPos = state.bMarks[startLine] + state.tShift[startLine]
		const lineText = state.src.slice(startPos, state.eMarks[startLine])

		if (!lineText.startsWith('$$')) return false

		if (silent) return true

		// Single-line: $$content$$
		const trimmed = lineText.trim()
		if (trimmed.length > 4 && trimmed.endsWith('$$')) {
			const token = state.push('math_block', '', 0)
			token.content = trimmed.slice(2, -2).trim()
			token.map = [startLine, startLine + 1]
			state.line = startLine + 1
			return true
		}

		// Multi-line: scan for a line ending with $$
		let endFound = false
		let nextLine = startLine + 1

		for (; nextLine < endLine; nextLine++) {
			const pos = state.bMarks[nextLine] + state.tShift[nextLine]
			const line = state.src.slice(pos, state.eMarks[nextLine])
			if (line.trimEnd().endsWith('$$')) {
				endFound = true
				break
			}
		}

		if (!endFound) return false

		const lines = []
		const firstContent = lineText.slice(2).trim()
		if (firstContent) lines.push(firstContent)
		for (let i = startLine + 1; i < nextLine; i++) {
			const pos = state.bMarks[i] + state.tShift[i]
			lines.push(state.src.slice(pos, state.eMarks[i]))
		}
		const lastPos = state.bMarks[nextLine] + state.tShift[nextLine]
		const lastLine = state.src.slice(lastPos, state.eMarks[nextLine]).trimEnd()
		const lastContent = lastLine.slice(0, -2)
		if (lastContent.trim()) lines.push(lastContent)

		const token = state.push('math_block', '', 0)
		token.content = lines.join('\n')
		token.map = [startLine, nextLine + 1]
		state.line = nextLine + 1
		return true
	})

	// Inline math: $...$ or $$...$$
	md.inline.ruler.after('escape', 'math_inline', (state, silent) => {
		const src = state.src
		const pos = state.pos

		if (src.charCodeAt(pos) !== 0x24) return false

		const isDisplay = src.charCodeAt(pos + 1) === 0x24
		const delimLen = isDisplay ? 2 : 1
		const start = pos + delimLen

		if (start >= state.posMax) return false

		let end = start
		while (end < state.posMax) {
			if (src.charCodeAt(end) === 0x24) {
				if (isDisplay) {
					if (end + 1 < state.posMax && src.charCodeAt(end + 1) === 0x24) break
				} else {
					if (end + 1 >= state.posMax || src.charCodeAt(end + 1) !== 0x24) break
				}
			}
			if (src.charCodeAt(end) === 0x5C) end++
			end++
		}

		if (end >= state.posMax) return false
		if (start === end) return false

		if (!silent) {
			const token = state.push(isDisplay ? 'math_display_inline' : 'math_inline', '', 0)
			token.content = src.slice(start, end)
		}

		state.pos = end + delimLen
		return true
	})

	md.renderer.rules.math_block = (tokens, idx) => {
		try {
			const html = katex.renderToString(tokens[idx].content, {
				displayMode: true,
				throwOnError: false,
			})
			return `<div class="obsidian-math-block">${html}</div>`
		} catch (e) {
			return `<div class="obsidian-math-block obsidian-math-error">${md.utils.escapeHtml(tokens[idx].content)}</div>`
		}
	}

	md.renderer.rules.math_display_inline = (tokens, idx) => {
		try {
			const html = katex.renderToString(tokens[idx].content, {
				displayMode: true,
				throwOnError: false,
			})
			return `<span class="obsidian-math-block obsidian-math-inline-display">${html}</span>`
		} catch (e) {
			return `<span class="obsidian-math-block obsidian-math-error">${md.utils.escapeHtml(tokens[idx].content)}</span>`
		}
	}

	md.renderer.rules.math_inline = (tokens, idx) => {
		try {
			return katex.renderToString(tokens[idx].content, {
				displayMode: false,
				throwOnError: false,
			})
		} catch (e) {
			return `<code class="obsidian-math-error">${md.utils.escapeHtml(tokens[idx].content)}</code>`
		}
	}
}
