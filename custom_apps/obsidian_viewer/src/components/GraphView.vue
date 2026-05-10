<template>
	<div ref="container" class="graph-view" />
</template>

<script>
import * as d3 from 'd3'
import { matchesQuery } from '../services/searchMatcher.js'

export default {
	name: 'GraphView',

	props: {
		graphData: {
			type: Object,
			required: true,
		},
		currentFile: {
			type: String,
			default: null,
		},
		settings: {
			type: Object,
			default: null,
		},
	},

	emits: ['navigate'],

	data() {
		return {
			simulation: null,
			resizeObserver: null,
		}
	},

	watch: {
		graphData() {
			this._dataChanged = true
			this.renderGraph()
		},
		currentFile() {
			this.updateHighlight()
		},
		settings: {
			deep: true,
			handler() {
				this.applySettings()
			},
		},
	},

	mounted() {
		this._dataChanged = true
		this._lastWidth = 0
		this._lastHeight = 0
		this.resizeObserver = new ResizeObserver(() => {
			const w = this.$refs.container?.clientWidth || 0
			const h = this.$refs.container?.clientHeight || 0
			if (w === this._lastWidth && h === this._lastHeight) return
			this._lastWidth = w
			this._lastHeight = h
			this.renderGraph()
		})
		this.resizeObserver.observe(this.$refs.container)
		this.renderGraph()
	},

	beforeDestroy() {
		if (this.simulation) this.simulation.stop()
		if (this.resizeObserver) this.resizeObserver.disconnect()
		this._animationInterval = null
		if (this._animationFrame) cancelAnimationFrame(this._animationFrame)
		if (this._animationTimer) clearTimeout(this._animationTimer)
	},

	methods: {
		renderGraph() {
			if (!this.$refs.container || !this.graphData) return
			this._animationInterval = null
			if (this._animationFrame) cancelAnimationFrame(this._animationFrame)
			if (this._animationTimer) clearTimeout(this._animationTimer)

			const container = this.$refs.container
			const width = container.clientWidth || 800
			const height = container.clientHeight || 600
			this._lastWidth = width
			this._lastHeight = height
			const s = this.settings || {}
			const forces = s.forces || {}
			const display = s.display || {}

			d3.select(container).selectAll('*').remove()
			if (this.simulation) this.simulation.stop()

			const progressiveLoad = !!this._dataChanged
			this._dataChanged = false

			const nodes = this.graphData.nodes.map((n) => ({ ...n }))
			const edges = this.graphData.edges.map((e) => ({ source: e.source, target: e.target, caseMismatch: !!e.caseMismatch }))

			const linkCount = {}
			edges.forEach((e) => {
				linkCount[e.source] = (linkCount[e.source] || 0) + 1
				linkCount[e.target] = (linkCount[e.target] || 0) + 1
			})
			this._linkCount = linkCount

			this._neighbourMap = {}
			edges.forEach((e) => {
				if (!this._neighbourMap[e.source]) this._neighbourMap[e.source] = new Set()
				if (!this._neighbourMap[e.target]) this._neighbourMap[e.target] = new Set()
				this._neighbourMap[e.source].add(e.target)
				this._neighbourMap[e.target].add(e.source)
			})

			const baseSize = display.nodeSize ?? 4

			this._radiusMap = {}
			nodes.forEach((n) => {
				this._radiusMap[n.id] = this.calcRadius(n, baseSize, linkCount)
			})

			if (progressiveLoad) {
				nodes.forEach((n) => {
					n.x = width / 2 + (Math.random() - 0.5) * 2
					n.y = height / 2 + (Math.random() - 0.5) * 2
				})
			}

			const svg = d3.select(container)
				.append('svg')
				.attr('width', width)
				.attr('height', height)

			const arrowSize = 8
			svg.append('defs').append('marker')
				.attr('id', 'ov-arrowhead')
				.attr('viewBox', '0 -5 10 10')
				.attr('refX', 0)
				.attr('refY', 0)
				.attr('markerWidth', arrowSize)
				.attr('markerHeight', arrowSize)
				.attr('markerUnits', 'userSpaceOnUse')
				.attr('orient', 'auto')
				.append('path')
				.attr('d', 'M0,-5L10,0L0,5')
				.attr('fill', '#b0b0b0')

			const g = svg.append('g')

			this._currentZoom = 1
			this._hoveredNode = null

			const zoom = d3.zoom()
				.scaleExtent([0.1, 4])
				.on('zoom', (event) => {
					g.attr('transform', event.transform)
					this._currentZoom = event.transform.k
					if (!this._hoveredNode) {
						this.applyLabelFade()
					}
				})
			svg.call(zoom)
			this._zoom = zoom
			this._svg = svg

			const baseThickness = display.linkThickness ?? 1
			const link = g.append('g')
				.selectAll('line')
				.data(edges)
				.join('line')
				.attr('class', 'graph-edge')
				.style('stroke-width', baseThickness)
				.style('stroke', (d) => d.caseMismatch ? '#e05050' : '#b0b0b0')
				.style('stroke-dasharray', (d) => d.caseMismatch ? '5 3' : null)

			if (display.arrows) {
				link.attr('marker-end', 'url(#ov-arrowhead)')
			}

			const node = g.append('g')
				.selectAll('circle')
				.data(nodes)
				.join('circle')
				.attr('class', (d) => 'graph-node graph-node--' + (d.type || 'md'))
				.attr('r', (d) => this.calcRadius(d, baseSize, linkCount))
				.attr('fill', (d) => this.nodeColor(d))
				.attr('stroke', 'var(--color-main-background)')
				.attr('stroke-width', 1.5)
				.attr('stroke-dasharray', (d) => d.type === 'unresolved' ? '3 2' : null)
				.style('cursor', 'pointer')
				.on('click', (_event, d) => {
					if (d.type !== 'tag' && d.type !== 'unresolved') {
						this.$emit('navigate', d.id)
					}
				})
				.on('mouseover', (_event, d) => {
					this._hoveredNode = d.id
					this.applyHoverHighlight(d.id)
				})
				.on('mouseout', () => {
					this._hoveredNode = null
					this.applyFilters()
				})

			const label = g.append('g')
				.selectAll('text')
				.data(nodes)
				.join('text')
				.attr('class', 'graph-label')
				.text((d) => d.name)
				.attr('dx', 10)
				.attr('dy', 4)
				.style('font-size', '11px')
				.style('fill', 'var(--color-main-text)')
				.style('pointer-events', 'none')
				.style('opacity', 0.8)

			const drag = d3.drag()
				.on('start', (event, d) => {
					if (!event.active) this.simulation.alphaTarget(0.3).restart()
					d.fx = d.x
					d.fy = d.y
				})
				.on('drag', (event, d) => {
					d.fx = event.x
					d.fy = event.y
				})
				.on('end', (event, d) => {
					if (!event.active) this.simulation.alphaTarget(0)
					d.fx = null
					d.fy = null
				})
			node.call(drag)

			const centerVal = forces.centerForce ?? 0.52
			const repelVal = forces.repelForce ?? 10
			const d3Repel = this.mapRepel(repelVal)
			const d3Center = centerVal * centerVal * 0.3

			this.simulation = d3.forceSimulation(nodes)
				.force('link', d3.forceLink(edges).id((d) => d.id)
					.distance(forces.linkDistance ?? 250)
					.strength(forces.linkForce ?? 1))
				.force('charge', d3.forceManyBody()
					.strength(-d3Repel))
				.force('x', d3.forceX(width / 2)
					.strength(d3Center))
				.force('y', d3.forceY(height / 2)
					.strength(d3Center))
				.force('collide', d3.forceCollide(20))
				.velocityDecay(0.4)
				.on('tick', () => {
					const showArrows = this.settings?.display?.arrows
					link
						.attr('x1', (d) => d.source.x)
						.attr('y1', (d) => d.source.y)
						.attr('x2', (d) => {
							if (!showArrows) return d.target.x
							const dx = d.target.x - d.source.x
							const dy = d.target.y - d.source.y
							const dist = Math.sqrt(dx * dx + dy * dy) || 1
							const r = (this._radiusMap[d.target.id] || baseSize) + arrowSize + 1
							return d.target.x - (dx / dist) * r
						})
						.attr('y2', (d) => {
							if (!showArrows) return d.target.y
							const dx = d.target.x - d.source.x
							const dy = d.target.y - d.source.y
							const dist = Math.sqrt(dx * dx + dy * dy) || 1
							const r = (this._radiusMap[d.target.id] || baseSize) + arrowSize + 1
							return d.target.y - (dy / dist) * r
						})
					node
						.attr('cx', (d) => d.x)
						.attr('cy', (d) => d.y)
					label
						.attr('x', (d) => d.x)
						.attr('y', (d) => d.y)
				})

			this._nodeSelection = node
			this._linkSelection = link
			this._labelSelection = label

			if (progressiveLoad) {
				nodes.forEach((n) => { n.fx = n.x; n.fy = n.y })
				node.style('opacity', 0)
				label.style('opacity', 0)
				link.style('opacity', 0)

				const visibleNodes = nodes.filter((n) => !this.isNodeHidden(n))
				const revealedIds = new Set()

				if (this._animateMode) {
					this._animateMode = false
					let idx = 0
					this._animationInterval = true
					const revealNext = () => {
						if (!this._animationInterval) return
						if (idx >= visibleNodes.length) {
							this._animationInterval = null
							this.centerOnHub(nodes, linkCount, svg, zoom, width, height)
							return
						}
						const n = visibleNodes[idx]
						n.fx = null
						n.fy = null
						node.filter((d) => d.id === n.id).style('opacity', (d) => this.isNodeHidden(d) ? 0 : 1)
						label.filter((d) => d.id === n.id).style('opacity', (d) => this.isNodeHidden(d) ? 0 : 0.8)
						revealedIds.add(n.id)
						link.filter((d) => {
							const sid = typeof d.source === 'object' ? d.source.id : d.source
							const tid = typeof d.target === 'object' ? d.target.id : d.target
							return revealedIds.has(sid) && revealedIds.has(tid)
						}).style('opacity', 1)
						this.simulation.alpha(0.5).restart()
						idx++
						this._animationTimer = setTimeout(revealNext, 300)
					}
					revealNext()
				} else {
					this.simulation.stop()
					visibleNodes.forEach((n) => { n.fx = null; n.fy = null })
					for (let i = 0; i < 300; i++) this.simulation.tick()

					node.attr('cx', (d) => d.x).attr('cy', (d) => d.y)
					label.attr('x', (d) => d.x).attr('y', (d) => d.y)
					link
						.attr('x1', (d) => d.source.x)
						.attr('y1', (d) => d.source.y)
						.attr('x2', (d) => d.target.x)
						.attr('y2', (d) => d.target.y)

					this.centerOnHub(nodes, linkCount, svg, zoom, width, height)
					this.simulation.alpha(0.05).restart()
					this.applyFilters()
				}
			} else {
				this.applyFilters()
			}
		},

		centerOnHub(nodes, linkCount, svg, zoom, width, height) {
			const hub = nodes.reduce((best, n) =>
				(linkCount[n.id] || 0) > (linkCount[best.id] || 0) ? n : best, nodes[0])
			if (hub) {
				const tx = width / 2 - hub.x
				const ty = height / 2 - hub.y
				svg.call(zoom.transform, d3.zoomIdentity.translate(tx, ty))
			}
		},

		mapRepel(val) {
			return val * val * 5
		},

		calcRadius(d, baseSize, linkCount) {
			const count = linkCount[d.id] || 0
			return baseSize + Math.sqrt(count) * 1.5
		},

		nodeColor(d) {
			if (d.id === this.currentFile) return '#e05050'

			const groups = this.settings?.groups || []
			for (const group of groups) {
				if (group.query && this.matchesGroup(d, group.query)) {
					return group.color
				}
			}

			if (d.type === 'tag') return '#49b583'
			if (d.type === 'unresolved') return '#c0c0c0'
			return '#666'
		},

		matchesGroup(node, query) {
			return matchesQuery(node, query)
		},

		isNodeHidden(d) {
			const filters = this.settings?.filters || {}
			const showTags = filters.tags !== false
			const showOrphans = filters.orphans !== false
			const existingOnly = filters.existingOnly === true
			const linkCount = this._linkCount || {}

			if (d.type === 'tag' && !showTags) return true
			if (d.type === 'unresolved' && existingOnly) return true
			if (!showOrphans && !linkCount[d.id]) return true
			return false
		},

		updateHighlight() {
			if (!this._nodeSelection) return
			this._nodeSelection.attr('fill', (d) => this.nodeColor(d))
		},

		applyHoverHighlight(hoveredId) {
			const neighbours = this._neighbourMap[hoveredId] || new Set()
			const connected = new Set([hoveredId, ...neighbours])

			this._nodeSelection
				.style('opacity', (d) => {
					if (this.isNodeHidden(d)) return 0
					return connected.has(d.id) ? 1 : 0.08
				})

			this._labelSelection
				.style('opacity', (d) => {
					if (this.isNodeHidden(d)) return 0
					return connected.has(d.id) ? 0.9 : 0
				})

			this._linkSelection
				.style('opacity', (d) => {
					const sid = typeof d.source === 'object' ? d.source.id : d.source
					const tid = typeof d.target === 'object' ? d.target.id : d.target
					const sNode = this.graphData.nodes.find((n) => n.id === sid)
					const tNode = this.graphData.nodes.find((n) => n.id === tid)
					if (sNode && this.isNodeHidden(sNode)) return 0
					if (tNode && this.isNodeHidden(tNode)) return 0
					return (sid === hoveredId || tid === hoveredId) ? 1 : 0.03
				})

			this._nodeSelection
				.filter((d) => d.id === hoveredId)
				.attr('fill', 'var(--color-primary-element)')
		},

		applyLabelFade() {
			if (!this._labelSelection) return
			const threshold = this.settings?.display?.textFadeThreshold ?? 1.5
			const k = this._currentZoom || 1
			const labelOpacity = Math.max(0, Math.min(0.8, (k - threshold + 0.5) * 1.6))
			const search = (this.settings?.filters?.search || '').trim()
			this._labelSelection.style('opacity', (d) => {
				if (this.isNodeHidden(d)) return 0
				if (search && !matchesQuery(d, search)) return 0
				return labelOpacity
			})
		},

		applySettings() {
			if (!this._nodeSelection || !this._linkSelection || !this.simulation) return
			const s = this.settings || {}
			const forces = s.forces || {}
			const display = s.display || {}
			const baseSize = display.nodeSize ?? 4
			const linkCount = this._linkCount || {}

			this._nodeSelection
				.attr('r', (d) => this.calcRadius(d, baseSize, linkCount))
				.attr('fill', (d) => this.nodeColor(d))

			this._linkSelection.style('stroke-width', display.linkThickness ?? 1)

			if (display.arrows) {
				this._linkSelection.attr('marker-end', 'url(#ov-arrowhead)')
			} else {
				this._linkSelection.attr('marker-end', null)
			}

			const cv = forces.centerForce ?? 0.52
			const cs = cv * cv * 0.3
			this.simulation.force('x').strength(cs)
			this.simulation.force('y').strength(cs)
			this.simulation.force('charge').strength(-this.mapRepel(forces.repelForce ?? 10))
			const linkForce = this.simulation.force('link')
			if (linkForce) {
				linkForce.strength(forces.linkForce ?? 1)
				linkForce.distance(forces.linkDistance ?? 250)
			}
			this.simulation.alpha(0.3).restart()

			this.applyFilters()
		},

		applyFilters() {
			if (!this._nodeSelection || !this._linkSelection || !this._labelSelection) return
			const search = (this.settings?.filters?.search || '').trim()
			let needsReheat = false

			this._nodeSelection
				.attr('fill', (d) => this.nodeColor(d))
				.style('opacity', (d) => {
					if (this.isNodeHidden(d)) return 0
					if (search && !matchesQuery(d, search)) return 0.08
					return 1
				})
				.style('pointer-events', (d) => {
					if (this.isNodeHidden(d)) return 'none'
					return 'auto'
				})
				.each((d) => {
					if (!this.isNodeHidden(d) && d.fx !== undefined && d.fx !== null) {
						d.fx = null
						d.fy = null
						needsReheat = true
					}
				})

			this.applyLabelFade()

			this._linkSelection.style('opacity', (d) => {
				const sid = typeof d.source === 'object' ? d.source.id : d.source
				const tid = typeof d.target === 'object' ? d.target.id : d.target
				const sNode = this.graphData.nodes.find((n) => n.id === sid)
				const tNode = this.graphData.nodes.find((n) => n.id === tid)
				if (sNode && this.isNodeHidden(sNode)) return 0
				if (tNode && this.isNodeHidden(tNode)) return 0
				if (search) {
					if (!matchesQuery(sNode || {}, search) && !matchesQuery(tNode || {}, search)) return 0.05
				}
				return 1
			})

			if (needsReheat && this.simulation) {
				this.simulation.alpha(0.5).restart()
			}
		},

		animate() {
			if (this._animationFrame) cancelAnimationFrame(this._animationFrame)
			this._animateMode = true
			this._dataChanged = true
			this.renderGraph()
		},
	},
}
</script>

<style scoped>
.graph-view {
	width: 100%;
	height: 100%;
	overflow: hidden;
	background: var(--color-main-background);
}

.graph-view :deep(.graph-edge) {
	stroke: #b0b0b0;
	stroke-opacity: 0.6;
}
</style>
