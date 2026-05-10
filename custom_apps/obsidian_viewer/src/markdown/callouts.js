const hexToRgba = (hex, alpha) => {
	const r = parseInt(hex.slice(1, 3), 16)
	const g = parseInt(hex.slice(3, 5), 16)
	const b = parseInt(hex.slice(5, 7), 16)
	return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

const svgWrap = (inner) =>
	`<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${inner}</svg>`

const ICONS = {
	pencil: svgWrap('<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>'),
	'clipboard-list': svgWrap('<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>'),
	info: svgWrap('<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>'),
	'check-circle-2': svgWrap('<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>'),
	flame: svgWrap('<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>'),
	check: svgWrap('<polyline points="20 6 9 17 4 12"/>'),
	'help-circle': svgWrap('<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>'),
	'alert-triangle': svgWrap('<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>'),
	x: svgWrap('<path d="M18 6 6 18"/><path d="m6 6 12 12"/>'),
	zap: svgWrap('<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>'),
	bug: svgWrap('<path d="m8 2 1.88 1.88"/><path d="M14.12 3.88 16 2"/><path d="M9 7.13v-1a3.003 3.003 0 1 1 6 0v1"/><path d="M12 20c-3.3 0-6-2.7-6-6v-3a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v3c0 3.3-2.7 6-6 6"/><path d="M12 20v-9"/><path d="M6.53 9C4.6 8.8 3 7.1 3 5"/><path d="M6 13H2"/><path d="M3 21c0-2.1 1.7-3.9 3.8-4"/><path d="M20.97 5c0 2.1-1.6 3.8-3.5 4"/><path d="M22 13h-4"/><path d="M17.2 17c2.1.1 3.8 1.9 3.8 4"/>'),
	list: svgWrap('<line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/>'),
	quote: svgWrap('<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/>'),
}

const CALLOUT_TYPES = {
	note: { icon: ICONS.pencil, color: '#086ddd' },
	abstract: { icon: ICONS['clipboard-list'], color: '#00bfbc' },
	summary: { icon: ICONS['clipboard-list'], color: '#00bfbc' },
	tldr: { icon: ICONS['clipboard-list'], color: '#00bfbc' },
	info: { icon: ICONS.info, color: '#086ddd' },
	todo: { icon: ICONS['check-circle-2'], color: '#086ddd' },
	tip: { icon: ICONS.flame, color: '#00bfbc' },
	hint: { icon: ICONS.flame, color: '#00bfbc' },
	important: { icon: ICONS.flame, color: '#00bfbc' },
	success: { icon: ICONS.check, color: '#08b94e' },
	check: { icon: ICONS.check, color: '#08b94e' },
	done: { icon: ICONS.check, color: '#08b94e' },
	question: { icon: ICONS['help-circle'], color: '#ec7500' },
	help: { icon: ICONS['help-circle'], color: '#ec7500' },
	faq: { icon: ICONS['help-circle'], color: '#ec7500' },
	warning: { icon: ICONS['alert-triangle'], color: '#ec7500' },
	caution: { icon: ICONS['alert-triangle'], color: '#ec7500' },
	attention: { icon: ICONS['alert-triangle'], color: '#ec7500' },
	failure: { icon: ICONS.x, color: '#e93147' },
	fail: { icon: ICONS.x, color: '#e93147' },
	missing: { icon: ICONS.x, color: '#e93147' },
	danger: { icon: ICONS.zap, color: '#e93147' },
	error: { icon: ICONS.zap, color: '#e93147' },
	bug: { icon: ICONS.bug, color: '#e93147' },
	example: { icon: ICONS.list, color: '#7852ee' },
	quote: { icon: ICONS.quote, color: '#9e9e9e' },
	cite: { icon: ICONS.quote, color: '#9e9e9e' },
}

export default function calloutPlugin(md) {
	const defaultBlockquoteOpen = md.renderer.rules.blockquote_open
	const defaultBlockquoteClose = md.renderer.rules.blockquote_close

	md.core.ruler.after('block', 'callout', (state) => {
		const tokens = state.tokens

		for (let i = 0; i < tokens.length; i++) {
			if (tokens[i].type !== 'blockquote_open') continue

			const inlineIdx = findNextInline(tokens, i)
			if (inlineIdx === -1) continue

			const inlineToken = tokens[inlineIdx]
			const match = inlineToken.content.match(/^\[!(\w+)\]([-+])?[ \t]*([^\n]*)/)
			if (!match) continue

			const typeName = match[1].toLowerCase()
			const foldState = match[2] || ''
			const customTitle = match[3].trim()
			const title = customTitle || typeName.charAt(0).toUpperCase() + typeName.slice(1)
			const typeInfo = CALLOUT_TYPES[typeName] || CALLOUT_TYPES.note

			tokens[i].meta = tokens[i].meta || {}
			tokens[i].meta.callout = {
				type: typeName,
				title,
				icon: typeInfo.icon,
				color: typeInfo.color,
				foldable: foldState !== '',
				collapsed: foldState === '-',
			}

			inlineToken.content = inlineToken.content.replace(/^\[!\w+\][-+]?[ \t]*[^\n]*/, '')
			if (inlineToken.children) {
				for (let j = 0; j < inlineToken.children.length; j++) {
					if (inlineToken.children[j].type === 'text') {
						const replaced = inlineToken.children[j].content.replace(/^\[!\w+\][-+]?[ \t]*[^\n]*/, '')
						if (replaced !== inlineToken.children[j].content) {
							inlineToken.children[j].content = replaced
							break
						}
					}
				}
			}
		}
	})

	md.renderer.rules.blockquote_open = (tokens, idx, options, env, self) => {
		const callout = tokens[idx].meta?.callout
		if (!callout) {
			return defaultBlockquoteOpen
				? defaultBlockquoteOpen(tokens, idx, options, env, self)
				: self.renderToken(tokens, idx, options)
		}

		const bg = hexToRgba(callout.color, 0.08)
		const collapseAttr = callout.foldable ? ` data-callout-fold="${callout.collapsed ? 'collapsed' : 'expanded'}"` : ''
		return `<div class="obsidian-callout obsidian-callout--${md.utils.escapeHtml(callout.type)}" style="border-left-color: ${callout.color}; background: ${bg}"${collapseAttr}>`
			+ `<div class="obsidian-callout__title" style="color: ${callout.color}">`
			+ `<span class="obsidian-callout__icon">${callout.icon}</span>`
			+ `<span>${md.utils.escapeHtml(callout.title)}</span>`
			+ `</div>`
			+ `<div class="obsidian-callout__body">`
	}

	md.renderer.rules.blockquote_close = (tokens, idx, options, env, self) => {
		const openIdx = findMatchingOpen(tokens, idx)
		const callout = openIdx >= 0 ? tokens[openIdx].meta?.callout : null
		if (!callout) {
			return defaultBlockquoteClose
				? defaultBlockquoteClose(tokens, idx, options, env, self)
				: self.renderToken(tokens, idx, options)
		}
		return '</div></div>'
	}
}

function findNextInline(tokens, fromIdx) {
	for (let i = fromIdx + 1; i < tokens.length; i++) {
		if (tokens[i].type === 'inline') return i
		if (tokens[i].type === 'blockquote_close') return -1
	}
	return -1
}

function findMatchingOpen(tokens, closeIdx) {
	let depth = 0
	for (let i = closeIdx; i >= 0; i--) {
		if (tokens[i].type === 'blockquote_close') depth++
		if (tokens[i].type === 'blockquote_open') depth--
		if (depth === 0) return i
	}
	return -1
}
