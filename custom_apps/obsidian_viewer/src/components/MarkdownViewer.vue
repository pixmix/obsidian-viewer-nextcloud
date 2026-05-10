<template>
	<div :class="['markdown-viewer', { 'is-loading': loading }]">
		<div v-if="frontmatter && showProperties" class="markdown-frontmatter">
			<div v-for="(value, key) in frontmatter"
				:key="key"
				class="markdown-frontmatter__field">
				<span class="markdown-frontmatter__icon" v-html="propertyIcon(key, value)" />
				<span class="markdown-frontmatter__key">{{ key }}</span>
				<span class="markdown-frontmatter__value">
					<template v-if="key === 'tags' && Array.isArray(value)">
						<span v-for="tag in value" :key="tag" class="markdown-frontmatter__tag">{{ tag }}</span>
					</template>
					<template v-else-if="Array.isArray(value)">
						<span v-for="item in value" :key="item" class="markdown-frontmatter__list-item">{{ item }}</span>
					</template>
					<template v-else-if="typeof value === 'boolean'">
						<input type="checkbox" :checked="value" disabled class="markdown-frontmatter__checkbox">
					</template>
					<template v-else>
						{{ value }}
					</template>
				</span>
			</div>
		</div>
		<h1 v-if="displayTitle" class="inline-title">{{ displayTitle }}</h1>
		<div ref="body" class="markdown-body" v-html="renderedContent" />
	</div>
</template>

<script>
import MarkdownIt from 'markdown-it'
import frontmatterPlugin from 'markdown-it-front-matter'
import footnotePlugin from 'markdown-it-footnote'
import taskListPlugin from 'markdown-it-task-lists'
import hljs from 'highlight.js/lib/core'
import hljsJavascript from 'highlight.js/lib/languages/javascript'
import hljsPython from 'highlight.js/lib/languages/python'
import hljsTypescript from 'highlight.js/lib/languages/typescript'
import hljsBash from 'highlight.js/lib/languages/bash'
import hljsCss from 'highlight.js/lib/languages/css'
import hljsJson from 'highlight.js/lib/languages/json'
import hljsYaml from 'highlight.js/lib/languages/yaml'
import hljsXml from 'highlight.js/lib/languages/xml'
import hljsPhp from 'highlight.js/lib/languages/php'
import hljsSql from 'highlight.js/lib/languages/sql'
import hljsMarkdown from 'highlight.js/lib/languages/markdown'
import hljsCpp from 'highlight.js/lib/languages/cpp'
import hljsJava from 'highlight.js/lib/languages/java'
import hljsRust from 'highlight.js/lib/languages/rust'
import hljsGo from 'highlight.js/lib/languages/go'
import 'highlight.js/styles/github.css'
import 'katex/dist/katex.min.css'

import wikilinkPlugin from '../markdown/wikilinks.js'
import embedPlugin from '../markdown/embeds.js'
import calloutPlugin from '../markdown/callouts.js'
import tagPlugin from '../markdown/tags.js'
import highlightPlugin from '../markdown/highlights.js'
import mathPlugin from '../markdown/math.js'
import commentPlugin from '../markdown/comments.js'

hljs.registerLanguage('javascript', hljsJavascript)
hljs.registerLanguage('js', hljsJavascript)
hljs.registerLanguage('python', hljsPython)
hljs.registerLanguage('typescript', hljsTypescript)
hljs.registerLanguage('ts', hljsTypescript)
hljs.registerLanguage('bash', hljsBash)
hljs.registerLanguage('shell', hljsBash)
hljs.registerLanguage('sh', hljsBash)
hljs.registerLanguage('css', hljsCss)
hljs.registerLanguage('json', hljsJson)
hljs.registerLanguage('yaml', hljsYaml)
hljs.registerLanguage('yml', hljsYaml)
hljs.registerLanguage('xml', hljsXml)
hljs.registerLanguage('html', hljsXml)
hljs.registerLanguage('php', hljsPhp)
hljs.registerLanguage('sql', hljsSql)
hljs.registerLanguage('markdown', hljsMarkdown)
hljs.registerLanguage('md', hljsMarkdown)
hljs.registerLanguage('cpp', hljsCpp)
hljs.registerLanguage('c', hljsCpp)
hljs.registerLanguage('java', hljsJava)
hljs.registerLanguage('rust', hljsRust)
hljs.registerLanguage('go', hljsGo)

let parsedFrontmatter = null

const md = new MarkdownIt({
	html: true,
	linkify: true,
	typographer: true,
	breaks: true,
	highlight(str, lang) {
		if (lang === 'mermaid' || lang === 'dataview') {
			return ''
		}
		if (lang && hljs.getLanguage(lang)) {
			try {
				return hljs.highlight(str, { language: lang }).value
			} catch (e) { /* fall through */ }
		}
		return ''
	},
})
	.use(frontmatterPlugin, (fm) => {
		try {
			parsedFrontmatter = {}
			const lines = fm.split('\n')
			let currentKey = null
			let currentList = null
			for (const line of lines) {
				const listMatch = line.match(/^[ \t]+- (.*)/)
				if (listMatch && currentKey) {
					if (!currentList) currentList = []
					currentList.push(listMatch[1].trim())
					continue
				}
				if (currentKey && currentList) {
					parsedFrontmatter[currentKey] = currentList
					currentList = null
				}
				const colonIdx = line.indexOf(':')
				if (colonIdx > 0) {
					currentKey = line.slice(0, colonIdx).trim()
					let value = line.slice(colonIdx + 1).trim()
					if (value === '') {
						continue
					}
					if (value.startsWith('[') && value.endsWith(']')) {
						value = value.slice(1, -1).split(',').map((s) => s.trim()).filter(Boolean)
					} else if (value === 'true' || value === 'false') {
						value = value === 'true'
					} else if (/^"(.*)"$/.test(value)) {
						value = value.slice(1, -1)
					}
					parsedFrontmatter[currentKey] = value
				}
			}
			if (currentKey && currentList) {
				parsedFrontmatter[currentKey] = currentList
			}
		} catch (e) {
			parsedFrontmatter = null
		}
	})
	.use(footnotePlugin)
	.use(taskListPlugin, { enabled: true, label: true })

md.core.ruler.before('github-task-lists', 'obsidian_custom_tasks', (state) => {
	const tokens = state.tokens
	for (let i = 2; i < tokens.length; i++) {
		if (tokens[i].type !== 'inline'
			|| tokens[i - 1].type !== 'paragraph_open'
			|| tokens[i - 2].type !== 'list_item_open') continue
		const m = tokens[i].content.match(/^\[(.)\] /)
		if (!m || m[1] === ' ') continue
		if (m[1] === 'x' || m[1] === 'X') {
			tokens[i - 2].attrPush(['data-task', 'x'])
		} else {
			tokens[i - 2].attrPush(['data-task', m[1]])
			tokens[i].content = '[x] ' + tokens[i].content.slice(4)
			if (tokens[i].children && tokens[i].children.length > 0) {
				tokens[i].children[0].content = '[x] ' + tokens[i].children[0].content.slice(4)
			}
		}
	}
})

md.use(wikilinkPlugin)
	.use(embedPlugin)
	.use(calloutPlugin)
	.use(tagPlugin)
	.use(highlightPlugin)
	.use(mathPlugin)
	.use(commentPlugin)

const defaultFenceRenderer = md.renderer.rules.fence
md.renderer.rules.fence = (tokens, idx, options, env, self) => {
	const token = tokens[idx]
	if (token.info.trim() === 'mermaid') {
		return `<div class="obsidian-mermaid">${md.utils.escapeHtml(token.content)}</div>`
	}
	if (token.info.trim() === 'dataview' || token.info.trim() === 'query') {
		const label = token.info.trim() === 'query' ? 'Query' : 'Dataview'
		return `<div class="obsidian-dataview"><div class="obsidian-dataview__label">${label}</div><pre><code>${md.utils.escapeHtml(token.content)}</code></pre></div>`
	}
	const lang = token.info.trim()
	const result = defaultFenceRenderer(tokens, idx, options, env, self)
	const copyLabel = lang
		? md.utils.escapeHtml(lang)
		: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>'
	return `<div class="obsidian-code-block"><button class="obsidian-code-block__copy" title="Copy to clipboard">${copyLabel}</button>${result}</div>`
}

const defaultCodeBlock = md.renderer.rules.code_block
md.renderer.rules.code_block = (tokens, idx, options, env, self) => {
	const result = defaultCodeBlock(tokens, idx, options, env, self)
	const copyLabel = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>'
	return `<div class="obsidian-code-block"><button class="obsidian-code-block__copy" title="Copy to clipboard">${copyLabel}</button>${result}</div>`
}

const defaultLinkOpen = md.renderer.rules.link_open || function(tokens, idx, options, env, self) {
	return self.renderToken(tokens, idx, options)
}
md.renderer.rules.link_open = (tokens, idx, options, env, self) => {
	const token = tokens[idx]
	const hrefIdx = token.attrIndex('href')
	if (hrefIdx >= 0) {
		const href = token.attrs[hrefIdx][1]
		if (/^https?:\/\//.test(href)) {
			token.attrPush(['target', '_blank'])
			token.attrPush(['rel', 'noopener noreferrer'])
		}
	}
	return defaultLinkOpen(tokens, idx, options, env, self)
}

const defaultImageRenderer = md.renderer.rules.image || function(tokens, idx, options, env, self) {
	return self.renderToken(tokens, idx, options)
}
md.renderer.rules.image = (tokens, idx, options, env, self) => {
	const token = tokens[idx]
	const altIdx = token.attrIndex('alt')
	let alt = token.content || ''
	const sizeMatch = alt.match(/^(.*?)\|(\d+(?:x\d+)?)$/)
	if (sizeMatch) {
		alt = sizeMatch[1]
		token.content = alt
		if (altIdx >= 0) token.attrs[altIdx][1] = alt
		const dims = sizeMatch[2]
		if (dims.includes('x')) {
			const [w, h] = dims.split('x')
			token.attrPush(['width', w])
			token.attrPush(['height', h])
		} else {
			token.attrPush(['width', dims])
		}
	}
	return defaultImageRenderer(tokens, idx, options, env, self)
}

md.core.ruler.push('obsidian_blockref', (state) => {
	const blockRefRe = /\s*\^([a-zA-Z0-9_-]+)\s*$/
	for (let i = 0; i < state.tokens.length; i++) {
		const token = state.tokens[i]
		if (token.type !== 'inline') continue
		const match = token.content.match(blockRefRe)
		if (!match) continue
		const blockId = match[1]
		token.content = token.content.replace(blockRefRe, '')
		if (token.children) {
			for (let j = token.children.length - 1; j >= 0; j--) {
				const child = token.children[j]
				if (child.type === 'text' && blockRefRe.test(child.content)) {
					child.content = child.content.replace(blockRefRe, '')
					break
				}
			}
		}
		if (i > 0 && state.tokens[i - 1].type.endsWith('_open')) {
			state.tokens[i - 1].attrPush(['data-block-id', blockId])
		}
	}
})

export default {
	name: 'MarkdownViewer',

	props: {
		content: {
			type: String,
			required: true,
		},
		fileName: {
			type: String,
			default: null,
		},
		embedResolver: {
			type: Function,
			default: null,
		},
		imageResolver: {
			type: Function,
			default: null,
		},
		showProperties: {
			type: Boolean,
			default: false,
		},
		fileUrlResolver: {
			type: Function,
			default: null,
		},
	},

	emits: ['navigate'],

	data() {
		return {
			frontmatter: null,
			renderedHtml: '',
			handlersAttached: false,
			loading: true,
			renderGeneration: 0,
		}
	},

	computed: {
		renderedContent() {
			return this.renderedHtml
		},
		displayTitle() {
			if (!this.fileName) return null
			const name = this.fileName.substring(this.fileName.lastIndexOf('/') + 1)
			return name.replace(/\.(md|canvas)$/, '')
		},
	},

	watch: {
		content: {
			immediate: true,
			handler(val) {
				this.renderGeneration++
				this.loading = true
				parsedFrontmatter = null
				this.dismissAllPreviews()
				this.revokeBlobUrls()
				try {
					this.renderedHtml = md.render(val)
				} catch (e) {
					console.error('md.render threw:', e)
					this.renderedHtml = '<p>Rendering error</p>'
				}
				this.frontmatter = parsedFrontmatter
				this.handlersAttached = false
			},
		},
	},

	mounted() {
		this.handlersAttached = true
		this.$nextTick(() => this.attachHandlers())
	},

	beforeDestroy() {
		this.revokeBlobUrls()
		this.dismissAllPreviews()
	},

	updated() {
		if (!this.handlersAttached) {
			this.handlersAttached = true
			this.$nextTick(() => this.attachHandlers())
		}
	},

	methods: {
		async attachHandlers() {
			if (!this.$refs.body) return
			const gen = this.renderGeneration
			this.loading = true
			this.attachHeadingFoldHandlers()
			this.attachLinkHandlers()
			this.attachCalloutHandlers()
			this.attachExternalImages(this.$refs.body)
			this.attachCopyButtons(this.$refs.body)
			this.resolveImageEmbeds(this.$refs.body)
			this.resolveFileEmbeds(this.$refs.body)
			try {
				await this.resolveEmbeds()
			} catch (e) {
				console.error('resolveEmbeds threw:', e)
			}
			if (gen !== this.renderGeneration) return
			try {
				await this.renderMermaid()
			} catch (e) {
				console.error('renderMermaid threw:', e)
			}
			if (gen !== this.renderGeneration) return
			this.loading = false
		},

		attachHandlersInside(container) {
			this.attachLinksIn(container)
			container.querySelectorAll('.obsidian-callout[data-callout-fold]').forEach((callout) => {
				const title = callout.querySelector('.obsidian-callout__title')
				if (!title) return
				title.onclick = () => {
					callout.dataset.calloutFold = callout.dataset.calloutFold === 'collapsed' ? 'expanded' : 'collapsed'
				}
			})
			container.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach((heading) => {
				if (heading.querySelector('.obsidian-heading-chevron')) return
				heading.dataset.headingText = heading.textContent.trim()
				const chevron = document.createElement('span')
				chevron.className = 'obsidian-heading-chevron'
				chevron.textContent = '▸'
				heading.insertBefore(chevron, heading.firstChild)
				chevron.onclick = (e) => {
					e.stopPropagation()
					const level = parseInt(heading.tagName[1])
					const folded = heading.dataset.folded === 'true'
					let sibling = heading.nextElementSibling
					while (sibling) {
						if (/^H[1-6]$/i.test(sibling.tagName) && parseInt(sibling.tagName[1]) <= level) break
						sibling.style.display = folded ? '' : 'none'
						sibling = sibling.nextElementSibling
					}
					heading.dataset.folded = folded ? 'false' : 'true'
				}
			})
			this.attachExternalImages(container)
			this.attachCopyButtons(container)
			this.resolveImageEmbeds(container)
			this.resolveFileEmbeds(container)
		},

		attachExternalImages(container) {
			container.querySelectorAll('img[src^="http"]:not([data-ext-handled])').forEach((img) => {
				img.setAttribute('data-ext-handled', 'true')
				img.onerror = () => {
					const url = img.src
					const link = document.createElement('a')
					link.href = url
					link.target = '_blank'
					link.rel = 'noopener noreferrer'
					link.className = 'obsidian-external-image-link'
					link.title = url
					link.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">'
						+ '<rect x="3" y="3" width="18" height="18" rx="2"/>'
						+ '<circle cx="8.5" cy="8.5" r="1.5"/>'
						+ '<path d="M21 15l-5-5L5 21"/>'
						+ '</svg>'
						+ '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
						+ '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>'
						+ '<polyline points="15 3 21 3 21 9"/>'
						+ '<line x1="10" y1="14" x2="21" y2="3"/>'
						+ '</svg>'
					img.replaceWith(link)
				}
			})
		},

		attachHeadingFoldHandlers() {
			const headings = this.$refs.body.querySelectorAll('h1, h2, h3, h4, h5, h6')
			headings.forEach((heading) => {
				heading.dataset.headingText = heading.textContent.trim()
				const chevron = document.createElement('span')
				chevron.className = 'obsidian-heading-chevron'
				chevron.textContent = '▸'
				heading.insertBefore(chevron, heading.firstChild)

				chevron.onclick = (e) => {
					e.stopPropagation()
					const level = parseInt(heading.tagName[1])
					const folded = heading.dataset.folded === 'true'
					let sibling = heading.nextElementSibling
					while (sibling) {
						if (/^H[1-6]$/i.test(sibling.tagName) && parseInt(sibling.tagName[1]) <= level) break
						sibling.style.display = folded ? '' : 'none'
						sibling = sibling.nextElementSibling
					}
					heading.dataset.folded = folded ? 'false' : 'true'
				}
			})
		},

		attachLinkHandlers() {
			this.attachLinksIn(this.$refs.body)
		},

		attachLinksIn(container) {
			container.querySelectorAll('.obsidian-wikilink').forEach((link) => {
				link.onclick = (e) => {
					e.preventDefault()
					e.stopPropagation()
					if (this.loading) return
					this.dismissAllPreviews()
					const target = link.dataset.target
					const heading = link.dataset.heading
					if (target) {
						this.$emit('navigate', target)
					} else if (heading) {
						this.scrollToAnchor(heading)
					}
				}
				this.attachHoverPreview(link)
			})
			container.querySelectorAll('a[href]:not(.obsidian-wikilink):not([target="_blank"])').forEach((link) => {
				const href = link.getAttribute('href')
				if (!href || /^(https?:|mailto:|javascript:)/.test(href)) return
				if (href.startsWith('obsidian://')) {
					link.onclick = (e) => { e.preventDefault() }
					link.title = href
					link.style.opacity = '0.5'
					link.style.cursor = 'not-allowed'
					return
				}
				if (href.startsWith('#')) {
					link.onclick = (e) => {
						e.preventDefault()
						e.stopPropagation()
						if (this.loading) return
						this.dismissAllPreviews()
						this.scrollToAnchor(decodeURIComponent(href.slice(1)))
					}
				} else {
					const name = decodeURIComponent(href).replace(/\.md$/, '')
					link.dataset.target = name
					link.onclick = (e) => {
						e.preventDefault()
						e.stopPropagation()
						if (this.loading) return
						this.dismissAllPreviews()
						this.$emit('navigate', name)
					}
					this.attachHoverPreview(link)
				}
			})
		},

		dismissAllPreviews() {
			document.querySelectorAll('.obsidian-hover-preview').forEach((el) => el.remove())
		},

		attachHoverPreview(link) {
			let popup = null
			let hideTimeout = null
			let showTimeout = null
			let fetchCache = null

			const show = () => {
				if (this.loading) return
				if (hideTimeout) { clearTimeout(hideTimeout); hideTimeout = null }
				if (popup || showTimeout) return

				showTimeout = setTimeout(() => {
					showTimeout = null
					if (popup) return

					const target = link.dataset.target
					const heading = link.dataset.heading

					if (!target && heading) {
						let el = null
						if (heading.startsWith('^')) {
							el = this.$refs.body.querySelector('[data-block-id="' + heading.slice(1) + '"]')
						} else {
							const t = heading.toLowerCase().trim()
							for (const h of this.$refs.body.querySelectorAll('h1, h2, h3, h4, h5, h6')) {
								if ((h.dataset.headingText || h.textContent).toLowerCase().trim() === t) { el = h; break }
							}
						}
						if (el) showPopup(el.innerHTML || el.textContent)
					} else if (target && this.embedResolver) {
						if (!fetchCache) fetchCache = this.fetchEmbed(target)
						fetchCache.then((content) => {
							if (content !== null && !popup) showPopup(md.render(content))
						}).catch(() => {})
					}
				}, 1000)
			}

			const showPopup = (html) => {
				popup = document.createElement('div')
				popup.className = 'obsidian-hover-preview'
				popup.style.cursor = 'pointer'
				popup.addEventListener('click', (e) => {
					e.preventDefault()
					e.stopPropagation()
					if (popup) { popup.remove(); popup = null }
					const target = link.dataset.target
					if (target) this.$emit('navigate', target)
				})
				const wrapper = document.createElement('div')
				wrapper.className = 'markdown-body'
				if (this.$refs.body) {
					for (const attr of this.$refs.body.attributes) {
						if (attr.name.startsWith('data-v-')) wrapper.setAttribute(attr.name, attr.value)
					}
				}
				wrapper.innerHTML = html
				popup.appendChild(wrapper)
				document.body.appendChild(popup)
				this.attachHandlersInside(wrapper)
				this.resolveEmbedsIn(wrapper)
				const rect = link.getBoundingClientRect()
				const gap = 6
				popup.style.left = Math.max(8, rect.left) + 'px'
				popup.style.top = (rect.top - gap) + 'px'
				popup.style.transform = 'translateY(-100%)'
				requestAnimationFrame(() => {
					if (!popup) return
					const pr = popup.getBoundingClientRect()
					if (pr.top < 8) {
						popup.style.top = (rect.bottom + gap) + 'px'
						popup.style.transform = 'none'
					}
					const pr2 = popup.getBoundingClientRect()
					if (pr2.right > window.innerWidth - 8) popup.style.left = Math.max(8, window.innerWidth - pr2.width - 8) + 'px'
				})
				popup.onmouseenter = () => { if (hideTimeout) { clearTimeout(hideTimeout); hideTimeout = null } }
				popup.onmouseleave = hide
			}

			const hide = () => {
				if (showTimeout) { clearTimeout(showTimeout); showTimeout = null }
				hideTimeout = setTimeout(() => {
					if (popup) { popup.remove(); popup = null }
				}, 200)
			}

			link.addEventListener('mouseenter', show)
			link.addEventListener('mouseleave', hide)
		},

		scrollToAnchor(anchor) {
			if (!this.$refs.body) return
			let el = this.$refs.body.querySelector('#' + CSS.escape(anchor))
			if (!el && anchor.startsWith('^')) {
				el = this.$refs.body.querySelector('[data-block-id="' + anchor.slice(1) + '"]')
			}
			if (!el) {
				const target = anchor.toLowerCase().trim()
				for (const h of this.$refs.body.querySelectorAll('h1, h2, h3, h4, h5, h6')) {
					if ((h.dataset.headingText || h.textContent).toLowerCase().trim() === target) {
						el = h
						break
					}
				}
			}
			if (!el) return
			el.scrollIntoView({ behavior: 'smooth', block: 'start' })
			el.classList.add('obsidian-highlight-target')
			setTimeout(() => el.classList.remove('obsidian-highlight-target'), 2000)
		},

		attachCalloutHandlers() {
			const callouts = this.$refs.body.querySelectorAll('.obsidian-callout[data-callout-fold]')
			callouts.forEach((callout) => {
				const title = callout.querySelector('.obsidian-callout__title')
				if (!title) return
				title.onclick = () => {
					const current = callout.dataset.calloutFold
					callout.dataset.calloutFold = current === 'collapsed' ? 'expanded' : 'collapsed'
				}
			})
		},

		attachCopyButtons(container) {
			if (!container) return
			container.querySelectorAll('.obsidian-code-block__copy:not([data-copy-bound])').forEach((btn) => {
				btn.setAttribute('data-copy-bound', 'true')
				btn.addEventListener('click', () => {
					const block = btn.closest('.obsidian-code-block')
					if (!block) return
					const code = block.querySelector('pre code')
					if (!code) return
					navigator.clipboard.writeText(code.textContent).then(() => {
						const original = btn.innerHTML
						btn.textContent = 'Copied!'
						btn.classList.add('obsidian-code-block__copy--copied')
						setTimeout(() => {
							btn.innerHTML = original
							btn.classList.remove('obsidian-code-block__copy--copied')
						}, 1500)
					})
				})
			})
		},

		resolveFileEmbeds(container) {
			if (!container || !this.fileUrlResolver) return
			container.querySelectorAll('.obsidian-embed--pdf:not([data-file-resolved])').forEach((el) => {
				el.setAttribute('data-file-resolved', 'true')
				const fileName = el.dataset.embedFile
				if (!fileName) return
				this.fileUrlResolver(fileName).then((info) => {
					if (!info) { el.style.opacity = '0.5'; return }
					const link = el.querySelector('.obsidian-embed-pdf__link')
					if (link) link.href = info.openUrl
					const iframe = el.querySelector('.obsidian-embed-pdf')
					if (iframe) {
						fetch(info.rawUrl, { credentials: 'same-origin' })
							.then((r) => r.blob())
							.then((blob) => {
								const url = URL.createObjectURL(blob)
								iframe.src = url
								if (!this._blobUrls) this._blobUrls = []
								this._blobUrls.push(url)
							})
							.catch(() => { iframe.style.display = 'none' })
					}
				})
			})
			container.querySelectorAll('a.obsidian-embed--file:not([data-file-resolved])').forEach((link) => {
				link.setAttribute('data-file-resolved', 'true')
				const fileName = link.dataset.embedFile
				if (!fileName) return
				this.fileUrlResolver(fileName).then((info) => {
					if (!info) {
						link.style.opacity = '0.5'
						link.style.cursor = 'not-allowed'
						link.removeAttribute('target')
						link.onclick = (e) => e.preventDefault()
						return
					}
					link.href = info.openUrl
				})
			})
		},

		revokeBlobUrls() {
			if (this._blobUrls) {
				this._blobUrls.forEach((url) => URL.revokeObjectURL(url))
				this._blobUrls = []
			}
		},

		resolveImageEmbeds(container) {
			if (!container || !this.imageResolver) return
			container.querySelectorAll('img.obsidian-embed-image[data-embed-target]:not([src])').forEach((img) => {
				const target = img.dataset.embedTarget
				if (!target) return
				const url = this.imageResolver(target)
				if (url) {
					img.src = url
				}
			})
		},

		async resolveEmbeds() {
			await this.resolveEmbedsIn(this.$refs.body)
		},

		async resolveEmbedsIn(container) {
			if (!container) return
			for (let depth = 0; depth < 5; depth++) {
				const embeds = container.querySelectorAll('.obsidian-embed--note[data-embed-target]:not([data-resolved])')
				if (embeds.length === 0) break
				for (const embed of embeds) {
					const target = embed.dataset.embedTarget
					if (!target) continue
					embed.setAttribute('data-resolved', 'true')
					try {
						const content = await this.fetchEmbed(target)
						if (content !== null) {
							const contentEl = embed.querySelector('.obsidian-embed__content')
							if (contentEl) {
								contentEl.innerHTML = md.render(content)
								this.attachHandlersInside(contentEl)
								this.renderMermaidIn(contentEl)
							}
						}
					} catch (e) {
						const contentEl = embed.querySelector('.obsidian-embed__content')
						if (contentEl) {
							contentEl.textContent = 'Could not load: ' + target
						}
					}
				}
			}
		},

		async fetchEmbed(target) {
			if (this.embedResolver) {
				return await this.embedResolver(target)
			}
			return null
		},

		async renderMermaid() {
			await this.renderMermaidIn(this.$refs.body)
		},

		async renderMermaidIn(container) {
			if (!container) return
			const els = container.querySelectorAll('.obsidian-mermaid:not([data-rendered])')
			if (els.length === 0) return

			try {
				const { default: mermaid } = await import('mermaid')

				mermaid.initialize({
					startOnLoad: false,
					theme: 'default',
					securityLevel: 'loose',
					gantt: {
						useMaxWidth: false,
						fontSize: 14,
						barHeight: 24,
						barGap: 6,
						sectionFontSize: 14,
						numberSectionStyles: 4,
					},
				})

				for (let i = 0; i < els.length; i++) {
					const el = els[i]
					el.setAttribute('data-rendered', 'true')
					const code = el.textContent
					try {
						const id = `mermaid-${Date.now()}-${i}`
						const { svg } = await mermaid.render(id, code)
						el.innerHTML = svg
						this.attachMermaidLinks(el)
					} catch (e) {
						el.innerHTML = `<pre class="obsidian-mermaid-error">${md.utils.escapeHtml(code)}</pre>`
					}
				}
			} catch (e) {
				console.error('Mermaid rendering failed:', e)
			}
		},

		attachMermaidLinks(container) {
			container.querySelectorAll('.internal-link').forEach((node) => {
				const label = node.querySelector('.nodeLabel, text')
				const name = label ? label.textContent.trim() : ''
				if (!name) return
				node.style.cursor = 'pointer'
				node.addEventListener('click', (e) => {
					e.preventDefault()
					e.stopPropagation()
					if (this.loading) return
					this.$emit('navigate', name)
				})
			})
		},

		propertyIcon(key, value) {
			const s = (d) => `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${d}</svg>`
			if (key === 'tags') return s('<path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/>')
			if (key === 'aliases') return s('<path d="M8 3H7a2 2 0 0 0-2 2v5a2 2 0 0 1-2 2 2 2 0 0 1 2 2v5a2 2 0 0 0 2 2h1"/><path d="M16 3h1a2 2 0 0 1 2 2v5a2 2 0 0 0 2 2 2 2 0 0 0-2 2v5a2 2 0 0 1-2 2h-1"/>')
			if (typeof value === 'boolean') return s('<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>')
			if (Array.isArray(value)) return s('<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>')
			if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)) return s('<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>')
			return s('<line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/>')
		},

		formatValue(value) {
			if (Array.isArray(value)) {
				return value.join(', ')
			}
			return String(value)
		},
	},
}
</script>

<style scoped>
.markdown-viewer {
	max-width: 800px;
	margin: 0 auto;
	padding: 20px 40px;
}

.markdown-viewer.is-loading :deep(a),
.markdown-viewer.is-loading :deep(.obsidian-wikilink) {
	pointer-events: none;
	opacity: 0.6;
}

.markdown-frontmatter {
	border-bottom: 1px solid var(--color-border);
	padding: 0 0 8px 0;
	margin-bottom: 16px;
	font-size: 0.9em;
}

.markdown-frontmatter__field {
	display: flex;
	align-items: baseline;
	gap: 8px;
	padding: 4px 0;
	min-height: 28px;
}

.markdown-frontmatter__icon {
	display: inline-flex;
	align-items: center;
	flex-shrink: 0;
	color: var(--color-text-maxcontrast);
	align-self: center;
}

.markdown-frontmatter__key {
	color: var(--color-text-maxcontrast);
	min-width: 120px;
	flex-shrink: 0;
}

.markdown-frontmatter__value {
	color: var(--color-main-text);
	font-weight: 600;
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 4px;
}

.markdown-frontmatter__tag {
	display: inline-block;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	padding: 1px 10px;
	border-radius: 12px;
	font-size: 0.9em;
	font-weight: 500;
}

.markdown-frontmatter__list-item {
	display: inline-block;
	font-weight: 600;
}

.markdown-frontmatter__list-item::after {
	content: ',';
	margin-right: 2px;
	font-weight: 400;
}

.markdown-frontmatter__list-item:last-child::after {
	content: none;
}

.markdown-frontmatter__checkbox {
	margin: 0;
	pointer-events: none;
}

.inline-title {
	font-size: 2.2em;
	font-weight: 700;
	margin: 0 0 0.25em 0;
	padding: 0;
	border: none;
	color: var(--color-main-text);
	line-height: 1.2;
}

.markdown-body {
	line-height: 1.75;
}

.markdown-body :deep(p) {
	margin: 1rem 0;
}

.markdown-body :deep(h1),
.markdown-body :deep(h2),
.markdown-body :deep(h3),
.markdown-body :deep(h4),
.markdown-body :deep(h5),
.markdown-body :deep(h6) {
	font-weight: 700;
	color: var(--color-main-text);
}

.markdown-body :deep(h1) {
	font-size: 2em;
	margin-top: 0;
}

.markdown-body :deep(h2) {
	font-size: 1.6em;
}

.markdown-body :deep(h3) {
	font-size: 1.37em;
}

.markdown-body :deep(h4) {
	font-size: 1.15em;
}

.markdown-body :deep(h5) {
	font-size: 1em;
}

.markdown-body :deep(h6) {
	font-size: 0.9em;
}

.markdown-body :deep(code) {
	background: var(--color-background-dark);
	padding: 0.2em 0.4em;
	border-radius: 3px;
	font-size: 0.9em;
}

.markdown-body :deep(pre) {
	background: var(--color-background-dark);
	padding: 16px;
	border-radius: 6px;
	white-space: pre-wrap;
	word-wrap: break-word;
}

.markdown-body :deep(pre code) {
	background: none;
	padding: 0;
}

.markdown-body :deep(.obsidian-code-block) {
	position: relative;
	margin: 1em 0;
}

.markdown-body :deep(.obsidian-code-block__copy) {
	position: absolute;
	top: 6px;
	right: 10px;
	font-size: 0.7em;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.05em;
	z-index: 1;
	background: none;
	border: none;
	cursor: pointer;
	padding: 2px 6px;
	border-radius: 4px;
	line-height: 1;
	display: inline-flex;
	align-items: center;
	gap: 4px;
	transition: color 0.15s, background 0.15s;
}

.markdown-body :deep(.obsidian-code-block__copy:hover) {
	color: var(--color-main-text);
	background: var(--color-background-hover);
}

.markdown-body :deep(.obsidian-code-block__copy--copied) {
	color: var(--color-success, #2d8c3c);
}

.markdown-body :deep(.obsidian-code-block pre) {
	margin: 0;
}

.markdown-body :deep(blockquote) {
	border-left: 4px solid var(--color-primary-element);
	margin-left: 0;
	padding: 0.5em 1em;
	color: var(--color-text-maxcontrast);
}

.markdown-body :deep(a) {
	color: var(--color-primary-element);
}

.markdown-body :deep(a[target="_blank"])::after {
	content: '';
	display: inline-block;
	width: 0.7em;
	height: 0.7em;
	margin-left: 0.15em;
	vertical-align: baseline;
	background-color: currentColor;
	-webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='currentColor' stroke-width='1.5' d='M9 2h5v5M14 2L7 9M6 3H3a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-3'/%3E%3C/svg%3E");
	mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='currentColor' stroke-width='1.5' d='M9 2h5v5M14 2L7 9M6 3H3a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-3'/%3E%3C/svg%3E");
	-webkit-mask-size: contain;
	mask-size: contain;
	opacity: 0.6;
}

.markdown-body :deep(img) {
	max-width: 100%;
}

.markdown-body :deep(.obsidian-external-image-link) {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 6px 10px;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	text-decoration: none;
	cursor: pointer;
	transition: background 0.15s, color 0.15s;
}

.markdown-body :deep(.obsidian-external-image-link:hover) {
	background: var(--color-background-hover);
	color: var(--color-primary-element);
}

.markdown-body :deep(table) {
	border-collapse: collapse;
	width: 100%;
	table-layout: auto;
	margin: 1em 0;
	font-size: 0.95em;
}

.markdown-body :deep(th),
.markdown-body :deep(td) {
	border: 1px solid var(--color-border);
	padding: 3px 8px;
	overflow-wrap: anywhere;
	word-break: break-word;
}

.markdown-body :deep(td code),
.markdown-body :deep(th code) {
	word-break: break-all;
	white-space: normal;
}

.markdown-body :deep(th) {
	background: var(--color-background-dark);
}

.markdown-body :deep(hr) {
	border: none;
	border-top: 1px solid var(--color-border);
}

.markdown-body :deep(ul) {
	padding-left: 2em;
	list-style: disc;
}

.markdown-body :deep(ul ul) {
	list-style: circle;
}

.markdown-body :deep(ul ul ul) {
	list-style: square;
}

.markdown-body :deep(ol) {
	padding-left: 2em;
	list-style: decimal;
}

.markdown-body :deep(li) {
	margin: 0;
	line-height: 1.6;
}

.markdown-body :deep(li p) {
	margin: 0.15em 0;
}

/* Wiki-links */
.markdown-body :deep(.obsidian-wikilink) {
	color: var(--color-primary-element);
	text-decoration: none;
	border-bottom: 1px dashed var(--color-primary-element);
	cursor: pointer;
}

.markdown-body :deep(.obsidian-wikilink:hover) {
	border-bottom-style: solid;
}

/* Tags */
.markdown-body :deep(.obsidian-tag) {
	display: inline-block;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	padding: 1px 8px;
	border-radius: 12px;
	font-size: 0.85em;
	font-weight: 500;
}

/* Highlights */
.markdown-body :deep(.obsidian-highlight) {
	background: rgba(255, 208, 0, 0.4);
	padding: 1px 2px;
	border-radius: 2px;
}

/* Callouts */
.markdown-body :deep(.obsidian-callout) {
	border-left: 4px solid;
	border-radius: 4px;
	margin: 1em 0;
	padding: 12px 16px;
}

.markdown-body :deep(.obsidian-callout__title) {
	display: flex;
	align-items: center;
	gap: 6px;
	font-weight: 600;
	margin-bottom: 4px;
}

.markdown-body :deep(.obsidian-callout__icon) {
	display: inline-flex;
	align-items: center;
	flex-shrink: 0;
}

.markdown-body :deep(.obsidian-callout__icon svg) {
	vertical-align: middle;
}

.markdown-body :deep(.obsidian-callout__body p:first-child) {
	margin-top: 0;
}

.markdown-body :deep(.obsidian-callout__body p:last-child) {
	margin-bottom: 0;
}

/* Embeds */
.markdown-body :deep(.obsidian-embed--note) {
	border-left: 2px solid var(--color-border);
	margin: 1em 0;
	padding: 2px 0 2px 20px;
}

.markdown-body :deep(.obsidian-embed__header) {
	font-size: 0.85em;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	margin-bottom: 4px;
}

.markdown-body :deep(.obsidian-embed--image) {
	margin: 1em 0;
}

.markdown-body :deep(.obsidian-embed-image) {
	max-width: 100%;
	height: auto;
	border-radius: 4px;
}

.markdown-body :deep(.obsidian-embed--file) {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 4px 10px;
	margin: 0.5em 0;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
	text-decoration: none;
	transition: background 0.15s, color 0.15s, border-color 0.15s;
}

.markdown-body :deep(.obsidian-embed--file:hover) {
	background: var(--color-background-hover);
	color: var(--color-primary-element);
	border-color: var(--color-primary-element-light);
}

.markdown-body :deep(.obsidian-embed--file svg) {
	flex-shrink: 0;
}

.markdown-body :deep(.obsidian-embed--pdf) {
	margin: 1em 0;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	overflow: hidden;
}

.markdown-body :deep(.obsidian-embed-pdf) {
	display: block;
	width: 100%;
	height: 500px;
	border: none;
}

.markdown-body :deep(.obsidian-embed-pdf__link) {
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 6px 12px;
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 0.85em;
	text-decoration: none;
	border-top: 1px solid var(--color-border);
	transition: color 0.15s, background 0.15s;
}

.markdown-body :deep(.obsidian-embed-pdf__link:hover) {
	background: var(--color-background-hover);
	color: var(--color-primary-element);
}

/* Mermaid */
.markdown-body :deep(.obsidian-mermaid) {
	margin: 1em 0;
	overflow-x: auto;
}

.markdown-body :deep(.obsidian-mermaid svg) {
	display: block;
}

.markdown-body :deep(.obsidian-mermaid .internal-link) {
	cursor: pointer;
}

.markdown-body :deep(.obsidian-mermaid .internal-link .nodeLabel) {
	text-decoration: underline;
	color: var(--color-primary-element);
}

.markdown-body :deep(.obsidian-mermaid-error) {
	color: var(--color-error);
	font-size: 0.9em;
}

/* Math */
.markdown-body :deep(.obsidian-math-block) {
	margin: 1em 0;
	text-align: center;
	overflow-x: auto;
}

.markdown-body :deep(.obsidian-math-error) {
	color: var(--color-error);
}

/* Dataview placeholder */
.markdown-body :deep(.obsidian-dataview) {
	background: var(--color-background-dark);
	border: 1px dashed var(--color-border);
	border-radius: 4px;
	margin: 1em 0;
	padding: 12px 16px;
}

.markdown-body :deep(.obsidian-dataview__label) {
	font-size: 0.8em;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	margin-bottom: 4px;
}

.markdown-body :deep(.obsidian-dataview pre) {
	margin: 0;
	background: none;
	padding: 0;
}

/* Task checkboxes */
.markdown-body :deep(.contains-task-list) {
	padding-left: 1.4em;
	margin: 0.25em 0;
}

.markdown-body :deep(.contains-task-list .contains-task-list) {
	margin: 0;
}

.markdown-body :deep(.contains-task-list li) {
	list-style: none;
	margin: 0 !important;
	padding: 0 0 0 0.2em !important;
	line-height: 1.6;
}

.markdown-body :deep(.contains-task-list li p) {
	margin: 0 !important;
	padding: 0 !important;
	display: inline !important;
}

.markdown-body :deep(.task-list-item label) {
	display: inline;
	vertical-align: baseline;
	line-height: 1.6;
}

.markdown-body :deep(.task-list-item-checkbox) {
	margin: 0 6px 0 0;
	padding: 0;
	vertical-align: middle;
	pointer-events: none;
	height: 14px;
	width: 14px;
	min-height: 0;
}

.markdown-body :deep(.task-list-item[data-task="x"]) label,
.markdown-body :deep(.task-list-item[data-task="X"]) label {
	text-decoration: line-through;
	color: var(--color-text-maxcontrast);
}

/* Footnotes */
.markdown-body :deep(.footnotes-sep) {
	margin-top: 2em;
	border-color: var(--color-border);
}

.markdown-body :deep(.footnotes) {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
	line-height: 1.6;
}

.markdown-body :deep(.footnotes-list) {
	padding-left: 1.5em;
}

.markdown-body :deep(.footnote-ref) {
	font-size: 0.75em;
	vertical-align: super;
	line-height: 0;
}

.markdown-body :deep(.footnote-ref a),
.markdown-body :deep(.footnote-backref) {
	color: var(--color-primary-element);
	text-decoration: none;
}

.markdown-body :deep(.footnote-backref) {
	margin-left: 4px;
	font-size: 0.85em;
}

/* Foldable callouts */
.markdown-body :deep(.obsidian-callout[data-callout-fold]) .obsidian-callout__title {
	cursor: pointer;
}

.markdown-body :deep(.obsidian-callout[data-callout-fold]) .obsidian-callout__title::after {
	content: '▸';
	margin-left: auto;
	transition: transform 0.15s;
}

.markdown-body :deep(.obsidian-callout[data-callout-fold="expanded"]) .obsidian-callout__title::after {
	transform: rotate(90deg);
}

.markdown-body :deep(.obsidian-callout[data-callout-fold="collapsed"]) .obsidian-callout__body {
	display: none;
}

/* Heading fold chevrons */
.markdown-body :deep(h1),
.markdown-body :deep(h2),
.markdown-body :deep(h3),
.markdown-body :deep(h4),
.markdown-body :deep(h5),
.markdown-body :deep(h6) {
	position: relative;
}

.markdown-body :deep(.obsidian-heading-chevron) {
	position: absolute;
	left: -1.4em;
	top: 0.15em;
	display: inline-block;
	width: 1em;
	text-align: center;
	cursor: pointer;
	color: rgba(124, 77, 255, 0.45);
	opacity: 0;
	transition: opacity 0.15s, transform 0.15s;
	transform: rotate(90deg);
	user-select: none;
	font-size: 0.6em;
}

.markdown-body :deep(h1:hover) > .obsidian-heading-chevron,
.markdown-body :deep(h2:hover) > .obsidian-heading-chevron,
.markdown-body :deep(h3:hover) > .obsidian-heading-chevron,
.markdown-body :deep(h4:hover) > .obsidian-heading-chevron,
.markdown-body :deep(h5:hover) > .obsidian-heading-chevron,
.markdown-body :deep(h6:hover) > .obsidian-heading-chevron {
	opacity: 1;
}

.markdown-body :deep([data-folded="true"]) > .obsidian-heading-chevron {
	opacity: 1;
	color: rgb(124, 77, 255);
	transform: rotate(0deg);
}

/* Scroll target highlight */
.markdown-body :deep(.obsidian-highlight-target) {
	background: rgba(255, 208, 0, 0.25);
	border-radius: 4px;
	transition: background 0.3s ease;
	animation: highlight-fade 2s ease-out;
}

@keyframes highlight-fade {
	0% { background: rgba(255, 208, 0, 0.45); }
	70% { background: rgba(255, 208, 0, 0.25); }
	100% { background: transparent; }
}

/* Tooltips via aria-label */
.markdown-body :deep([aria-label]) {
	position: relative;
}

.markdown-body :deep([aria-label])::after {
	content: attr(aria-label);
	position: absolute;
	z-index: 1000;
	background: var(--color-main-text, #1a1a1a);
	color: var(--color-main-background, #fff);
	padding: 4px 8px;
	border-radius: 4px;
	font-size: 0.8em;
	font-weight: 400;
	white-space: nowrap;
	pointer-events: none;
	opacity: 0;
	transition: opacity 0.15s;
	left: 50%;
	transform: translateX(-50%);
	top: calc(100% + 4px);
}

.markdown-body :deep([aria-label]):hover::after {
	opacity: 1;
}

.markdown-body :deep([aria-label-position="top"])::after {
	top: auto;
	bottom: calc(100% + 4px);
}

.markdown-body :deep([aria-label-position="left"])::after {
	top: 50%;
	left: auto;
	right: calc(100% + 6px);
	transform: translateY(-50%);
}

.markdown-body :deep([aria-label-position="right"])::after {
	top: 50%;
	left: calc(100% + 6px);
	transform: translateY(-50%);
}
</style>

<style>
/* Hover preview (unscoped — appended to body) */
.obsidian-hover-preview {
	position: fixed;
	z-index: 10000;
	max-width: 500px;
	max-height: 300px;
	overflow-y: auto;
	background: var(--color-main-background, #fff);
	border: 1px solid var(--color-border, #ddd);
	border-radius: 8px;
	padding: 12px 16px;
	box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
	font-size: 0.92em;
	line-height: 1.6;
	pointer-events: auto;
}

/* Obsidian theme colour swatch tables */
.theme-dark { background-color: #202020 !important; color: #dcddde !important; }
.theme-dark td, .theme-dark th { border-color: #333 !important; }
.theme-light { background-color: #fff !important; color: #1a1a1a !important; }
.theme-light td, .theme-light th { border-color: #ddd !important; }

.theme-dark { --bg-primary: #202020; --bg-primary-alt: #1a1a1a; --bg-secondary: #161616; --bg-secondary-alt: #0d0d0d; --bg-mod-border: #333; --bg-mod-form: rgba(0,0,0,.3); --bg-mod-form-hl: rgba(0,0,0,.22); --bg-mod-box-shadow: rgba(0,0,0,.3); --bg-mod-success: #197300; --bg-mod-error: #3d0000; --bg-mod-error-rgb: 61,0,0; --bg-mod-error-hover: #470000; --bg-mod-cover: rgba(0,0,0,.6); --t-accent: #7f6df2; --t-accent-hover: #8875ff; --t-normal: #ddd; --t-muted: #999; --t-muted-rgb: 153,153,153; --t-faint: #666; --t-error: #e93147; --t-error-hover: #ff3a50; --t-hl-bg: rgba(255,255,0,.4); --t-hl-bg-active: rgba(255,128,0,.4); --t-selection: rgba(0,122,255,.4); --t-on-accent: #fff; --i-normal: #2a2a2a; --i-hover: #303030; --i-accent: #7f6df2; --i-accent-rgb: 127,109,242; --i-accent-hover: #8875ff; --i-success: #197300; --sb-active-thumb: rgba(255,255,255,.2); --sb-bg: rgba(255,255,255,.05); --sb-thumb: rgba(255,255,255,.1); }
.theme-light { --bg-primary: #fff; --bg-primary-alt: #f5f6f8; --bg-secondary: #e9e9e9; --bg-secondary-alt: #d4d4d4; --bg-mod-border: #ddd; --bg-mod-form: #fff; --bg-mod-form-hl: #fff; --bg-mod-box-shadow: rgba(0,0,0,.1); --bg-mod-success: #a6e3a1; --bg-mod-error: #fee2e2; --bg-mod-error-rgb: 254,226,226; --bg-mod-error-hover: #fecaca; --bg-mod-cover: rgba(0,0,0,.4); --t-accent: #705dcf; --t-accent-hover: #7c6be0; --t-normal: #1a1a1a; --t-muted: #666; --t-muted-rgb: 102,102,102; --t-faint: #999; --t-error: #e93147; --t-error-hover: #d42a3e; --t-hl-bg: rgba(255,255,0,.4); --t-hl-bg-active: rgba(255,128,0,.4); --t-selection: rgba(0,122,255,.3); --t-on-accent: #fff; --i-normal: #f5f6f8; --i-hover: #e9e9e9; --i-accent: #705dcf; --i-accent-rgb: 112,93,207; --i-accent-hover: #7c6be0; --i-success: #197300; --sb-active-thumb: rgba(0,0,0,.2); --sb-bg: rgba(0,0,0,.05); --sb-thumb: rgba(0,0,0,.1); }

/* Text color swatches */
.color-background-primary-text { color: var(--bg-primary) !important; }
.color-background-primary-alt-text { color: var(--bg-primary-alt) !important; }
.color-background-secondary-text { color: var(--bg-secondary) !important; }
.color-background-secondary-alt-text { color: var(--bg-secondary-alt) !important; }
.color-background-modifier-border-text { color: var(--bg-mod-border) !important; }
.color-background-modifier-form-field-text { color: var(--bg-mod-form) !important; }
.color-background-modifier-form-field-highlighted-text { color: var(--bg-mod-form-hl) !important; }
.color-background-modifier-box-shadow-text { color: var(--bg-mod-box-shadow) !important; }
.color-background-modifier-success-text { color: var(--bg-mod-success) !important; }
.color-background-modifier-error-text { color: var(--bg-mod-error) !important; }
.color-background-modifier-error-rgb-text { color: var(--bg-mod-error-rgb) !important; }
.color-background-modifier-error-hover-text { color: var(--bg-mod-error-hover) !important; }
.color-background-modifier-cover-text { color: var(--bg-mod-cover) !important; }
.color-text-accent-text { color: var(--t-accent) !important; }
.color-text-accent-hover-text { color: var(--t-accent-hover) !important; }
.color-text-normal-text { color: var(--t-normal) !important; }
.color-text-muted-text { color: var(--t-muted) !important; }
.color-text-muted-rgb-text { color: var(--t-muted-rgb) !important; }
.color-text-faint-text { color: var(--t-faint) !important; }
.color-text-error-text { color: var(--t-error) !important; }
.color-text-error-hover-text { color: var(--t-error-hover) !important; }
.color-text-highlight-bg-text { color: var(--t-hl-bg) !important; }
.color-text-highlight-bg-active-text { color: var(--t-hl-bg-active) !important; }
.color-text-selection-text { color: var(--t-selection) !important; }
.color-text-on-accent-text { color: var(--t-on-accent) !important; }
.color-interactive-normal-text { color: var(--i-normal) !important; }
.color-interactive-hover-text { color: var(--i-hover) !important; }
.color-interactive-accent-text { color: var(--i-accent) !important; }
.color-interactive-accent-rgb-text { color: var(--i-accent-rgb) !important; }
.color-interactive-accent-hover-text { color: var(--i-accent-hover) !important; }
.color-interactive-success-text { color: var(--i-success) !important; }
.color-scrollbar-active-thumb-bg-text { color: var(--sb-active-thumb) !important; }
.color-scrollbar-bg-text { color: var(--sb-bg) !important; }
.color-scrollbar-thumb-bg-text { color: var(--sb-thumb) !important; }

/* Background color swatches */
.color-background-primary-bg { background-color: var(--bg-primary) !important; }
.color-background-primary-alt-bg { background-color: var(--bg-primary-alt) !important; }
.color-background-secondary-bg { background-color: var(--bg-secondary) !important; }
.color-background-secondary-alt-bg { background-color: var(--bg-secondary-alt) !important; }
.color-background-modifier-border-bg { background-color: var(--bg-mod-border) !important; }
.color-background-modifier-form-field-bg { background-color: var(--bg-mod-form) !important; }
.color-background-modifier-form-field-highlighted-bg { background-color: var(--bg-mod-form-hl) !important; }
.color-background-modifier-box-shadow-bg { background-color: var(--bg-mod-box-shadow) !important; }
.color-background-modifier-success-bg { background-color: var(--bg-mod-success) !important; }
.color-background-modifier-error-bg { background-color: var(--bg-mod-error) !important; }
.color-background-modifier-error-rgb-bg { background-color: var(--bg-mod-error-rgb) !important; }
.color-background-modifier-error-hover-bg { background-color: var(--bg-mod-error-hover) !important; }
.color-background-modifier-cover-bg { background-color: var(--bg-mod-cover) !important; }
.color-text-accent-bg { background-color: var(--t-accent) !important; }
.color-text-accent-hover-bg { background-color: var(--t-accent-hover) !important; }
.color-text-normal-bg { background-color: var(--t-normal) !important; }
.color-text-muted-bg { background-color: var(--t-muted) !important; }
.color-text-muted-rgb-bg { background-color: var(--t-muted-rgb) !important; }
.color-text-faint-bg { background-color: var(--t-faint) !important; }
.color-text-error-bg { background-color: var(--t-error) !important; }
.color-text-error-hover-bg { background-color: var(--t-error-hover) !important; }
.color-text-highlight-bg-bg { background-color: var(--t-hl-bg) !important; }
.color-text-highlight-bg-active-bg { background-color: var(--t-hl-bg-active) !important; }
.color-text-selection-bg { background-color: var(--t-selection) !important; }
.color-text-on-accent-bg { background-color: var(--t-on-accent) !important; }
.color-interactive-normal-bg { background-color: var(--i-normal) !important; }
.color-interactive-hover-bg { background-color: var(--i-hover) !important; }
.color-interactive-accent-bg { background-color: var(--i-accent) !important; }
.color-interactive-accent-rgb-bg { background-color: var(--i-accent-rgb) !important; }
.color-interactive-accent-hover-bg { background-color: var(--i-accent-hover) !important; }
.color-interactive-success-bg { background-color: var(--i-success) !important; }
.color-scrollbar-active-thumb-bg-bg { background-color: var(--sb-active-thumb) !important; }
.color-scrollbar-bg-bg { background-color: var(--sb-bg) !important; }
.color-scrollbar-thumb-bg-bg { background-color: var(--sb-thumb) !important; }
</style>
