<template>
	<div ref="container"
		class="canvas-viewer"
		@wheel.prevent="onWheel"
		@pointerdown="onPanStart">
		<svg class="canvas-viewer__svg" :viewBox="viewBox">
			<defs>
				<marker id="cv-arrow-default"
					viewBox="0 -5 10 10" refX="10" refY="0"
					markerWidth="8" markerHeight="8"
					markerUnits="userSpaceOnUse" orient="auto">
					<path d="M0,-5L10,0L0,5" fill="var(--color-text-maxcontrast)" />
				</marker>
				<marker v-for="col in uniqueEdgeColours" :key="col"
					:id="'cv-arrow-' + col.replace('#', '')"
					viewBox="0 -5 10 10" refX="10" refY="0"
					markerWidth="8" markerHeight="8"
					markerUnits="userSpaceOnUse" orient="auto">
					<path d="M0,-5L10,0L0,5" :fill="col" />
				</marker>
			</defs>
			<g v-for="edge in edgeLines" :key="edge.id">
				<path :d="edge.path"
					class="canvas-viewer__edge"
					:style="edge.style"
					:marker-start="edge.markerStart"
					:marker-end="edge.markerEnd" />
				<g v-if="edge.label">
					<rect :x="edge.labelX - edge.labelW / 2 - 6"
						:y="edge.labelY - 10"
						:width="edge.labelW + 12"
						:height="20"
						rx="4" ry="4"
						class="canvas-viewer__edge-label-bg" />
					<text :x="edge.labelX" :y="edge.labelY + 4"
						class="canvas-viewer__edge-label">
						{{ edge.label }}
					</text>
				</g>
			</g>
		</svg>
		<div class="canvas-viewer__nodes" :style="transformStyle">
			<div v-for="(node, idx) in canvas.nodes"
				:key="node.id"
				class="canvas-viewer__node"
				:class="nodeClasses(node)"
				:style="nodeStyle(node, idx)"
				@dblclick="onNodeDblClick(node)">
				<template v-if="node.type === 'group'">
					<div v-if="node.label" class="canvas-viewer__group-label">{{ node.label }}</div>
				</template>
				<template v-else-if="node.type === 'text'">
					<div class="canvas-viewer__card-body" v-html="renderMarkdown(node.text || '')" />
				</template>
				<template v-else-if="node.type === 'file'">
					<div class="canvas-viewer__file-header">
						<span class="canvas-viewer__file-icon">&#128196;</span>
						{{ fileLabel(node) }}
					</div>
					<div v-if="fileContents[node.file]"
						class="canvas-viewer__card-body"
						v-html="renderMarkdown(fileContents[node.file])" />
				</template>
				<template v-else-if="node.type === 'link'">
					<div class="canvas-viewer__link-header">
						<span class="canvas-viewer__link-icon">&#128279;</span>
						<a :href="node.url" target="_blank" rel="noopener">{{ node.url }}</a>
					</div>
					<iframe v-if="iframeState[node.id] !== 'failed'"
						class="canvas-viewer__link-frame"
						:src="node.url"
						sandbox="allow-scripts allow-same-origin"
						loading="lazy"
						referrerpolicy="no-referrer"
						@load="onIframeLoad($event, node.id)" />
					<div v-if="iframeState[node.id] === 'failed'"
						class="canvas-viewer__link-fallback">
						<div class="canvas-viewer__link-fallback-icon">&#127760;</div>
						<a :href="node.url" target="_blank" rel="noopener">Open link</a>
					</div>
				</template>
			</div>
		</div>
		<div class="canvas-viewer__controls">
			<button title="Zoom to fit (Shift+1)" @click="centreView">Fit</button>
			<button title="Zoom in" @click="zoomBy(1.3)">+</button>
			<button title="Zoom out" @click="zoomBy(0.7)">&minus;</button>
		</div>
	</div>
</template>

<script>
import MarkdownIt from 'markdown-it'
import wikilinkPlugin from '../markdown/wikilinks.js'
import tagPlugin from '../markdown/tags.js'
import highlightPlugin from '../markdown/highlights.js'

const PRESET_COLOURS = {
	1: { border: '#e93147', bg: 'rgba(233, 49, 71, 0.12)' },
	2: { border: '#ec7500', bg: 'rgba(236, 117, 0, 0.12)' },
	3: { border: '#e0ac00', bg: 'rgba(224, 172, 0, 0.12)' },
	4: { border: '#08b94e', bg: 'rgba(8, 185, 78, 0.12)' },
	5: { border: '#00bfbc', bg: 'rgba(0, 191, 188, 0.12)' },
	6: { border: '#7852ee', bg: 'rgba(120, 82, 238, 0.12)' },
}

const md = new MarkdownIt({ html: false, linkify: true, breaks: true })
	.use(wikilinkPlugin)
	.use(tagPlugin)
	.use(highlightPlugin)

export default {
	name: 'CanvasViewer',

	props: {
		content: { type: String, required: true },
		embedResolver: { type: Function, default: null },
	},

	emits: ['navigate'],

	data() {
		return {
			panX: 0,
			panY: 0,
			zoom: 1,
			isPanning: false,
			lastPointer: null,
			fileContents: {},
			iframeState: {},
			containerW: 1200,
			containerH: 800,
		}
	},

	computed: {
		canvas() {
			try {
				const parsed = JSON.parse(this.content)
				return { nodes: parsed.nodes || [], edges: parsed.edges || [] }
			} catch {
				return { nodes: [], edges: [] }
			}
		},

		nodeMap() {
			const m = {}
			for (const n of this.canvas.nodes) m[n.id] = n
			return m
		},

		viewBox() {
			const vx = -this.panX / this.zoom
			const vy = -this.panY / this.zoom
			return `${vx} ${vy} ${this.containerW / this.zoom} ${this.containerH / this.zoom}`
		},

		transformStyle() {
			return {
				transform: `translate(${this.panX}px, ${this.panY}px) scale(${this.zoom})`,
				transformOrigin: '0 0',
			}
		},

		uniqueEdgeColours() {
			const colours = new Set()
			for (const e of this.canvas.edges) {
				const col = this.resolveColour(e.color)
				if (col) colours.add(col.border)
			}
			return [...colours]
		},

		edgeLines() {
			return this.canvas.edges.map((e) => {
				const from = this.nodeMap[e.fromNode]
				const to = this.nodeMap[e.toNode]
				if (!from || !to) return null

				const fromSide = e.fromSide || this.nearestSide(from, to)
				const toSide = e.toSide || this.nearestSide(to, from)
				const p1 = this.anchorPoint(from, fromSide)
				const p2 = this.anchorPoint(to, toSide)
				const dist = Math.hypot(p2.x - p1.x, p2.y - p1.y)
				const offset = Math.min(dist * 0.4, 120)
				const c1 = this.controlPoint(p1, fromSide, offset)
				const c2 = this.controlPoint(p2, toSide, offset)
				const path = `M${p1.x},${p1.y} C${c1.x},${c1.y} ${c2.x},${c2.y} ${p2.x},${p2.y}`

				const mx = 0.125 * p1.x + 0.375 * c1.x + 0.375 * c2.x + 0.125 * p2.x
				const my = 0.125 * p1.y + 0.375 * c1.y + 0.375 * c2.y + 0.125 * p2.y

				const col = this.resolveColour(e.color)
				const strokeColour = col ? col.border : null
				const markerId = strokeColour
					? 'url(#cv-arrow-' + strokeColour.replace('#', '') + ')'
					: 'url(#cv-arrow-default)'

				const fromEnd = e.fromEnd || 'none'
				const toEnd = e.toEnd || 'arrow'
				const label = e.label || null
				const labelW = label ? label.length * 7 : 0

				return {
					id: e.id,
					path,
					label,
					labelX: mx,
					labelY: my,
					labelW,
					markerStart: fromEnd === 'arrow' ? markerId : null,
					markerEnd: toEnd === 'arrow' ? markerId : null,
					style: strokeColour ? { stroke: strokeColour } : {},
				}
			}).filter(Boolean)
		},
	},

	watch: {
		canvas: {
			immediate: true,
			handler() {
				this.$nextTick(() => {
					this.measureContainer()
					this.centreView()
					this.loadFileNodes()
				})
			},
		},
	},

	mounted() {
		this.measureContainer()
		this._resizeObserver = new ResizeObserver(() => this.measureContainer())
		this._resizeObserver.observe(this.$refs.container)
		document.addEventListener('keydown', this.onKeyDown)
	},

	beforeDestroy() {
		if (this._resizeObserver) this._resizeObserver.disconnect()
		document.removeEventListener('keydown', this.onKeyDown)
	},

	methods: {
		measureContainer() {
			const c = this.$refs.container
			if (c) {
				this.containerW = c.clientWidth || 1200
				this.containerH = c.clientHeight || 800
			}
		},

		resolveColour(color) {
			if (!color) return null
			if (PRESET_COLOURS[color]) return PRESET_COLOURS[color]
			return { border: color, bg: color + '1f' }
		},

		nodeClasses(node) {
			return {
				['canvas-viewer__node--' + node.type]: true,
				'canvas-viewer__node--coloured': !!node.color,
			}
		},

		nodeStyle(node, idx) {
			const col = this.resolveColour(node.color)
			const style = {
				left: node.x + 'px',
				top: node.y + 'px',
				width: node.width + 'px',
				height: node.height + 'px',
				zIndex: idx,
			}
			if (col && node.type !== 'group') {
				style.borderLeftColor = col.border
				style.borderLeftWidth = '4px'
				style.backgroundColor = col.bg
			}
			if (col && node.type === 'group') {
				style.borderColor = col.border
				style.backgroundColor = col.bg
			}
			return style
		},

		anchorPoint(node, side) {
			const x = node.x, y = node.y, w = node.width, h = node.height
			switch (side) {
			case 'top': return { x: x + w / 2, y }
			case 'bottom': return { x: x + w / 2, y: y + h }
			case 'left': return { x, y: y + h / 2 }
			case 'right': return { x: x + w, y: y + h / 2 }
			default: return { x: x + w / 2, y: y + h / 2 }
			}
		},

		nearestSide(nodeA, nodeB) {
			const ax = nodeA.x + nodeA.width / 2
			const ay = nodeA.y + nodeA.height / 2
			const bx = nodeB.x + nodeB.width / 2
			const by = nodeB.y + nodeB.height / 2
			const dx = bx - ax
			const dy = by - ay
			if (Math.abs(dx) > Math.abs(dy)) {
				return dx > 0 ? 'right' : 'left'
			}
			return dy > 0 ? 'bottom' : 'top'
		},

		bezierPath(p1, p2, fromSide, toSide) {
			const dist = Math.hypot(p2.x - p1.x, p2.y - p1.y)
			const offset = Math.min(dist * 0.4, 120)
			const c1 = this.controlPoint(p1, fromSide, offset)
			const c2 = this.controlPoint(p2, toSide, offset)
			return `M${p1.x},${p1.y} C${c1.x},${c1.y} ${c2.x},${c2.y} ${p2.x},${p2.y}`
		},

		controlPoint(p, side, offset) {
			switch (side) {
			case 'top': return { x: p.x, y: p.y - offset }
			case 'bottom': return { x: p.x, y: p.y + offset }
			case 'left': return { x: p.x - offset, y: p.y }
			case 'right': return { x: p.x + offset, y: p.y }
			default: return p
			}
		},

		centreView() {
			const nodes = this.canvas.nodes
			if (!nodes.length) return
			let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity
			for (const n of nodes) {
				if (n.x < minX) minX = n.x
				if (n.y < minY) minY = n.y
				if (n.x + n.width > maxX) maxX = n.x + n.width
				if (n.y + n.height > maxY) maxY = n.y + n.height
			}
			const contentW = maxX - minX
			const contentH = maxY - minY
			const pad = 40
			const scaleX = (this.containerW - pad * 2) / contentW
			const scaleY = (this.containerH - pad * 2) / contentH
			this.zoom = Math.min(Math.max(Math.min(scaleX, scaleY), 0.15), 2)
			this.panX = (this.containerW - contentW * this.zoom) / 2 - minX * this.zoom
			this.panY = (this.containerH - contentH * this.zoom) / 2 - minY * this.zoom
		},

		zoomBy(factor) {
			const cx = this.containerW / 2
			const cy = this.containerH / 2
			const oldZoom = this.zoom
			this.zoom = Math.min(Math.max(this.zoom * factor, 0.1), 4)
			this.panX = cx - (cx - this.panX) * (this.zoom / oldZoom)
			this.panY = cy - (cy - this.panY) * (this.zoom / oldZoom)
		},

		async loadFileNodes() {
			if (!this.embedResolver) return
			for (const node of this.canvas.nodes) {
				if (node.type === 'file' && node.file && !this.fileContents[node.file]) {
					try {
						const target = node.file.replace(/\.md$/, '')
						const content = await this.embedResolver(target)
						if (content) this.$set(this.fileContents, node.file, content)
					} catch { /* skip */ }
				}
			}
		},

		renderMarkdown(text) {
			return md.render(text)
		},

		fileLabel(node) {
			return (node.file || '').replace(/\.md$/, '').split('/').pop()
		},

		onIframeLoad(event, nodeId) {
			const iframe = event.target
			try {
				// Same-origin: accessing contentDocument succeeds
				const doc = iframe.contentDocument
				if (doc && doc.body && doc.body.innerHTML.length > 0) {
					this.$set(this.iframeState, nodeId, 'loaded')
					return
				}
			} catch {
				// Cross-origin or CSP blocked
			}
			this.$set(this.iframeState, nodeId, 'failed')
		},

		onNodeDblClick(node) {
			if (node.type === 'file' && node.file) {
				this.$emit('navigate', node.file.replace(/\.md$/, ''))
			}
		},

		onWheel(e) {
			const rect = this.$refs.container.getBoundingClientRect()
			const mx = e.clientX - rect.left
			const my = e.clientY - rect.top
			const oldZoom = this.zoom
			const delta = e.deltaY > 0 ? 0.9 : 1.1
			this.zoom = Math.min(Math.max(this.zoom * delta, 0.1), 4)
			this.panX = mx - (mx - this.panX) * (this.zoom / oldZoom)
			this.panY = my - (my - this.panY) * (this.zoom / oldZoom)
		},

		onPanStart(e) {
			if (e.target.closest('a')) return
			if (e.button !== 0 && e.button !== 1) return
			this.isPanning = true
			this.lastPointer = { x: e.clientX, y: e.clientY }
			const onMove = (ev) => {
				if (!this.isPanning) return
				this.panX += ev.clientX - this.lastPointer.x
				this.panY += ev.clientY - this.lastPointer.y
				this.lastPointer = { x: ev.clientX, y: ev.clientY }
			}
			const onUp = () => {
				this.isPanning = false
				document.removeEventListener('pointermove', onMove)
				document.removeEventListener('pointerup', onUp)
			}
			document.addEventListener('pointermove', onMove)
			document.addEventListener('pointerup', onUp)
		},

		onKeyDown(e) {
			if (e.shiftKey && e.key === '!') {
				e.preventDefault()
				this.centreView()
			}
		},
	},
}
</script>

<style scoped>
.canvas-viewer {
	width: 100%;
	height: 100%;
	overflow: hidden;
	position: relative;
	background: var(--color-main-background);
	cursor: grab;
	user-select: none;
}

.canvas-viewer:active {
	cursor: grabbing;
}

.canvas-viewer__svg {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	pointer-events: none;
}

.canvas-viewer__edge {
	stroke: var(--color-text-maxcontrast);
	stroke-width: 2;
	fill: none;
}

.canvas-viewer__edge-label-bg {
	fill: var(--color-main-background);
	fill-opacity: 0.85;
	stroke: var(--color-border);
	stroke-width: 0.5;
}

.canvas-viewer__edge-label {
	font-size: 12px;
	font-weight: 500;
	fill: var(--color-main-text);
	text-anchor: middle;
	dominant-baseline: auto;
	pointer-events: none;
}

.canvas-viewer__nodes {
	position: absolute;
	top: 0;
	left: 0;
}

.canvas-viewer__node {
	position: absolute;
	border-radius: 8px;
	border: 1.5px solid var(--color-border-dark);
	background: var(--color-main-background);
	overflow: hidden;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
	display: flex;
	flex-direction: column;
}

.canvas-viewer__node--group {
	background: var(--color-background-dark);
	border-style: dashed;
	border-color: var(--color-text-maxcontrast);
	box-shadow: none;
	opacity: 0.7;
}

.canvas-viewer__node--coloured {
	border-left-style: solid;
}

.canvas-viewer__card-body {
	padding: 10px 14px;
	font-size: 13px;
	line-height: 1.55;
	overflow: auto;
	flex: 1;
}

.canvas-viewer__card-body :deep(h1),
.canvas-viewer__card-body :deep(h2),
.canvas-viewer__card-body :deep(h3) {
	margin-top: 0;
	margin-bottom: 6px;
}

.canvas-viewer__card-body :deep(p) {
	margin: 0 0 6px;
}

.canvas-viewer__card-body :deep(ul),
.canvas-viewer__card-body :deep(ol) {
	margin: 0 0 6px;
	padding-left: 1.4em;
}

.canvas-viewer__card-body :deep(li) {
	list-style: disc;
}

.canvas-viewer__card-body :deep(code) {
	background: var(--color-background-dark);
	padding: 1px 4px;
	border-radius: 3px;
	font-size: 12px;
}

.canvas-viewer__file-header {
	padding: 6px 12px;
	font-size: 12px;
	font-weight: 600;
	background: var(--color-background-dark);
	border-bottom: 1px solid var(--color-border);
	color: var(--color-text-maxcontrast);
	flex-shrink: 0;
}

.canvas-viewer__file-icon {
	margin-right: 4px;
}

.canvas-viewer__group-label {
	padding: 4px 10px;
	font-size: 12px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.canvas-viewer__link-header {
	display: flex;
	align-items: center;
	padding: 6px 12px;
	gap: 6px;
	background: var(--color-background-dark);
	border-bottom: 1px solid var(--color-border);
	flex-shrink: 0;
}

.canvas-viewer__link-icon {
	font-size: 14px;
	flex-shrink: 0;
}

.canvas-viewer__link-header a {
	color: var(--color-primary-element);
	text-decoration: underline;
	font-size: 12px;
	cursor: pointer;
	pointer-events: auto;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.canvas-viewer__link-frame {
	flex: 1;
	width: 100%;
	border: none;
	background: var(--color-main-background);
	pointer-events: none;
}

.canvas-viewer__link-fallback {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 8px;
	color: var(--color-text-maxcontrast);
}

.canvas-viewer__link-fallback-icon {
	font-size: 28px;
}

.canvas-viewer__link-fallback a {
	color: var(--color-primary-element);
	text-decoration: underline;
	font-size: 13px;
	cursor: pointer;
	pointer-events: auto;
}

.canvas-viewer__controls {
	position: absolute;
	top: 8px;
	right: 8px;
	display: flex;
	gap: 4px;
	z-index: 10;
}

.canvas-viewer__controls button {
	width: 32px;
	height: 28px;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	cursor: pointer;
	font-size: 13px;
	font-weight: 600;
	display: flex;
	align-items: center;
	justify-content: center;
}

.canvas-viewer__controls button:hover {
	background: var(--color-background-hover);
}
</style>
